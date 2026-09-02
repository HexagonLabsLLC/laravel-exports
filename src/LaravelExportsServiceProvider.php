<?php

namespace HexagonLabsLLC\LaravelExports;

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Services\SchemaSync;
use Illuminate\Support\ServiceProvider;

class LaravelExportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-exports.php', 'laravel-exports');

        $this->app->singleton(ModelRelationInspector::class);
        $this->app->singleton(SchemaSync::class);

        $this->app->singleton(DynamicExportService::class, function ($app) {
            return new DynamicExportService(
                $app->make(ModelRelationInspector::class),
                $app->make(SchemaSync::class)
            );
        });

        $this->app->singleton('laravel-exports', function ($app) {
            return $app->make(DynamicExportService::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-exports.php' => config_path('laravel-exports.php'),
        ], 'config');

        $this->publishesMigrations([
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
