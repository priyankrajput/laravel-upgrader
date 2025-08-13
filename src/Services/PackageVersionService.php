<?php

namespace priyank\LaravelUpgrader\Services;

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
            $latestVersion = $this->getLatestVersionFromPackagist($package);
            
            $hasUpdate = $latestVersion && 
                        $currentVersion && 
                        $this->versionCompare($currentVersion, $latestVersion) < 0;

            $updates[$package] = [
                'current' => $currentVersion,
                'target' => $latestVersion,
                'constraint' => $constraint,
                'has_update' => $hasUpdate,
                'selected' => $hasUpdate,
                'versions' => $this->getAllStableVersionsFromPackagist($package)
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
                    
                    $latest = !empty($stableVersions) ? max($stableVersions) : (count($versions) ? max($versions) : null);
                    return $latest ? ltrim($latest, 'v') : null;
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch latest version for {$package}: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Get all stable versions of a package
     */
    public function getAllStableVersionsFromPackagist($package)
    {
        try {
            $response = Http::timeout($this->config['sources']['packagist']['timeout'] ?? 10)
                ->get("{$this->config['sources']['packagist']['api_url']}/{$package}.json");

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['packages'][$package])) {
                    $versions = array_column($data['packages'][$package], 'version');
                    $stable = array_filter($versions, function($v) {
                        return !preg_match('/(?:dev|alpha|beta|RC)/i', $v);
                    });
                    
                    if (empty($stable)) {
                        $stable = $versions; // fallback if no stable found
                    }
                    
                    $normalized = array_map(fn($v) => ltrim($v, 'v'), $stable);
                    usort($normalized, 'version_compare');
                    return array_reverse($normalized); // newest first
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch versions for {$package}: " . $e->getMessage());
        }
        
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
