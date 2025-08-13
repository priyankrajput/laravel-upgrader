<?php

namespace priyank\LaravelUpgrader\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array getCurrentVersions()
 * @method static array checkForUpdates(array $currentVersions, array $allPackages)
 * @method static string|null getLatestVersionFromPackagist(string $package)
 * @method static array getAllStableVersionsFromPackagist(string $package)
 * @method static int versionCompare(string $version1, string $version2)
 * @method static void updateComposerConstraints(array $selectedTargets)
 * 
 * @see \priyank\LaravelUpgrader\Services\PackageVersionService
 */
class Upgrader extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-upgrader';
    }
}
