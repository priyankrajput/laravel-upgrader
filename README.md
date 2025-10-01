# Laravel Upgrader Package

A beautiful UI for managing Laravel package upgrades with automatic backups, changelog viewing, and real-time progress monitoring.

## Features

- 🎨 Modern, responsive UI (Tailwind CSS)
- 📦 View all package updates in one place
- 🔄 Real-time upgrade progress monitoring
- 💾 Automatic backups before upgrades
- 📝 View changelogs from GitHub releases
- ⚡ Background composer updates
- 🔙 Restore from backups if needed
- 🎯 Select specific packages to update
- 🔍 Major version upgrade warnings

## Installation

### 1. Install via Composer

```bash
composer require priyankrajput/laravel-upgrader
```

### 2. Publish Configuration (Optional)

```bash
php artisan vendor:publish --tag=upgrader-config
```

This creates `config/upgrader.php` where you can customize:
- Route prefix (default: `admin/upgrade`)
- Middleware
- Cache duration
- Composer path (for Windows or custom installations)

### 3. Publish Views (Optional - for theme customization)

```bash
php artisan vendor:publish --tag=upgrader-views
```

This publishes views to `resources/views/vendor/upgrader/` where you can:
- Customize colors and styling
- Modify layout and structure
- Add your own branding
- Change UI components

### 4. Configure Composer Path (Windows Users)

If you're on Windows or Composer isn't in your PATH, add to `.env`:

```env
COMPOSER_PATH="C:\ProgramData\ComposerSetup\bin\composer.bat"
```

Or for local composer.phar:

```env
COMPOSER_PATH="php C:\path\to\your\project\composer.phar"
```

## Usage

### Access the Upgrader UI

Navigate to: `http://your-app.test/admin/upgrade`

(The prefix can be changed in `config/upgrader.php`)

### Protect with Authentication

Add middleware in `config/upgrader.php`:

```php
'route' => [
    'prefix' => 'admin/upgrade',
    'middleware' => ['web', 'auth', 'admin'], // Add your middleware
],
```

### Workflow

1. **Check for Updates** - Click "Check for Updates" to scan packages
2. **Review Changes** - View available versions and changelogs
3. **Select Packages** - Choose which packages to update
4. **Confirm Backup** - Automatic backup is created before upgrade
5. **Monitor Progress** - Watch real-time composer output
6. **Restore if Needed** - Access backups via "Backups & Restore"

## Customizing the Theme

After publishing views, edit files in `resources/views/vendor/upgrader/`:

### Main View: `index.blade.php`

```blade
{{-- Change colors --}}
<div class="bg-gradient-to-r from-blue-600 to-blue-700">
    {{-- Change to your brand colors --}}
    <div class="bg-gradient-to-r from-purple-600 to-pink-700">
```

```blade
{{-- Modify header --}}
<h1 class="text-xl font-semibold text-white">
    <i class="fas fa-cubes"></i>
    Laravel Package Upgrader {{-- Add your app name --}}
</h1>
```

```blade
{{-- Add custom CSS --}}
<style>
    .custom-theme {
        /* Your custom styles */
    }
</style>
```

### Backup View: `backups.blade.php`

Customize the backup listing and restore interface.

## Configuration Options

### `config/upgrader.php`

```php
return [
    // Route configuration
    'route' => [
        'prefix' => 'admin/upgrade',      // URL prefix
        'middleware' => ['web', 'auth'],  // Middleware stack
    ],

    // Cache settings
    'cache' => [
        'enabled' => true,
        'duration' => 1440,  // 24 hours in minutes
        'key' => 'package_updates',
    ],

    // Packagist API
    'sources' => [
        'packagist' => [
            'api_url' => 'https://repo.packagist.org/p2',
            'timeout' => 10,
        ],
    ],

    // Logging
    'logging' => [
        'enabled' => true,
        'path' => storage_path('logs/upgrade.log'),
    ],

    // Composer executable path
    'composer_path' => env('COMPOSER_PATH', 'composer'),
];
```

## Troubleshooting

### "Resource temporarily unavailable" Error

**Windows users**: Set the full path to composer in `.env`:

```env
COMPOSER_PATH="C:\ProgramData\ComposerSetup\bin\composer.bat"
```

### Upgrade Doesn't Start

1. Check `storage/logs/laravel.log` for errors
2. Ensure `storage/logs/` is writable
3. Verify Composer is accessible from web server user
4. Check that `proc_open` is not disabled in `php.ini`

### UI Shows "Failed to Start"

1. Open browser DevTools → Network tab
2. Check the POST request to `/admin/upgrade/run`
3. Look for 419 (CSRF), 403 (auth), or 500 (server error)
4. Check response body for error details

### No Log Output

1. Check `storage/logs/upgrade-*.log` files exist
2. Verify `storage/logs/upgrade.current` points to correct file
3. Ensure web server can execute background processes
4. Try running `composer update` manually from project root

## Advanced Usage

### Programmatic Access

```php
use priyankrajput\LaravelUpgrader\Services\PackageVersionService;

$service = app(PackageVersionService::class);

// Get current versions
$versions = $service->getCurrentVersions();

// Check for updates
$updates = $service->checkForUpdates($versions, $allPackages);

// Update constraints
$service->updateComposerConstraints(['laravel/framework' => '10.0']);
```

### Custom Backup Location

Modify `BackupService` or extend it to change backup storage location.

### Add Custom Checks

Extend `UpgradeController` to add pre-upgrade validation:

```php
protected function validateUpgrade($packages)
{
    // Your custom validation
    if ($this->hasBreakingChanges($packages)) {
        throw new \Exception('Breaking changes detected!');
    }
}
```

## Security

- Always backup before upgrading
- Use authentication middleware in production
- Test upgrades in staging environment first
- Review changelogs for breaking changes
- Keep backups for at least 30 days

## Requirements

- PHP 8.0.2+
- Laravel 9.x, 10.x, 11.x, or 12.x
- Composer installed and accessible
- `proc_open` enabled in PHP
- Writable `storage/logs/` directory

## License

MIT License

## Support

For issues and feature requests, please use the GitHub repository.

## Credits

Created by Priyank Rajput
