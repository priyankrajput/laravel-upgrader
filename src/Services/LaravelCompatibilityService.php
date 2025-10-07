<?php

namespace priyankrajput\LaravelUpgrader\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LaravelCompatibilityService
{
    protected $cacheTime = 3600; // 1 hour

    /**
     * Get Laravel version compatibility information for a package
     */
    public function getPackageLaravelCompatibility(string $packageName, string $packageVersion = null, string $compareLaravelVersion = null): array
    {
        $cacheKey = "laravel_compatibilitydd_{$packageName}_" . ($packageVersion ?? 'latest') . '_' . ($compareLaravelVersion ?? 'current');

        return Cache::remember($cacheKey, $this->cacheTime, function() use ($packageName, $packageVersion, $compareLaravelVersion) {
            try {
                // Try to get information from Packagist
                $packagistData = $this->getPackagistData($packageName);

                if (!$packagistData) {
                    return [
                        'compatible' => false,
                        'supported_versions' => [],
                        'recommended_version' => null,
                        'alternatives' => [],
                        'error' => 'Package not found on Packagist'
                    ];
                }

                $supportedVersions = $this->extractLaravelVersions($packagistData);

                if (empty($supportedVersions)) {
                    // Fallback: attempt to fetch composer.json from repository (GitHub) and parse constraints
                    $repoConstraints = $this->fetchRepositoryConstraints($packagistData);
                    if (!empty($repoConstraints)) {
                        $supportedVersions = ['dev-default' => $this->parseLaravelConstraint($repoConstraints)];
                    }
                }

                if (empty($supportedVersions)) {
                    // Still unknown: mark as unknown rather than blindly compatible
                    $currentLaravel = $compareLaravelVersion ?? $this->getCurrentLaravelVersion();
                    return [
                        'compatible' => true, // default to true to avoid false negatives
                        'current_laravel' => $currentLaravel,
                        'supported_versions' => [],
                        'recommended_version' => null,
                        'alternatives' => [],
                        'compatibility_notes' => ['No Laravel/Illuminate constraints found on Packagist or repository. Assuming compatible; manual review advised.']
                    ];
                }

                $currentLaravel = $compareLaravelVersion ?? $this->getCurrentLaravelVersion();
                $compatible = $this->isCompatible($supportedVersions, $currentLaravel);

                return [
                    'compatible' => $compatible,
                    'current_laravel' => $currentLaravel,
                    'supported_versions' => $supportedVersions,
                    'recommended_version' => $this->getRecommendedVersion($supportedVersions, $currentLaravel),
                    'alternatives' => $compatible ? [] : $this->findAlternatives($packageName),
                    'compatibility_notes' => $this->getCompatibilityNotes($supportedVersions, $currentLaravel)
                ];

            } catch (\Exception $e) {
                return [
                    'compatible' => false,
                    'supported_versions' => [],
                    'recommended_version' => null,
                    'alternatives' => [],
                    'error' => 'Error checking compatibility: ' . $e->getMessage()
                ];
            }
        });
    }

    /**
     * Get current Laravel version
     */
    public function getCurrentLaravelVersion(): string
    {
        return app()->version();
    }

    /**
     * Get package data from Packagist
     */
    protected function getPackagistData(string $packageName): ?array
    {
        try {
            $response = Http::timeout(10)->get("https://repo.packagist.org/p2/{$packageName}.json");

            if ($response->successful()) {
                $data = $response->json();
                return $data['packages'][$packageName] ?? null;
            }
        } catch (\Exception $e) {
            // Log error but don't throw
        }

        return null;
    }

    /**
     * Extract Laravel version requirements from package data
     */
    protected function extractLaravelVersions(array $packageVersions): array
    {
        $laravelVersions = [];

        foreach ($packageVersions as $version) {
            if (isset($version['require']['laravel/framework'])) {
                $constraint = $version['require']['laravel/framework'];
                $laravelVersions[$version['version']] = $this->parseLaravelConstraint($constraint);
            }

            // Consider any illuminate/* as proxy for Laravel compatibility
            if (isset($version['require']) && is_array($version['require'])) {
                foreach ($version['require'] as $reqName => $reqConstraint) {
                    if (strpos($reqName, 'illuminate/') === 0) {
                        $laravelVersions[$version['version']] = $this->parseLaravelConstraint($reqConstraint);
                        break;
                    }
                }
            }

            // Also scan require-dev for illuminate/* constraints
            if (isset($version['require-dev']) && is_array($version['require-dev'])) {
                foreach ($version['require-dev'] as $reqName => $reqConstraint) {
                    if (strpos($reqName, 'illuminate/') === 0) {
                        $laravelVersions[$version['version']] = $this->parseLaravelConstraint($reqConstraint);
                        break;
                    }
                }
            }
        }

        return $laravelVersions;
    }

