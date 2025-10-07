<?php

namespace priyankrajput\LaravelUpgrader\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CodeChangeRecommendationService
{
    protected $cacheTime = 3600; // 1 hour

    /**
     * Get code change recommendations for Laravel upgrade
     */
    public function getCodeChangeRecommendations(string $currentVersion, string $targetVersion): array
    {
        $cacheKey = "code_changes_{$currentVersion}_to_{$targetVersion}";

        return Cache::remember($cacheKey, $this->cacheTime, function() use ($currentVersion, $targetVersion) {
            $recommendations = [];

            // Get Laravel-specific changes
            $recommendations = array_merge($recommendations, $this->getLaravelCoreChanges($currentVersion, $targetVersion));

            // Get package-specific changes
            $recommendations = array_merge($recommendations, $this->getPackageSpecificChanges($currentVersion, $targetVersion));

            // Get configuration changes
            $recommendations = array_merge($recommendations, $this->getConfigurationChanges($currentVersion, $targetVersion));

            // Get middleware changes
            $recommendations = array_merge($recommendations, $this->getMiddlewareChanges($currentVersion, $targetVersion));

            return $this->prioritizeAndSortRecommendations($recommendations);
        });
    }

    /**
     * Get Laravel core changes between versions
     */
    protected function getLaravelCoreChanges(string $currentVersion, string $targetVersion): array
    {
        $changes = [];

        // Laravel 10 to 11 changes
        if (version_compare($currentVersion, '10.0', '>=') && version_compare($targetVersion, '11.0', '>=')) {
            $changes[] = [
                'type' => 'breaking',
                'category' => 'core',
                'title' => 'Application Structure Changes',
                'description' => 'Laravel 11 introduces new application structure. The bootstrap/app.php file is now the main entry point.',
                'files_affected' => ['bootstrap/app.php', 'config/app.php'],
                'action_required' => 'Update application structure to Laravel 11 conventions',
                'priority' => 'high',
                'difficulty' => 'medium',
                'estimated_time' => '30 minutes'
            ];

            $changes[] = [
                'type' => 'breaking',
                'category' => 'routing',
                'title' => 'Route Caching Changes',
                'description' => 'Route caching behavior has changed in Laravel 11.',
                'files_affected' => ['routes/*.php', 'bootstrap/app.php'],
                'action_required' => 'Update route definitions and caching strategy',
                'priority' => 'medium',
                'difficulty' => 'low',
                'estimated_time' => '15 minutes'
            ];
        }

        // Laravel 9 to 10 changes
        if (version_compare($currentVersion, '9.0', '>=') && version_compare($targetVersion, '10.0', '>=')) {
            $changes[] = [
                'type' => 'breaking',
                'category' => 'php',
                'title' => 'PHP 8.1+ Required',
                'description' => 'Laravel 10 requires PHP 8.1 or higher.',
                'files_affected' => ['composer.json'],
                'action_required' => 'Update PHP version requirement in composer.json',
                'priority' => 'high',
                'difficulty' => 'low',
                'estimated_time' => '5 minutes'
            ];
        }

        // Laravel 8 to 9 changes
        if (version_compare($currentVersion, '8.0', '>=') && version_compare($targetVersion, '9.0', '>=')) {
            $changes[] = [
                'type' => 'breaking',
                'category' => 'php',
                'title' => 'PHP 8.0+ Required',
                'description' => 'Laravel 9 requires PHP 8.0 or higher.',
                'files_affected' => ['composer.json'],
                'action_required' => 'Update PHP version requirement in composer.json',
                'priority' => 'high',
                'difficulty' => 'low',
                'estimated_time' => '5 minutes'
            ];

            $changes[] = [
                'type' => 'breaking',
                'category' => 'middleware',
                'title' => 'Middleware Changes',
                'description' => 'Several middleware have been moved or changed in Laravel 9.',
                'files_affected' => ['app/Http/Kernel.php', 'routes/*.php'],
                'action_required' => 'Update middleware references',
                'priority' => 'medium',
                'difficulty' => 'medium',
                'estimated_time' => '20 minutes'
            ];
        }

        // Laravel 7 to 8 changes
        if (version_compare($currentVersion, '7.0', '>=') && version_compare($targetVersion, '8.0', '>=')) {
            $changes[] = [
                'type' => 'breaking',
                'category' => 'models',
                'title' => 'Model Changes',
                'description' => 'Laravel 8 introduced significant changes to model factories and seeders.',
                'files_affected' => ['database/factories/*.php', 'database/seeders/*.php'],
                'action_required' => 'Update model factories and seeders to Laravel 8 syntax',
                'priority' => 'high',
                'difficulty' => 'medium',
                'estimated_time' => '45 minutes'
            ];
        }

        return $changes;
    }

