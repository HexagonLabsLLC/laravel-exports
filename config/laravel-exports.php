<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | These settings define the model classes and table names used by the
    | export system. You can override these to use custom models.
    |
    */

    'export_models' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportModel::class,
        'table' => 'export_models',
    ],

    'export_model_relations' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportModelRelation::class,
        'table' => 'export_model_relations',
    ],

    'export_layouts' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportLayout::class,
        'table' => 'export_layouts',
    ],

    'export_filters' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportFilter::class,
        'table' => 'export_filters',
    ],

    'export_sorts' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportSort::class,
        'table' => 'export_sorts',
    ],

    'export_functions' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportFunction::class,
        'table' => 'export_functions',
    ],

    'export_columns' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportColumn::class,
        'table' => 'export_columns',
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue settings for background export processing.
    |
    */

    // Queue name for background exports
    'queue' => env('EXPORT_QUEUE', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configure where exported files are stored when using background jobs.
    |
    */

    // Storage disk for background exports
    'disk' => env('EXPORT_DISK', 'local'),

    // Storage path prefix for exports
    'path' => env('EXPORT_PATH', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Status Tracking
    |--------------------------------------------------------------------------
    |
    | Configure how long export status information is retained in cache.
    |
    */

    // Status cache TTL in seconds (default: 24 hours)
    'status_ttl' => env('EXPORT_STATUS_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default processing options for exports.
    |
    */

    // Default chunk size for large exports
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Job Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default job settings for background export processing.
    |
    */

    // Number of times the job may be attempted
    'job_tries' => env('EXPORT_JOB_TRIES', 3),

    // Number of seconds the job can run before timing out
    'job_timeout' => env('EXPORT_JOB_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Value Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default behavior for extracting values from related models.
    |
    */

    // Fallback attributes to check when extracting values from related objects
    // without a specific value_path
    'fallback_attributes' => ['name', 'title', 'value', 'label'],
];
