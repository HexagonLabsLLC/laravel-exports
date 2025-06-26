<?php

namespace HexagonLabsLLC\LaravelExports;

use Illuminate\Support\ServiceProvider;

class LaravelExportsServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-exports.php', 'laravel-exports');

        // Register the main service
        $this->app->singleton('laravel-exports', function ($app) {
            return new Services\DynamicExportService;
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/laravel-exports.php' => config_path('laravel-exports.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\ImportModelsCommand::class,
                Console\Commands\SeedTransformationFunctionsCommand::class,
            ]);
        }
    }
}
