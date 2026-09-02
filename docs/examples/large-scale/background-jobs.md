# Background Jobs

Queue large exports for asynchronous processing with status tracking.

## Scenario

Export millions of records without blocking the user, with progress updates.

## Basic Usage

### Queue an Export

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();

// Queue the export
$exportId = $service->queueExport(
    $layout,
    'csv',
    $requestData
);

// Return ID to client
return response()->json([
    'export_id' => $exportId,
    'message' => 'Export started',
]);
```

Any format registered with `ExportFactory` can be queued. `csv` and `json` are written chunk by chunk to a temp file and then streamed to the disk, so memory stays flat regardless of row count; queued `json` is always a plain array of row objects.

### Queue a Handler-Backed Format

```php
// One sheet per region; xlsx options are passed through to the handler
$exportId = $service->queueExport(
    $layout,
    'xlsx',
    $requestData,
    ['sheet_by' => 'Region']
);
```

xlsx and custom handlers buffer the full result set in memory and call the handler's `export()`, so csv stays the recommendation for very large exports; xlsx is fine for report-sized ones. The stored file's extension comes from the handler's `getExtension()`, so a custom `excel` format whose handler returns `xlsx` writes a `.xlsx` file.

An unregistered format throws an `InvalidArgumentException` when the job runs ("Unsupported export format: pdf. Supported formats: csv, json, xlsx"), and `xlsx` without `phpoffice/phpspreadsheet` throws a `RuntimeException` with install instructions. Either way the export ends up `failed` with the message in `error`.

### Check Status

```php
use HexagonLabsLLC\LaravelExports\Jobs\ProcessExportJob;

$status = ProcessExportJob::getStatus($exportId);

// Returns (while processing):
// [
//     'status' => 'processing',      // processing, completed, failed
//     'progress' => 45,              // Percentage
//     'processed_rows' => 45000,     // Rows written so far
//     'total_rows' => 100000,        // Rows the export will produce
//     'export_id' => '...',          // Always present
//     'layout_id' => '...',          // Always present
//     'format' => 'csv',             // Always present
//     'updated_at' => '...',         // Always present
// ]

// On completion the payload also carries:
// 'row_count', 'path', 'disk', 'url', 'filename', 'completed_at'
// A zero-row export completes with 'row_count' => 0 and
// 'message' => 'No records to export', and writes no file.
// On failure: 'error' and 'failed_at'
```

### Check Completion

```php
if (ProcessExportJob::isComplete($exportId)) {
    if (ProcessExportJob::isSuccessful($exportId)) {
        $url = ProcessExportJob::getDownloadUrl($exportId);
        $path = ProcessExportJob::getFilePath($exportId);
    } else {
        $error = ProcessExportJob::getStatus($exportId)['error'];
    }
}
```

## Complete Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Jobs\ProcessExportJob;

class BackgroundExportController extends Controller
{
    public function start(Request $request, string $layoutId)
    {
        $validated = $request->validate([
            'format' => 'in:csv,json,xlsx',
            'filters' => 'array',
        ]);

        $layout = ExportLayout::findOrFail($layoutId);
        $service = new DynamicExportService();

        // Get estimated count
        $count = $service->getExportCount($layout, $validated['filters'] ?? []);

        // Queue the export
        $exportId = $service->queueExport(
            $layout,
            $validated['format'] ?? 'csv',
            $validated['filters'] ?? []
        );

        return response()->json([
            'export_id' => $exportId,
            'estimated_rows' => $count,
            'status' => 'queued',
        ]);
    }

    public function status(string $exportId)
    {
        $status = ProcessExportJob::getStatus($exportId);

        if (!$status) {
            return response()->json([
                'error' => 'Export not found or expired',
            ], 404);
        }

        return response()->json($status);
    }

    public function download(string $exportId)
    {
        if (!ProcessExportJob::isSuccessful($exportId)) {
            return response()->json([
                'error' => 'Export not ready or failed',
            ], 400);
        }

        $path = ProcessExportJob::getFilePath($exportId);
        $disk = config('laravel-exports.disk', 'local');

        return Storage::disk($disk)->download($path);
    }

    public function cancel(string $exportId)
    {
        // Note: Cancellation requires custom implementation
        // This is a placeholder showing the concept

        return response()->json([
            'message' => 'Export cancellation requested',
        ]);
    }
}
```

## Routes

```php
// routes/api.php
Route::prefix('exports')->group(function () {
    Route::post('{layoutId}/start', [BackgroundExportController::class, 'start']);
    Route::get('{exportId}/status', [BackgroundExportController::class, 'status']);
    Route::get('{exportId}/download', [BackgroundExportController::class, 'download']);
});
```

## Frontend Integration

