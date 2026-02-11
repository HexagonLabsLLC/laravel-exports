<?php

namespace HexagonLabsLLC\LaravelExports;

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
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

        // Register ModelRelationInspector as singleton
        $this->app->singleton(ModelRelationInspector::class);

        // Register DynamicExportService with proper dependency injection
        $this->app->singleton(DynamicExportService::class, function ($app) {
            return new DynamicExportService(
                $app->make(ModelRelationInspector::class)
            );
        });

        // Register facade alias
        $this->app->singleton('laravel-exports', function ($app) {
            return $app->make(DynamicExportService::class);
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