    /**
     * Get package-specific changes
     */
    protected function getPackageSpecificChanges(string $currentVersion, string $targetVersion): array
    {
        $changes = [];

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $packages = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

        foreach ($packages as $package => $version) {
            $packageChanges = $this->getPackageUpgradeChanges($package, $currentVersion, $targetVersion);
            $changes = array_merge($changes, $packageChanges);
        }

        return $changes;
    }

    /**
     * Get changes for specific package upgrade
     */
    protected function getPackageUpgradeChanges(string $package, string $currentVersion, string $targetVersion): array
    {
        $changes = [];

        switch ($package) {
            case 'laravel/sanctum':
                if (version_compare($targetVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'enhancement',
                        'category' => 'auth',
                        'title' => 'Sanctum Improvements',
                        'description' => 'Laravel 11 includes improved Sanctum configuration.',
                        'files_affected' => ['config/sanctum.php'],
                        'action_required' => 'Review Sanctum configuration for Laravel 11 features',
                        'priority' => 'low',
                        'difficulty' => 'low',
                        'estimated_time' => '10 minutes'
                    ];
                }
                break;

            case 'spatie/laravel-permission':
                if (version_compare($targetVersion, '10.0', '>=')) {
                    $changes[] = [
                        'type' => 'breaking',
                        'category' => 'auth',
                        'title' => 'Permission Package Updates',
                        'description' => 'spatie/laravel-permission may have breaking changes for Laravel 10+.',
                        'files_affected' => ['config/permission.php', 'app/Models/User.php'],
                        'action_required' => 'Update permission configuration and model relationships',
                        'priority' => 'medium',
                        'difficulty' => 'medium',
                        'estimated_time' => '25 minutes'
                    ];
                }
                break;

            case 'barryvdh/laravel-debugbar':
                if (version_compare($targetVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'enhancement',
                        'category' => 'debug',
                        'title' => 'Debugbar Compatibility',
                        'description' => 'Debugbar may need updates for Laravel 11 compatibility.',
                        'files_affected' => ['config/debugbar.php'],
                        'action_required' => 'Verify Debugbar configuration with Laravel 11',
                        'priority' => 'low',
                        'difficulty' => 'low',
                        'estimated_time' => '5 minutes'
                    ];
                }
                break;

            case 'laravel/ui':
                if (version_compare($targetVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'deprecated',
                        'category' => 'ui',
                        'title' => 'Laravel UI Deprecation',
                        'description' => 'laravel/ui is deprecated in Laravel 11. Consider using Laravel Breeze.',
                        'files_affected' => ['composer.json'],
                        'action_required' => 'Replace laravel/ui with Laravel Breeze or similar',
                        'priority' => 'medium',
                        'difficulty' => 'medium',
                        'estimated_time' => '30 minutes'
                    ];
                }
                break;

            case 'phpunit/phpunit':
                if (version_compare($targetVersion, '10.0', '>=')) {
                    $changes[] = [
                        'type' => 'breaking',
                        'category' => 'testing',
                        'title' => 'PHPUnit Updates',
                        'description' => 'Laravel 10+ uses PHPUnit 10+ with new syntax.',
                        'files_affected' => ['phpunit.xml', 'tests/**/*.php'],
                        'action_required' => 'Update PHPUnit configuration and test syntax',
                        'priority' => 'medium',
                        'difficulty' => 'high',
                        'estimated_time' => '60 minutes'
                    ];
                }
                break;

            case 'laravel/tinker':
                if (version_compare($targetVersion, '11.0', '>=')) {
                    $changes[] = [
                        'type' => 'enhancement',
                        'category' => 'console',
                        'title' => 'Tinker Improvements',
                        'description' => 'Laravel 11 includes improved Tinker functionality.',
                        'files_affected' => ['config/tinker.php'],
                        'action_required' => 'Review Tinker configuration for new features',
                        'priority' => 'low',
                        'difficulty' => 'low',
                        'estimated_time' => '5 minutes'
                    ];
                }
                break;
        }

        return $changes;
    }

