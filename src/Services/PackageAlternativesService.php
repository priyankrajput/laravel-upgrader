<?php

namespace priyankrajput\LaravelUpgrader\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PackageAlternativesService
{
    protected $cacheTime = 7200; // 2 hours

    /**
     * Get alternative packages for a given package
     */
    public function getAlternatives(string $packageName, string $targetLaravelVersion = null): array
    {
        $cacheKey = "alternatives_{$packageName}_" . ($targetLaravelVersion ?? 'any');

        return Cache::remember($cacheKey, $this->cacheTime, function() use ($packageName, $targetLaravelVersion) {
            $alternatives = $this->getPredefinedAlternatives($packageName);

            if (!empty($alternatives)) {
                return $this->enrichAlternativesWithData($alternatives, $targetLaravelVersion);
            }

            // If no predefined alternatives, try to find similar packages
            return $this->findSimilarPackages($packageName, $targetLaravelVersion);
        });
    }

    /**
     * Get predefined alternative packages mapping
     */
    protected function getPredefinedAlternatives(string $packageName): array
    {
        $alternatives = [
            // Authentication & Authorization
            'laravel/sanctum' => [
                'tymon/jwt-auth' => ['type' => 'alternative', 'reason' => 'More flexible token-based authentication'],
                'spatie/laravel-permission' => ['type' => 'complementary', 'reason' => 'Role and permission management'],
                'laravel/passport' => ['type' => 'alternative', 'reason' => 'OAuth2 server implementation']
            ],

            // Debugging & Development
            'barryvdh/laravel-debugbar' => [
                'laravel/telescope' => ['type' => 'advanced', 'reason' => 'More comprehensive debugging and monitoring'],
                'spatie/laravel-ray' => ['type' => 'modern', 'reason' => 'Modern debugging with better UX'],
                'clockwork' => ['type' => 'lightweight', 'reason' => 'Lightweight alternative to Debugbar']
            ],

            // Database & Excel
            'maatwebsite/excel' => [
                'rap2hpoutre/fast-excel' => ['type' => 'faster', 'reason' => 'Faster Excel processing with lower memory usage'],
                'box/spout' => ['type' => 'streaming', 'reason' => 'Memory-efficient streaming for large files'],
                'laravel-excel' => ['type' => 'community', 'reason' => 'Community-driven Excel package']
            ],

            // Image Processing
            'intervention/image' => [
                'spatie/image' => ['type' => 'modern', 'reason' => 'Modern PHP image manipulation'],
                'league/glide' => ['type' => 'server', 'reason' => 'Image server with on-the-fly processing'],
                'phpgd' => ['type' => 'native', 'reason' => 'Native PHP GD library wrapper']
            ],

            // API & HTTP
            'guzzlehttp/guzzle' => [
                'symfony/http-client' => ['type' => 'lightweight', 'reason' => 'Lightweight HTTP client'],
                'laravel/http' => ['type' => 'built-in', 'reason' => 'Laravel built-in HTTP client'],
                'buzz/laravel-buzz' => ['type' => 'alternative', 'reason' => 'Another HTTP client option']
            ],

            // Notifications
            'laravel-notification-channels/webpush' => [
                'minishlink/web-push' => ['type' => 'standalone', 'reason' => 'Standalone web push library'],
                'spatie/laravel-webhook' => ['type' => 'webhooks', 'reason' => 'Webhook delivery system']
            ],

            // SEO & Meta
            'artesaos/seotools' => [
                'spatie/laravel-seo' => ['type' => 'modern', 'reason' => 'Modern SEO package'],
                'butschster/meta-tags' => ['type' => 'alternative', 'reason' => 'Meta tags management']
            ],

            // Backup
            'spatie/laravel-backup' => [
                'backup-manager/laravel' => ['type' => 'alternative', 'reason' => 'Different backup strategy'],
                'laravel-backup-panel' => ['type' => 'panel', 'reason' => 'Backup management panel']
            ],

            // Media Management
            'spatie/laravel-medialibrary' => [
                'laravel-medialibrary-pro' => ['type' => 'pro', 'reason' => 'Professional media library'],
                'unisharp/laravel-filemanager' => ['type' => 'simple', 'reason' => 'Simple file manager']
            ],

            // Search
            'laravel/scout' => [
                'teamtnt/laravel-scout-tntsearch-driver' => ['type' => 'driver', 'reason' => 'TNTSearch driver for Scout'],
                'algolia/algoliasearch-client-php' => ['type' => 'algolia', 'reason' => 'Algolia search integration']
            ],

            // UI Components
            'laravel/ui' => [
                'laravel/breeze' => ['type' => 'recommended', 'reason' => 'Laravel official starter kit'],
                'laravel/jetstream' => ['type' => 'advanced', 'reason' => 'Full-stack starter kit with teams'],
                'filament/filament' => ['type' => 'admin', 'reason' => 'Admin panel with TALL stack']
            ],

            // Code Quality
            'phpstan/phpstan' => [
                'larastan/larastan' => ['type' => 'laravel', 'reason' => 'Laravel-specific PHPStan rules'],
                'rector/rector' => ['type' => 'refactoring', 'reason' => 'Automated refactoring tool']
            ],

            // Testing
            'phpunit/phpunit' => [
                'pestphp/pest' => ['type' => 'modern', 'reason' => 'Modern PHP testing framework'],
                'laravel/dusk' => ['type' => 'e2e', 'reason' => 'Browser testing for Laravel']
            ],

            // Deployment
            'deployer/deployer' => [
                'laravel/envoy' => ['type' => 'laravel', 'reason' => 'Laravel deployment tool'],
                'spatie/laravel-deploy' => ['type' => 'alternative', 'reason' => 'Laravel deployment package']
            ],

            // Monitoring
            'spatie/laravel-health' => [
                'laravel/pulse' => ['type' => 'built-in', 'reason' => 'Laravel built-in monitoring'],
                'sentry/sentry-laravel' => ['type' => 'error_tracking', 'reason' => 'Error tracking and monitoring']
            ],

            // API Documentation
            'darkaonline/l5-swagger' => [
                'dedoc/scramble' => ['type' => 'modern', 'reason' => 'Modern API documentation'],
                'laravel/scout' => ['type' => 'search', 'reason' => 'API search functionality']
            ],

            // Payment Processing
            'stripe/stripe-php' => [
                'laravel/cashier' => ['type' => 'laravel', 'reason' => 'Laravel Stripe integration'],
                'paypal/paypal-checkout-sdk' => ['type' => 'paypal', 'reason' => 'PayPal payment processing']
            ],

            // Social Authentication
            'laravel/socialite' => [
                'socialiteproviders' => ['type' => 'extended', 'reason' => 'Extended social providers'],
                'stevebauman/socialite-auth' => ['type' => 'alternative', 'reason' => 'Alternative social auth']
            ],

            // Queue & Jobs
            'laravel/horizon' => [
                'laravel/telescope' => ['type' => 'monitoring', 'reason' => 'Queue monitoring with Telescope'],
                'spatie/laravel-queue' => ['type' => 'alternative', 'reason' => 'Queue management package']
            ],

            // Localization
            'spatie/laravel-translatable' => [
                'astrotomic/laravel-translatable' => ['type' => 'alternative', 'reason' => 'Alternative translation package'],
                'joe/laravel-translator' => ['type' => 'translation', 'reason' => 'Translation management']
            ],

            // Caching
            'spatie/laravel-responsecache' => [
                'laravel/cache' => ['type' => 'built-in', 'reason' => 'Laravel built-in caching'],
                'barryvdh/laravel-httpcache' => ['type' => 'http', 'reason' => 'HTTP response caching']
            ],

            // Rate Limiting
            'graham-campbell/throttle' => [
                'laravel/throttle' => ['type' => 'built-in', 'reason' => 'Laravel built-in rate limiting'],
                'spatie/laravel-rate-limited-job-middleware' => ['type' => 'jobs', 'reason' => 'Rate limiting for jobs']
            ],

            // Markdown
            'spatie/laravel-markdown' => [
                'league/commonmark' => ['type' => 'standalone', 'reason' => 'Standalone markdown parser'],
                'parsedown/parsedown' => ['type' => 'simple', 'reason' => 'Simple markdown parser']
            ],

            // PDF Generation
            'barryvdh/laravel-dompdf' => [
                'niklasravnsborg/laravel-pdf' => ['type' => 'alternative', 'reason' => 'Alternative PDF generator'],
                'tcpdf/tcpdf' => ['type' => 'standalone', 'reason' => 'Standalone PDF library']
            ],

            // Charts & Graphs
            'consoletvs/charts' => [
                'laravel-charts' => ['type' => 'modern', 'reason' => 'Modern chart library'],
                'chartjs' => ['type' => 'javascript', 'reason' => 'JavaScript charting library']
            ],

            // Form Builders
            'laravelcollective/html' => [
                'spatie/laravel-html' => ['type' => 'modern', 'reason' => 'Modern HTML forms'],
                'kalnoy/nestedset' => ['type' => 'nested', 'reason' => 'Nested set functionality']
            ],

            // Activity Logging
            'spatie/laravel-activitylog' => [
                'owen-it/laravel-auditing' => ['type' => 'alternative', 'reason' => 'Alternative audit logging'],
                'laravel-activity-log' => ['type' => 'simple', 'reason' => 'Simple activity logging']
            ],

            // Tag Management
            'spatie/laravel-tags' => [
                'rtconner/laravel-tagging' => ['type' => 'alternative', 'reason' => 'Alternative tagging system'],
                'cviebrock/eloquent-taggable' => ['type' => 'eloquent', 'reason' => 'Eloquent taggable models']
            ],

            // Sitemap Generation
            'spatie/laravel-sitemap' => [
                'laravel/sitemap' => ['type' => 'built-in', 'reason' => 'Laravel sitemap generator'],
                'roumen/sitemap' => ['type' => 'alternative', 'reason' => 'Alternative sitemap package']
            ],

            // RSS Feeds
            'spatie/laravel-feed' => [
                'willvincent/feeds' => ['type' => 'alternative', 'reason' => 'Alternative RSS feed package'],
                'laravel-feed' => ['type' => 'simple', 'reason' => 'Simple RSS feed generator']
            ],

            // Cookie Consent
            'spatie/laravel-cookie-consent' => [
                'orestbida/cookieconsent' => ['type' => 'javascript', 'reason' => 'JavaScript cookie consent'],
                'laravel-cookie-consent' => ['type' => 'simple', 'reason' => 'Simple cookie consent']
            ],

            // Newsletter
            'spatie/laravel-newsletter' => [
                'laravel-mailchimp' => ['type' => 'mailchimp', 'reason' => 'Mailchimp integration'],
                'mailchimp/mailchimp' => ['type' => 'standalone', 'reason' => 'Standalone Mailchimp']
            ],

            // Shopping Cart
            'hardevine/shoppingcart' => [
                'laravel-shopping-cart' => ['type' => 'alternative', 'reason' => 'Alternative shopping cart'],
                'darryldecode/cart' => ['type' => 'modern', 'reason' => 'Modern shopping cart']
            ],

            // Image Optimization
            'spatie/laravel-image-optimizer' => [
                'image-optimizer' => ['type' => 'standalone', 'reason' => 'Standalone image optimizer'],
                'laravel-image-optimizer' => ['type' => 'simple', 'reason' => 'Simple image optimization']
            ],

            // QR Codes
            'simplesoftwareio/simple-qrcode' => [
                'bacon/bacon-qr-code' => ['type' => 'standalone', 'reason' => 'Standalone QR code generator'],
                'laravel-qr-code' => ['type' => 'laravel', 'reason' => 'Laravel QR code package']
            ],

            // UUID
            'ramsey/uuid' => [
                'symfony/uid' => ['type' => 'symfony', 'reason' => 'Symfony UID component'],
                'laravel-uuid' => ['type' => 'laravel', 'reason' => 'Laravel UUID helper']
            ],

            // Environment Configuration
            'vlucas/phpdotenv' => [
                'symfony/dotenv' => ['type' => 'symfony', 'reason' => 'Symfony dotenv component'],
                'laravel-env' => ['type' => 'laravel', 'reason' => 'Laravel environment helper']
            ],

            // File Management
            'league/flysystem' => [
                'laravel-flysystem' => ['type' => 'laravel', 'reason' => 'Laravel filesystem abstraction'],
                'spatie/flysystem-dropbox' => ['type' => 'dropbox', 'reason' => 'Dropbox filesystem adapter']
            ],

            // Email Verification
            'laravel-email-verification' => [
                'propaganistas/laravel-phone' => ['type' => 'phone', 'reason' => 'Phone number verification'],
                'laravel-2fa' => ['type' => '2fa', 'reason' => 'Two-factor authentication']
            ],

            // API Rate Limiting
            'laravel-api-rate-limiting' => [
                'spatie/laravel-rate-limited-job-middleware' => ['type' => 'jobs', 'reason' => 'Rate limiting for jobs'],
                'laravel-throttle' => ['type' => 'simple', 'reason' => 'Simple rate limiting']
            ],

            // Database Backup
            'spatie/laravel-db-snapshots' => [
                'laravel-backup' => ['type' => 'backup', 'reason' => 'Database backup solution'],
                'mysqldump-php' => ['type' => 'standalone', 'reason' => 'Standalone mysqldump']
            ],

            // Analytics
            'spatie/laravel-analytics' => [
                'laravel-google-analytics' => ['type' => 'google', 'reason' => 'Google Analytics integration'],
                'analytics-php' => ['type' => 'standalone', 'reason' => 'Standalone analytics']
            ],

            // Mobile Detection
            'mobiledetect/mobiledetectlib' => [
                'jenssegers/agent' => ['type' => 'laravel', 'reason' => 'Laravel mobile detection'],
                'laravel-mobile-detect' => ['type' => 'simple', 'reason' => 'Simple mobile detection']
            ],

            // Image Lazy Loading
            'spatie/laravel-lazy-loading' => [
                'laravel-lazy-load' => ['type' => 'alternative', 'reason' => 'Alternative lazy loading'],
                'eloquent-lazy-loading' => ['type' => 'eloquent', 'reason' => 'Eloquent lazy loading']
            ],

            // Code Generation
            'laravel-generator' => [
                'infyom/laravel-generator' => ['type' => 'infyom', 'reason' => 'InfyOm Laravel generator'],
                'crud-generator' => ['type' => 'crud', 'reason' => 'CRUD code generator']
            ],

            // API Testing
            'laravel-api-tester' => [
                'pestphp/pest' => ['type' => 'testing', 'reason' => 'Modern PHP testing'],
                'phpunit-api-test' => ['type' => 'phpunit', 'reason' => 'PHPUnit API testing']
            ],

            // Real-time Features
            'pusher/pusher-php-server' => [
                'laravel-websockets' => ['type' => 'websockets', 'reason' => 'Laravel WebSockets'],
                'laravel-broadcasting' => ['type' => 'broadcasting', 'reason' => 'Laravel event broadcasting']
            ],

            // Slug Generation
            'spatie/laravel-sluggable' => [
                'cviebrock/eloquent-sluggable' => ['type' => 'eloquent', 'reason' => 'Eloquent sluggable models'],
                'laravel-sluggable' => ['type' => 'simple', 'reason' => 'Simple slug generation']
            ],

            // Soft Deletes
            'spatie/laravel-soft-deletes' => [
                'laravel-soft-deletes' => ['type' => 'built-in', 'reason' => 'Laravel built-in soft deletes'],
                'eloquent-soft-deletes' => ['type' => 'extended', 'reason' => 'Extended soft delete features']
            ],

            // Database Seeding
            'laravel-seed' => [
                'laravel-factories' => ['type' => 'factories', 'reason' => 'Laravel model factories'],
                'database-seed' => ['type' => 'seed', 'reason' => 'Database seeding helper']
            ],

            // Migration Helpers
            'laravel-migrations' => [
                'laravel-migration-generator' => ['type' => 'generator', 'reason' => 'Migration code generator'],
                'doctrine/dbal' => ['type' => 'doctrine', 'reason' => 'Doctrine DBAL for complex migrations']
            ]
        ];

        return $alternatives[$packageName] ?? [];
    }

