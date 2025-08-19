<?php

namespace priyankrajput\LaravelUpgrader\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PackageVersionService
{
    protected $config;

    public function __construct()
    {
        $this->config = config('upgrader');
    }

    /**
     * Get current versions from composer.lock
     */
    public function getCurrentVersions()
    {
        $lockFile = base_path('composer.lock');
        $installed = json_decode(file_get_contents($lockFile), true);
        $versions = [];

        // Get versions from composer.lock "packages"
        foreach (($installed['packages'] ?? []) as $package) {
            $versions[$package['name']] = ltrim($package['version'], 'v');
        }

        // Get versions from composer.lock "packages-dev"
        foreach (($installed['packages-dev'] ?? []) as $package) {
            $versions[$package['name']] = ltrim($package['version'], 'v');
        }

        return $versions;
    }

    /**
     * Check for available updates
     */
    public function checkForUpdates($currentVersions, $allPackages)
    {
        $updates = [];
        
        foreach ($allPackages as $package => $constraint) {
            if ($package === 'php') {
                continue;
            }

            $currentVersion = $currentVersions[$package] ?? null;
            // Get all stable versions (newest first) and compute a correct target
            $allStableVersions = $this->getAllStableVersionsFromPackagist($package);
            $latestVersion = $allStableVersions[0] ?? null;
            // Choose the highest version that is greater than current (avoid downgrades)
            $targetVersion = $latestVersion;
            if ($currentVersion) {
                $targetVersion = null;
                foreach ($allStableVersions as $v) {
                    if ($this->versionCompare($currentVersion, $v) < 0) {
                        $targetVersion = $v;
                        break;
                    }
                }
            }
            
            $hasUpdate = $targetVersion &&
                        $currentVersion &&
                        $this->versionCompare($currentVersion, $targetVersion) < 0;

            $updates[$package] = [
                'current' => $currentVersion,
                'target' => $targetVersion ?: $latestVersion,
                'constraint' => $constraint,
                'has_update' => $hasUpdate,
                'selected' => $hasUpdate,
                // Only show upgrade options (>= current + strictly greater), to avoid user selecting downgrades
                'versions' => $currentVersion
                    ? array_values(array_filter($allStableVersions, fn($v) => $this->versionCompare($currentVersion, $v) < 0))
                    : $allStableVersions,
            ];
        }

        return $updates;
    }

    /**
     * Get the latest version of a package from Packagist
     */
    public function getLatestVersionFromPackagist($package)
    {
        try {
            $response = Http::timeout($this->config['sources']['packagist']['timeout'] ?? 10)
                ->get("{$this->config['sources']['packagist']['api_url']}/{$package}.json");

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['packages'][$package])) {
                    $versions = array_column($data['packages'][$package], 'version');
                    $stableVersions = array_filter($versions, function($v) {
                        return !preg_match('/(?:dev|alpha|beta|RC)/i', $v);
                    });

                    // Normalize (strip leading v) and sort using semantic version_compare
                    $normalized = array_map(fn($v) => ltrim($v, 'v'), $stableVersions ?: $versions);
                    usort($normalized, 'version_compare');
                    $normalized = array_reverse($normalized); // newest first
                    $latest = $normalized[0] ?? null;
                    return $latest ?: null;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch latest version for {$package}: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get all stable versions from Packagist for a package with caching
     */
    protected function getAllStableVersionsFromPackagist($package)
    {
        $cacheKey = "package_versions_{$package}";
        
        // Check cache first (1 hour TTL)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        try {
            $response = Http::timeout(10)->get("https://repo.packagist.org/p2/{$package}.json");
            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['packages'][$package])) {
                    $versions = array_column($data['packages'][$package], 'version');
                    // Filter for stable versions only
                    $stable = array_filter($versions, function($v) {
                        return !preg_match('/(?:dev|alpha|beta|RC)/i', $v);
                    });
                    if (empty($stable)) {
                        $stable = $versions; // fallback to all if none found
                    }
                    // Normalize and sort semantically
                    $normalized = array_map(function($v) {
                        return ltrim($v, 'v');
                    }, $stable);
                    usort($normalized, function($a, $b) {
                        return version_compare($b, $a); // Descending order (newest first)
                    });
                    
                    // Cache for 1 hour
                    Cache::put($cacheKey, $normalized, now()->addHour());
                    return $normalized;
                }
            }
        } catch (\Exception $e) {
            // Return empty array on failure
        }
        
        // Cache empty result for 5 minutes to avoid repeated failures
        Cache::put($cacheKey, [], now()->addMinutes(5));
        return [];
    }

    /**
     * Compare two version numbers
     */
    public function versionCompare($version1, $version2)
    {
        $version1 = ltrim($version1, 'v');
        $version2 = ltrim($version2, 'v');
        return version_compare($version1, $version2);
    }

    /**
     * Update composer.json with new constraints
     */
    public function updateComposerConstraints($selectedTargets)
    {
        $composerPath = base_path('composer.json');
        $composerJson = json_decode(file_get_contents($composerPath), true);
        
        foreach ($selectedTargets as $pkg => $ver) {
            if (isset($composerJson['require'][$pkg])) {
                $composerJson['require'][$pkg] = '^' . ltrim($ver, 'v');
            } elseif (isset($composerJson['require-dev'][$pkg])) {
                $composerJson['require-dev'][$pkg] = '^' . ltrim($ver, 'v');
            }
        }
        
        file_put_contents(
            $composerPath,
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Fetch changelogs for packages (best-effort via GitHub Releases API).
     * Expects $updates in the same structure returned by checkForUpdates().
     *
     * @param array $updates
     * @return array<string, array<int, array{version:string,date:string|null,notes:string|null}>>
     */
    public function getChangelogs(array $updates): array
    {
        $changelogs = [];

        foreach ($updates as $package => $data) {
            if (empty($data['has_update'])) {
                continue;
            }

            try {
                // Try to map composer package to GitHub repo path
                // Heuristic: vendor/name => vendor/name on GitHub
                $repo = $package; // e.g. laravel/framework
                $url = "https://api.github.com/repos/{$repo}/releases";

                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    $releases = $response->json();
                    if (is_array($releases)) {
                        $changelogs[$package] = array_map(function ($release) {
                            return [
                                'version' => $release['tag_name'] ?? '',
                                'date' => $release['published_at'] ?? null,
                                'notes' => $release['body'] ?? null,
                            ];
                        }, $releases);
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('Changelog fetch failed for '.$package.': '.$e->getMessage());
                continue;
            }
        }

        return $changelogs;
    }
}
