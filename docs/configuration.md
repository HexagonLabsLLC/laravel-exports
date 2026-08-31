# Configuration

All configuration options are in `config/laravel-exports.php`.

## Publishing the Configuration

```bash
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider" --tag="config"
```

## Queue Configuration

Configure background export processing:

```php
// Queue name for background exports
'queue' => env('EXPORT_QUEUE', 'exports'),
```

### Environment Variable

```env
EXPORT_QUEUE=exports
```

### Queue Worker

Run a dedicated worker for exports:

```bash
php artisan queue:work --queue=exports
```

Or include it with other queues:

```bash
php artisan queue:work --queue=high,default,exports
```

## Storage Configuration

Configure where exported files are stored for background jobs:

```php
// Storage disk for background exports
'disk' => env('EXPORT_DISK', 'local'),

// Storage path prefix for exports
'path' => env('EXPORT_PATH', 'exports'),
```

### Environment Variables

```env
EXPORT_DISK=local
EXPORT_PATH=exports
```

### Using S3 or Other Disks

To store exports on S3:

```env
EXPORT_DISK=s3
EXPORT_PATH=exports
```

Ensure your S3 disk is configured in `config/filesystems.php`:

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
],
```

## Status Tracking

Configure how long export status information is retained in cache:

```php
// Status cache TTL in seconds (default: 24 hours)
'status_ttl' => env('EXPORT_STATUS_TTL', 86400),
```

### Environment Variable

```env
EXPORT_STATUS_TTL=86400
```

The status includes:
- Export progress percentage
- Completion status
- File path and download URL
- Error messages (if failed)
- Row count

### Cache Driver

Export status uses Laravel's cache. Ensure you have a cache driver configured that persists across requests:

```env
CACHE_DRIVER=redis
```

Avoid `array` or `file` drivers in production with multiple workers.

## Processing Configuration

Configure default processing options:

```php
// Default chunk size for large exports
'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
```

### Environment Variable

```env
EXPORT_CHUNK_SIZE=1000
```

### Choosing a Chunk Size

| Dataset Size | Recommended Chunk Size |
|-------------|------------------------|
| < 10,000 rows | 1000 (default) |
| 10,000 - 100,000 rows | 500 - 1000 |
| 100,000 - 1,000,000 rows | 200 - 500 |
| > 1,000,000 rows | 100 - 200 |

Smaller chunks use less memory but increase processing time. Larger chunks are faster but use more memory.

## Job Configuration

Configure default settings for the `ProcessExportJob` used by background exports:

```php
// Number of times the job may be attempted before failing
'job_tries' => env('EXPORT_JOB_TRIES', 3),

// Number of seconds the job may run before timing out
'job_timeout' => env('EXPORT_JOB_TIMEOUT', 3600),
```

### Environment Variables

```env
EXPORT_JOB_TRIES=3
EXPORT_JOB_TIMEOUT=3600
```

Raise `job_timeout` for very large exports that legitimately need to run longer than an hour, and tune `job_tries` against your queue's backoff strategy. Keep `job_tries` low (1-3) for non-idempotent exports so a transient failure doesn't produce duplicate output files.

## Value Extraction

Configure the attributes the package falls back to when it needs to extract a
scalar from an Eloquent Model without an explicit `value_path`. This applies in
two places:

- The final-value fallback when a column's `value_path` resolves to a Model
  instance.
- Collection-filter comparisons, where the value being compared against may be
  a related Model and needs to be reduced to a comparable scalar.

```php
// Ordered list of attributes to check on a related Model
'fallback_attributes' => ['name', 'title', 'value', 'label'],
```

The first attribute in the list that exists on the Model wins. Override the
list to match your own domain conventions:

```php
'fallback_attributes' => ['display_name', 'label', 'code', 'name'],
```

## Complete Configuration File

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => env('EXPORT_QUEUE', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */

    'disk' => env('EXPORT_DISK', 'local'),
    'path' => env('EXPORT_PATH', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Status Tracking
    |--------------------------------------------------------------------------
    */

    'status_ttl' => env('EXPORT_STATUS_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Processing Configuration
    |--------------------------------------------------------------------------
    */

    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Job Configuration
    |--------------------------------------------------------------------------
    */

    'job_tries' => env('EXPORT_JOB_TRIES', 3),
    'job_timeout' => env('EXPORT_JOB_TIMEOUT', 3600),

    /*
    |--------------------------------------------------------------------------
    | Value Extraction Configuration
    |--------------------------------------------------------------------------
    */

    'fallback_attributes' => ['name', 'title', 'value', 'label'],
];
```

## Environment Variables Summary

```env
# Queue name for background exports
EXPORT_QUEUE=exports

# Storage disk for completed exports
EXPORT_DISK=local

# Path prefix within the disk
EXPORT_PATH=exports

# Status cache TTL in seconds (24 hours)
EXPORT_STATUS_TTL=86400

# Default chunk size for processing
EXPORT_CHUNK_SIZE=1000

# Background job attempt count before failure
EXPORT_JOB_TRIES=3

# Background job timeout in seconds
EXPORT_JOB_TIMEOUT=3600
```

## Related Documentation

- [Large Datasets](guides/large-datasets.md) - Chunking and background job configuration
- [API Reference](reference/api.md) - DynamicExportService methods
