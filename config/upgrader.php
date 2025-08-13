<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Customize the route prefix and middleware for the upgrader.
    |
    */
    'route' => [
        'prefix' => 'admin/upgrade',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the cache duration for package version checks.
    |
    */
    'cache' => [
        'enabled' => true,
        'duration' => 1440, // minutes (24 hours)
        'key' => 'package_updates',
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Sources
    |--------------------------------------------------------------------------
    |
    | Configure the sources for package information.
    |
    */
    'sources' => [
        'packagist' => [
            'api_url' => 'https://repo.packagist.org/p2',
            'timeout' => 10, // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for upgrade operations.
    |
    */
    'logging' => [
        'enabled' => true,
        'path' => storage_path('logs/upgrade.log'),
    ],
];
