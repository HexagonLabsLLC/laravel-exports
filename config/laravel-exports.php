<?php

return [
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

    /*
    |--------------------------------------------------------------------------
    | Schema Sync
    |--------------------------------------------------------------------------
    |
    | How the export catalog stays in sync with your Eloquent models when a
    | model is referenced at runtime:
    |   lazy   - sync a model's catalog rows on first reference (default)
    |   verify - also re-sync when the model's reflected schema has drifted
    |   manual - never sync at runtime; run export:import-models yourself
    |
    */

    'schema_sync' => env('EXPORT_SCHEMA_SYNC', 'lazy'),
];
