<?php

namespace priyankrajput\LaravelUpgrader\Providers;

use Illuminate\Support\ServiceProvider;
use priyankrajput\LaravelUpgrader\Services\PackageVersionService;
use priyankrajput\LaravelUpgrader\Commands\RunUpgradeCommand;

class UpgraderServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/upgrader.php', 'upgrader'
        );

        // Register the main service
        $this->app->singleton('laravel-upgrader', function ($app) {
            return new PackageVersionService();
        });

        // Register the command
        $this->commands([
            RunUpgradeCommand::class,
        ]);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../../config/upgrader.php' => config_path('upgrader.php'),
        ], 'upgrader-config');

        // Publish views
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/upgrader'),
        ], 'upgrader-views');

        // Publish assets
        $this->publishes([
            __DIR__.'/../../resources/assets' => public_path('vendor/upgrader'),
        ], 'upgrader-assets');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'upgrader');

        // Register the command
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunUpgradeCommand::class,
            ]);
        }
    }
}