    /**
     * Enrich alternatives with additional data
     */
    protected function enrichAlternativesWithData(array $alternatives, string $targetLaravelVersion = null): array
    {
        $enriched = [];

        foreach ($alternatives as $package => $info) {
            $packageData = $this->getPackageData($package);

            $enriched[$package] = array_merge($info, [
                'name' => $package,
                'description' => $packageData['description'] ?? 'No description available',
                'downloads' => $packageData['downloads'] ?? 0,
                'github_stars' => $packageData['github_stars'] ?? 0,
                'last_updated' => $packageData['last_updated'] ?? null,
                'laravel_compatibility' => $this->checkLaravelCompatibility($package, $targetLaravelVersion),
                'maintenance_status' => $this->checkMaintenanceStatus($package)
            ]);
        }

        return $enriched;
    }

    /**
     * Find similar packages based on keywords
     */
    protected function findSimilarPackages(string $packageName, string $targetLaravelVersion = null): array
    {
        // Extract keywords from package name
        $keywords = $this->extractKeywords($packageName);

        $similarPackages = [];

        // This would typically query Packagist API or use a predefined database
        // For now, return empty array as this would require external API calls
        return $similarPackages;
    }

    /**
     * Extract keywords from package name
     */
    protected function extractKeywords(string $packageName): array
    {
        $parts = explode('/', $packageName);
        $packageSlug = end($parts);

        // Convert camelCase and kebab-case to keywords
        $keywords = preg_split('/[-_]/', $packageSlug);

        // Remove Laravel prefix if present
        $keywords = array_filter($keywords, function($keyword) {
            return strtolower($keyword) !== 'laravel';
        });

        return array_map('strtolower', $keywords);
    }

