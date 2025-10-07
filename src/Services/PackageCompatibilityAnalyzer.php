<?php

namespace priyankrajput\LaravelUpgrader\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use priyankrajput\LaravelUpgrader\Services\LaravelCompatibilityService;

class PackageCompatibilityAnalyzer
{
    protected $laravelCompatibility;
    protected $cacheTime = 1800; // 30 minutes

    public function __construct(LaravelCompatibilityService $laravelCompatibility)
    {
        $this->laravelCompatibility = $laravelCompatibility;
    }

    /**
     * Analyze compatibility for all packages in composer.json
     */
    public function analyzeProjectCompatibility(string $targetLaravelVersion = null): array
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $require = $composer['require'] ?? [];
        $requireDev = $composer['require-dev'] ?? [];

        $allPackages = array_merge($require, $requireDev);

        // Remove PHP and Laravel framework itself from analysis
        unset($allPackages['php'], $allPackages['laravel/framework']);

        $analysis = [];
        $targetVersion = $targetLaravelVersion ?? $this->laravelCompatibility->getCurrentLaravelVersion();

        foreach ($allPackages as $package => $constraint) {
            $analysis[$package] = $this->analyzePackageCompatibility($package, $constraint, $targetVersion);
        }

        return [
            'target_laravel_version' => $targetVersion,
            'current_laravel_version' => $this->laravelCompatibility->getCurrentLaravelVersion(),
            'packages' => $analysis,
            'summary' => $this->generateSummary($analysis)
        ];
    }

    /**
     * Analyze compatibility for a specific package
     */
    public function analyzePackageCompatibility(string $packageName, string $currentConstraint, string $targetLaravelVersion): array
    {
        $cacheKey = "package_analysised_{$packageName}_{$currentConstraint}_{$targetLaravelVersion}";

        return Cache::remember($cacheKey, $this->cacheTime, function() use ($packageName, $currentConstraint, $targetLaravelVersion) {
            $compatibility = $this->laravelCompatibility->getPackageLaravelCompatibility($packageName, null, $targetLaravelVersion);

            $currentLaravel = $this->laravelCompatibility->getCurrentLaravelVersion();

            return [
                'package' => $packageName,
                'current_constraint' => $currentConstraint,
                'current_laravel' => $currentLaravel,
                'target_laravel' => $targetLaravelVersion,
                'compatibility' => $compatibility,
                'upgrade_required' => !$compatibility['compatible'],
                'risk_level' => $this->assessRiskLevel($compatibility, $currentLaravel, $targetLaravelVersion),
                'recommended_action' => $this->getRecommendedAction($compatibility, $packageName),
                'code_changes' => $this->getRequiredCodeChanges($packageName, $currentLaravel, $targetLaravelVersion)
            ];
        });
    }

    /**
     * Assess the risk level of upgrading a package
     */
    protected function assessRiskLevel(array $compatibility, string $currentLaravel, string $targetLaravelVersion): string
    {
        if ($compatibility['compatible']) {
            return 'low';
        }

        $currentMajor = (int) explode('.', $currentLaravel)[0];
        $targetMajor = (int) explode('.', $targetLaravelVersion)[0];

        $majorDifference = abs($targetMajor - $currentMajor);

        if ($majorDifference >= 2) {
            return 'high';
        } elseif ($majorDifference === 1) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get recommended action for a package
     */
    protected function getRecommendedAction(array $compatibility, string $packageName): string
    {
        if ($compatibility['compatible']) {
            return 'No action required - package is compatible';
        }

        if (!empty($compatibility['alternatives'])) {
            return 'Consider replacing with: ' . implode(', ', $compatibility['alternatives']);
        }

        if (!empty($compatibility['supported_versions'])) {
            return 'Update constraint to support Laravel ' . $compatibility['recommended_version'];
        }

        return 'Manual review required - no clear upgrade path';
    }

    /**
     * Get required code changes for package upgrade
     */
    protected function getRequiredCodeChanges(string $packageName, string $currentLaravel, string $targetLaravelVersion): array
    {
        $changes = [];

        // Package-specific code changes
        switch ($packageName) {
            case 'laravel/sanctum':
                if (version_compare($currentLaravel, '11.0', '<') && version_compare($targetLaravelVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'config',
                        'file' => 'config/sanctum.php',
                        'description' => 'Review Sanctum configuration for Laravel 11 compatibility',
                        'action' => 'update_config'
                    ];
                }
                break;

            case 'spatie/laravel-permission':
                if (version_compare($currentLaravel, '10.0', '<') && version_compare($targetLaravelVersion, '10.0', '>=')) {
                    $changes[] = [
                        'type' => 'migration',
                        'description' => 'Run php artisan permission:cache-reset after upgrade',
                        'action' => 'artisan_command'
                    ];
                }
                break;

            case 'barryvdh/laravel-debugbar':
                if (version_compare($targetLaravelVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'dependency',
                        'description' => 'Ensure compatibility with Laravel 11+',
                        'action' => 'check_compatibility'
                    ];
                }
                break;

            case 'laravel/ui':
                if (version_compare($targetLaravelVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'alternative',
                        'description' => 'Consider using Laravel Breeze or Livewire instead of laravel/ui',
                        'action' => 'consider_alternative'
                    ];
                }
                break;

            default:
                // Generic changes for major version upgrades
                $currentMajor = (int) explode('.', $currentLaravel)[0];
                $targetMajor = (int) explode('.', $targetLaravelVersion)[0];

                if ($targetMajor > $currentMajor) {
                    $changes[] = [
                        'type' => 'review',
                        'description' => 'Review package usage for breaking changes in Laravel ' . $targetLaravelVersion,
                        'action' => 'manual_review'
                    ];
                }
        }

        return $changes;
    }

    /**
     * Generate summary of compatibility analysis
     */
    protected function generateSummary(array $analysis): array
    {
        $total = count($analysis);
        $compatible = 0;
        $incompatible = 0;
        $highRisk = 0;
        $mediumRisk = 0;
        $lowRisk = 0;

        foreach ($analysis as $package) {
            if ($package['compatibility']['compatible']) {
                $compatible++;
            } else {
                $incompatible++;

                switch ($package['risk_level']) {
                    case 'high':
                        $highRisk++;
                        break;
                    case 'medium':
                        $mediumRisk++;
                        break;
                    case 'low':
                        $lowRisk++;
                        break;
                }
            }
        }

        return [
            'total_packages' => $total,
            'compatible' => $compatible,
            'incompatible' => $incompatible,
            'compatibility_rate' => $total > 0 ? round(($compatible / $total) * 100, 1) : 0,
            'risk_distribution' => [
                'high' => $highRisk,
                'medium' => $mediumRisk,
                'low' => $lowRisk
            ]
        ];
    }

    /**
     * Get upgrade suggestions for incompatible packages
     */
    public function getUpgradeSuggestions(array $analysis, string $targetLaravelVersion): array
    {
        $suggestions = [];

        foreach ($analysis as $packageName => $package) {
            if (!$package['compatibility']['compatible']) {
                $suggestions[$packageName] = [
                    'current_version' => $package['current_constraint'],
                    'recommended_action' => $package['recommended_action'],
                    'alternatives' => $package['compatibility']['alternatives'],
                    'code_changes' => $package['code_changes'],
                    'risk_level' => $package['risk_level']
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Check if upgrade is safe based on analysis
     */
    public function isUpgradeSafe(array $analysis): bool
    {
        $highRiskPackages = array_filter($analysis, function($package) {
            return $package['risk_level'] === 'high';
        });

        // Consider upgrade safe if no high-risk packages or very few
        return count($highRiskPackages) <= 2;
    }
}