```javascript
class ExportManager {
    constructor(apiBase) {
        this.apiBase = apiBase;
    }

    async startExport(layoutId, format, filters) {
        const response = await fetch(`${this.apiBase}/exports/${layoutId}/start`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ format, filters }),
        });
        return response.json();
    }

    async pollStatus(exportId, onProgress, intervalMs = 2000) {
        return new Promise((resolve, reject) => {
            const poll = async () => {
                try {
                    const response = await fetch(`${this.apiBase}/exports/${exportId}/status`);
                    const status = await response.json();

                    onProgress(status);

                    if (status.status === 'processing') {
                        setTimeout(poll, intervalMs);
                    } else if (status.status === 'completed') {
                        resolve(status);
                    } else {
                        reject(new Error(status.error || 'Export failed'));
                    }
                } catch (error) {
                    reject(error);
                }
            };
            poll();
        });
    }

    getDownloadUrl(exportId) {
        return `${this.apiBase}/exports/${exportId}/download`;
    }
}

// Usage
const exportManager = new ExportManager('/api');

async function runExport() {
    try {
        // Start export
        const { export_id, estimated_rows } = await exportManager.startExport(
            layoutId,
            'csv',
            { start_date: '2024-01-01', end_date: '2024-12-31' }
        );

        showProgress(0, estimated_rows);

        // Poll for status
        const result = await exportManager.pollStatus(export_id, (status) => {
            showProgress(status.progress, estimated_rows);
        });

        // Download complete file
        window.location.href = exportManager.getDownloadUrl(export_id);

    } catch (error) {
        showError(error.message);
    }
}
```

## Configuration

### Environment Variables

```env
# Queue name for export jobs
EXPORT_QUEUE=exports

# Storage disk for completed exports
EXPORT_DISK=local

# Path prefix on disk
EXPORT_PATH=exports

# Status cache TTL (seconds, default 24 hours)
EXPORT_STATUS_TTL=86400

# Chunk size for processing
EXPORT_CHUNK_SIZE=1000

# Job attempts and timeout (seconds)
EXPORT_JOB_TRIES=3
EXPORT_JOB_TIMEOUT=3600
```

### Queue Worker

Run a dedicated worker for exports:

```bash
# Single worker
php artisan queue:work --queue=exports

# Multiple workers with Supervisor
[program:export-worker]
command=php /var/www/artisan queue:work --queue=exports --sleep=3 --tries=3
numprocs=2
autostart=true
autorestart=true
```

### Storage Configuration

Configure where exports are stored:

```php
// config/filesystems.php
'disks' => [
    'exports' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_EXPORT_BUCKET'),
    ],
],
```

```env
EXPORT_DISK=exports
```

## Status Lifecycle

```
processing -> completed
          \-> failed
```

**Status values:**
- `processing` - Job is running, includes `progress` (0-100)
- `completed` - Job finished successfully, includes `path` and `url`
- `failed` - Job failed, includes `error` message

## Error Handling

The job handles errors gracefully:

```php
// On failure, status becomes:
[
    'status' => 'failed',
    'error' => 'Memory limit exceeded',
    'progress' => 75,  // Last known progress
]
```

### Retry Logic

The job sets `$tries` and `$timeout` from config in its constructor, so a subclass
property default is overwritten. Configure retries through the config keys instead:

```env
EXPORT_JOB_TRIES=3
EXPORT_JOB_TIMEOUT=3600
```

Backoff is not set by the package; add it in a subclass if you need one:

```php
class CustomExportJob extends ProcessExportJob
{
    public array $backoff = [60, 120, 300];  // Seconds between retries
}
```

## Cleanup

Exported files and status entries expire based on `status_ttl`. For manual cleanup:

```php
// Clean up old export files
$cutoff = now()->subDays(7);
$files = Storage::disk(config('laravel-exports.disk'))
    ->files(config('laravel-exports.path'));

foreach ($files as $file) {
    if (Storage::disk(config('laravel-exports.disk'))->lastModified($file) < $cutoff->timestamp) {
        Storage::disk(config('laravel-exports.disk'))->delete($file);
    }
}
```

## Performance Tips

1. **Use SQS or Redis** for the queue (not database)
2. **Run multiple workers** for parallel processing
3. **Use S3** for storage to avoid disk space issues
4. **Set appropriate chunk sizes** based on data complexity
5. **Monitor queue** for stuck jobs

## When to Use Background Jobs

Use background jobs when:
- Export has 100k+ records
- Export takes more than 30 seconds
- You need progress tracking
- Multiple users may export simultaneously

Use streaming when:
- User needs immediate download
- Records are under 100k
- Network connection is stable

## Related Documentation

- [Sample Data Setup](sample-data.md) - Create test data
- [Chunked Processing](chunked-processing.md) - Understanding chunks
- [Streaming Exports](streaming-exports.md) - Alternative approach
- [Configuration](../../configuration.md) - Queue and storage settings