    /**
     * Get configuration changes
     */
    protected function getConfigurationChanges(string $currentVersion, string $targetVersion): array
    {
        $changes = [];

        if (version_compare($currentVersion, '10.0', '<') && version_compare($targetVersion, '11.0', '>=')) {
            $changes[] = [
                'type' => 'breaking',
                'category' => 'config',
                'title' => 'Configuration Structure',
                'description' => 'Laravel 11 has reorganized configuration structure.',
                'files_affected' => ['config/*.php', 'bootstrap/app.php'],
                'action_required' => 'Review and update configuration files for Laravel 11',
                'priority' => 'high',
                'difficulty' => 'medium',
                'estimated_time' => '45 minutes'
            ];
        }

        return $changes;
    }

    /**
     * Get middleware changes
     */
    protected function getMiddlewareChanges(string $currentVersion, string $targetVersion): array
    {
        $changes = [];

        if (version_compare($currentVersion, '8.0', '<') && version_compare($targetVersion, '9.0', '>=')) {
            $changes[] = [
                'type' => 'breaking',
                'category' => 'middleware',
                'title' => 'Middleware Registration',
                'description' => 'Laravel 9 changed how middleware is registered.',
                'files_affected' => ['app/Http/Kernel.php', 'bootstrap/app.php'],
                'action_required' => 'Update middleware registration to Laravel 9+ format',
                'priority' => 'medium',
                'difficulty' => 'medium',
                'estimated_time' => '20 minutes'
            ];
        }

        return $changes;
    }

    /**
     * Prioritize and sort recommendations
     */
    protected function prioritizeAndSortRecommendations(array $recommendations): array
    {
        // Define priority order
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        $typeOrder = ['breaking' => 3, 'deprecated' => 2, 'enhancement' => 1];

        usort($recommendations, function($a, $b) use ($priorityOrder, $typeOrder) {
            // First sort by priority
            $priorityA = $priorityOrder[$a['priority']] ?? 0;
            $priorityB = $priorityOrder[$b['priority']] ?? 0;

            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA;
            }

            // Then sort by type
            $typeA = $typeOrder[$a['type']] ?? 0;
            $typeB = $typeOrder[$b['type']] ?? 0;

            if ($typeA !== $typeB) {
                return $typeB <=> $typeA;
            }

            // Finally sort by estimated time (shorter first)
            return $a['estimated_time'] <=> $b['estimated_time'];
        });

        return $recommendations;
    }

    /**
     * Generate code snippets for specific changes
     */
    public function generateCodeSnippets(array $recommendations): array
    {
        $snippets = [];

        foreach ($recommendations as $recommendation) {
            $snippet = $this->generateSnippetForRecommendation($recommendation);
            if ($snippet) {
                $snippets[$recommendation['title']] = $snippet;
            }
        }

        return $snippets;
    }

    /**
     * Generate code snippet for a specific recommendation
     */
    protected function generateSnippetForRecommendation(array $recommendation): ?array
    {
        switch ($recommendation['title']) {
            case 'PHP 8.1+ Required':
                return [
                    'file' => 'composer.json',
                    'content' => '"php": "^8.1"',
                    'description' => 'Update PHP version requirement'
                ];

            case 'Application Structure Changes':
                return [
                    'file' => 'bootstrap/app.php',
                    'content' => $this->getLaravel11AppStructure(),
                    'description' => 'Laravel 11 application structure'
                ];

            case 'Middleware Registration':
                return [
                    'file' => 'bootstrap/app.php',
                    'content' => $this->getLaravel9MiddlewareStructure(),
                    'description' => 'Laravel 9+ middleware registration'
                ];

            case 'Permission Package Updates':
                return [
                    'file' => 'config/permission.php',
                    'content' => $this->getPermissionConfig(),
                    'description' => 'Updated permission configuration'
                ];

            default:
                return null;
        }
    }

    /**
     * Get Laravel 11 application structure
     */
    protected function getLaravel11AppStructure(): string
    {
        return <<<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
PHP;
    }

    /**
     * Get Laravel 9+ middleware structure
     */
    protected function getLaravel9MiddlewareStructure(): string
    {
        return <<<'PHP'
// In bootstrap/app.php or App\Http\Kernel
$middleware->alias([
    'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
    'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
    'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
    'can' => \Illuminate\Auth\Middleware\Authorize::class,
    'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
    'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
    'precognitive' => \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
    'signed' => \App\Http\Middleware\ValidateSignature::class,
    'subscribed' => \Spark\Http\Middleware\VerifyBillableIsSubscribed::class,
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
]);
PHP;
    }

    /**
     * Get updated permission configuration
     */
    protected function getPermissionConfig(): string
    {
        return <<<'PHP'
<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role' => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'categories' => 'categories',
        'category_tenant' => 'category_tenant',
        'permissions' => 'permissions',
        'permission_dependencies' => 'permission_dependencies',
        'permission_role_pivot' => 'permission_role_pivot',
        'permission_tenant_pivot' => 'permission_tenant_pivot',
        'roles' => 'roles',
        'role_tenant_pivot' => 'role_tenant_pivot',
        'tenants' => 'tenants',
        'tenant_user_pivot' => 'tenant_user_pivot',
        'users' => 'users',
        'user_permission_pivot' => 'user_permission_pivot',
        'user_role_pivot' => 'user_role_pivot',
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
    ],

    'teams' => false,

    'use_passport_client_credentials' => false,

    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