    /**
     * Get package data from Packagist
     */
    protected function getPackageData(string $packageName): array
    {
        try {
            $response = Http::timeout(5)->get("https://repo.packagist.org/p2/{$packageName}.json");

            if ($response->successful()) {
                $data = $response->json();
                $packageInfo = $data['packages'][$packageName][0] ?? [];

                return [
                    'description' => $packageInfo['description'] ?? '',
                    'downloads' => $data['packages'][$packageName] ?
                        array_sum(array_column($data['packages'][$packageName], 'downloads')) : 0,
                    'github_stars' => 0, // Would need GitHub API call
                    'last_updated' => $packageInfo['time'] ?? null
                ];
            }
        } catch (\Exception $e) {
            // Return empty data on error
        }

        return [];
    }

    /**
     * Check Laravel compatibility for alternative package
     */
    protected function checkLaravelCompatibility(string $packageName, string $targetLaravelVersion = null): array
    {
        $laravelCompatibility = app(LaravelCompatibilityService::class);
        return $laravelCompatibility->getPackageLaravelCompatibility($packageName);
    }

    /**
     * Check maintenance status of a package
     */
    protected function checkMaintenanceStatus(string $packageName): string
    {
        try {
            $response = Http::timeout(5)->get("https://repo.packagist.org/p2/{$packageName}.json");

            if ($response->successful()) {
                $data = $response->json();
                $latestVersion = $data['packages'][$packageName][0] ?? [];

                if ($latestVersion) {
                    $lastUpdated = strtotime($latestVersion['time'] ?? 'now');
                    $sixMonthsAgo = strtotime('-6 months');

                    if ($lastUpdated < $sixMonthsAgo) {
                        return 'stale';
                    }

                    return 'active';
                }
            }
        } catch (\Exception $e) {
            return 'unknown';
        }

        return 'unknown';
    }

    /**
     * Get top alternatives based on popularity and compatibility
     */
    public function getTopAlternatives(string $packageName, string $targetLaravelVersion = null, int $limit = 3): array
    {
        $alternatives = $this->getAlternatives($packageName, $targetLaravelVersion);

        if (empty($alternatives)) {
            return [];
        }

        // Sort by downloads and compatibility
        uasort($alternatives, function($a, $b) {
            $scoreA = ($a['downloads'] ?? 0) + ($a['laravel_compatibility']['compatible'] ? 1000 : 0);
            $scoreB = ($b['downloads'] ?? 0) + ($b['laravel_compatibility']['compatible'] ? 1000 : 0);

            return $scoreB <=> $scoreA;
        });

        return array_slice($alternatives, 0, $limit, true);
    }
}
