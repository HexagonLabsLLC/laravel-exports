# Configuration

All configuration options are in `config/laravel-exports.php`.

## Publishing the Configuration

```bash
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider" --tag="config"
```

## Model Configuration

Override the default model classes to add custom behavior:

```php
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
```

### Custom Model Example

Create a custom model that extends the package model:

```php
<?php

namespace App\Models;

use HexagonLabsLLC\LaravelExports\Models\ExportLayout as BaseExportLayout;

class ExportLayout extends BaseExportLayout
{
    // Add custom scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Add custom relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

Update the configuration:

```php
'export_layouts' => [
    'model' => \App\Models\ExportLayout::class,
    'table' => 'export_layouts',
],
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

## Complete Configuration File

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
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
```

## Related Documentation

- [Large Datasets](guides/large-datasets.md) - Chunking and background job configuration
- [API Reference](reference/api.md) - DynamicExportService methods