PHP;
    }

    /**
     * Get upgrade checklist
     */
    public function getUpgradeChecklist(string $currentVersion, string $targetVersion): array
    {
        $recommendations = $this->getCodeChangeRecommendations($currentVersion, $targetVersion);

        $checklist = [];

        foreach ($recommendations as $recommendation) {
            $checklist[] = [
                'title' => $recommendation['title'],
                'description' => $recommendation['description'],
                'priority' => $recommendation['priority'],
                'estimated_time' => $recommendation['estimated_time'],
                'files_affected' => $recommendation['files_affected'],
                'completed' => false
            ];
        }

        return $checklist;
    }

    /**
     * Generate migration guide for specific versions
     */
    public function generateMigrationGuide(string $currentVersion, string $targetVersion): array
    {
        return [
            'current_version' => $currentVersion,
            'target_version' => $targetVersion,
            'upgrade_steps' => $this->getUpgradeSteps($currentVersion, $targetVersion),
            'breaking_changes' => $this->getBreakingChanges($currentVersion, $targetVersion),
            'new_features' => $this->getNewFeatures($currentVersion, $targetVersion),
            'deprecations' => $this->getDeprecations($currentVersion, $targetVersion)
        ];
    }

    /**
     * Get upgrade steps
     */
    protected function getUpgradeSteps(string $currentVersion, string $targetVersion): array
    {
        $steps = [
            '1' => 'Backup your current application',
            '2' => 'Update composer.json dependencies',
            '3' => 'Review breaking changes documentation',
            '4' => 'Run composer update',
            '5' => 'Update configuration files',
            '6' => 'Run database migrations if needed',
            '7' => 'Test application functionality',
            '8' => 'Update frontend dependencies if using Laravel Mix/Vite'
        ];

        return $steps;
    }

    /**
     * Get breaking changes
     */
    protected function getBreakingChanges(string $currentVersion, string $targetVersion): array
    {
        $changes = [];

        if (version_compare($targetVersion, '11.0', '>=')) {
            $changes[] = 'New application structure with bootstrap/app.php';
            $changes[] = 'PHP 8.2+ requirement';
            $changes[] = 'New Artisan command signatures';
            $changes[] = 'Updated middleware registration';
        }

        return $changes;
    }

    /**
     * Get new features
     */
    protected function getNewFeatures(string $currentVersion, string $targetVersion): array
    {
        $features = [];

        if (version_compare($targetVersion, '11.0', '>=')) {
            $features[] = 'Improved application structure';
            $features[] = 'Enhanced routing capabilities';
            $features[] = 'Better developer experience';
            $features[] = 'Improved performance optimizations';
        }

        return $features;
    }

    /**
     * Get deprecations
     */
    protected function getDeprecations(string $currentVersion, string $targetVersion): array
    {
        $deprecations = [];

        if (version_compare($targetVersion, '11.0', '>=')) {
            $deprecations[] = 'laravel/ui package (use Laravel Breeze instead)';
            $deprecations[] = 'Some legacy middleware aliases';
            $deprecations[] = 'Old application structure';
        }

        return $deprecations;
    }
}