    /**
     * Parse Laravel version constraint
     */
    protected function parseLaravelConstraint(string $constraint): array
    {
        // Handle common constraint formats
        $constraint = trim($constraint);

        // Support OR constraints like "^9.0|^10.0"
        if (strpos($constraint, '|') !== false) {
            $parts = array_map('trim', explode('|', $constraint));
            $parsed = [];
            foreach ($parts as $part) {
                $parsed[] = $this->parseLaravelConstraint($part);
            }
            return ['any_of' => $parsed, 'raw' => $constraint];
        }

        if (preg_match('/^(\^|~)?(\d+)\.(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $matches)) {
            $operator = $matches[1] ?? '^';
            $major = (int) $matches[2];
            $minor = (int) $matches[3];
            $patch = isset($matches[4]) ? (int) $matches[4] : 0;
            $build = isset($matches[5]) ? (int) $matches[5] : 0;

            return [
                'operator' => $operator,
                'major' => $major,
                'minor' => $minor,
                'patch' => $patch,
                'build' => $build,
                'constraint' => $constraint
            ];
        }

        // Handle range constraints like ">=8.0,<9.0"
        if (preg_match('/>=(\d+\.\d+).*?<(\d+\.\d+)/', $constraint, $matches)) {
            return [
                'operator' => '>=,<',
                'major' => (int) explode('.', $matches[1])[0],
                'minor' => (int) explode('.', $matches[1])[1],
                'max_major' => (int) explode('.', $matches[2])[0],
                'max_minor' => (int) explode('.', $matches[2])[1],
                'constraint' => $constraint
            ];
        }

        return [
            'operator' => '*',
            'constraint' => $constraint
        ];
    }

    /**
     * Check if package is compatible with current Laravel version
     */
    protected function isCompatible(array $supportedVersions, string $currentLaravel): bool
    {
        $currentMajor = (int) explode('.', $currentLaravel)[0];
        $currentMinor = (int) explode('.', $currentLaravel)[1];

        foreach ($supportedVersions as $version => $constraint) {
            if ($this->checkVersionCompatibility($constraint, $currentMajor, $currentMinor)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check version compatibility with constraint
     */
    protected function checkVersionCompatibility(array $constraint, int $currentMajor, int $currentMinor): bool
    {
        // Composite any_of
        if (isset($constraint['any_of']) && is_array($constraint['any_of'])) {
            foreach ($constraint['any_of'] as $sub) {
                if ($this->checkVersionCompatibility($sub, $currentMajor, $currentMinor)) {
                    return true;
                }
            }
            return false;
        }

        $operator = $constraint['operator'] ?? '*';

        switch ($operator) {
            case '^':
                $constraintMajor = $constraint['major'];
                $constraintMinor = $constraint['minor'];

                if ($currentMajor !== $constraintMajor) {
                    return false;
                }

                return $currentMinor >= $constraintMinor;

            case '~':
                $constraintMajor = $constraint['major'];
                $constraintMinor = $constraint['minor'];

                if ($currentMajor !== $constraintMajor) {
                    return false;
                }

                return $currentMinor >= $constraintMinor;

            case '>=':
            case '<':
                // Handle range constraints
                if (isset($constraint['max_major'])) {
                    return $currentMajor >= $constraint['major'] &&
                           $currentMinor >= $constraint['minor'] &&
                           ($currentMajor < $constraint['max_major'] ||
                            ($currentMajor === $constraint['max_major'] && $currentMinor < $constraint['max_minor']));
                }

                return version_compare("{$currentMajor}.{$currentMinor}", "{$constraint['major']}.{$constraint['minor']}", $operator);

            case '*':
                return true; // Wildcard means compatible with all versions

            default:
                return false;
        }
    }

    /**
     * Attempt to fetch composer.json from the repository to discover constraints.
     */
    protected function fetchRepositoryConstraints(array $packageVersions): ?string
    {
        // Find a version with a GitHub source URL
        foreach ($packageVersions as $version) {
            if (isset($version['source']['url'])) {
                $url = $version['source']['url'];
                // Expecting formats like https://github.com/vendor/name.git or git@github.com:vendor/name.git
                $repo = null;
                if (preg_match('#github.com[:/]+([\w\-\.]+)/([\w\-\.]+)(?:\.git)?$#i', $url, $m)) {
                    $repo = $m[1] . '/' . $m[2];
                }
                if (!$repo) {
                    continue;
                }
                $branches = ['main', 'master'];
                foreach ($branches as $branch) {
                    $rawUrl = "https://raw.githubusercontent.com/{$repo}/{$branch}/composer.json";
                    try {
                        $resp = Http::timeout(6)->get($rawUrl);
                        if ($resp->successful()) {
                            $composer = $resp->json();
                            if (isset($composer['require']['laravel/framework'])) {
                                return $composer['require']['laravel/framework'];
                            }
                            if (isset($composer['require'])) {
                                foreach ($composer['require'] as $reqName => $reqConstraint) {
                                    if (strpos($reqName, 'illuminate/') === 0) {
                                        return $reqConstraint;
                                    }
                                }
                            }
                            if (isset($composer['require-dev'])) {
                                foreach ($composer['require-dev'] as $reqName => $reqConstraint) {
                                    if (strpos($reqName, 'illuminate/') === 0) {
                                        return $reqConstraint;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore and try next
                    }
                }
            }
        }
        return null;
    }

    /**
     * Get recommended version for current Laravel installation
     */
    protected function getRecommendedVersion(array $supportedVersions, string $currentLaravel): ?string
    {
        $currentMajor = (int) explode('.', $currentLaravel)[0];
        $currentMinor = (int) explode('.', $currentLaravel)[1];

        $compatibleVersions = [];

        foreach ($supportedVersions as $version => $constraint) {
            if ($this->checkVersionCompatibility($constraint, $currentMajor, $currentMinor)) {
                $compatibleVersions[$version] = $constraint;
            }
        }

        if (empty($compatibleVersions)) {
            return null;
        }

        // Return the latest compatible version
        uksort($compatibleVersions, 'version_compare');
        return end(array_keys($compatibleVersions));
    }

    /**
     * Find alternative packages
     */
    protected function findAlternatives(string $packageName): array
    {
        // This could be expanded to use a predefined mapping or search similar packages
        $alternatives = [
            'barryvdh/laravel-debugbar' => ['laravel/telescope', 'spatie/laravel-ray'],
            'spatie/laravel-permission' => ['kodeine/laravel-acl', 'zizaco/entrust'],
            'intervention/image' => ['spatie/image', 'league/glide'],
            'maatwebsite/excel' => ['rap2hpoutre/fast-excel', 'box/spout'],
            'laravel/sanctum' => ['tymon/jwt-auth', 'spatie/laravel-permission'],
        ];

        return $alternatives[$packageName] ?? [];
    }

    /**
     * Get compatibility notes
     */
    protected function getCompatibilityNotes(array $supportedVersions, string $currentLaravel): array
    {
        $notes = [];
        $currentMajor = (int) explode('.', $currentLaravel)[0];

        foreach ($supportedVersions as $version => $constraint) {
            if (isset($constraint['major']) && $constraint['major'] !== $currentMajor) {
                if ($constraint['major'] > $currentMajor) {
                    $notes[] = "Package version {$version} requires Laravel {$constraint['constraint']} (newer than current {$currentLaravel})";
                } else {
                    $notes[] = "Package version {$version} supports Laravel {$constraint['constraint']} (older than current {$currentLaravel})";
                }
            }
        }

        return $notes;
    }

    /**
     * Get Laravel version from composer.json
     */
    public function getLaravelVersionFromComposer(): string
    {
        $composerFile = base_path('composer.json');

        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            return $composer['require']['laravel/framework'] ?? 'unknown';
        }

        return 'unknown';
    }

    /**
     * Get all Laravel major versions for compatibility matrix
     */
    public function getLaravelVersionsMatrix(): array
    {
        return [
            '9' => ['min_php' => '8.0', 'supported_until' => '2024-02', 'status' => 'security'],
            '10' => ['min_php' => '8.1', 'supported_until' => '2025-02', 'status' => 'active'],
            '11' => ['min_php' => '8.2', 'supported_until' => '2026-02', 'status' => 'active'],
            '12' => ['min_php' => '8.3', 'supported_until' => '2027-02', 'status' => 'active'],
        ];
    }
}
