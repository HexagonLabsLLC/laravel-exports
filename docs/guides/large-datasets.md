# Large Datasets

Handle exports with hundreds of thousands or millions of records efficiently.

## Overview

For large datasets, use:

1. **Chunked Processing** - Process records in batches
2. **Streaming Exports** - Stream output to avoid memory limits
3. **Background Jobs** - Queue exports for async processing

## Memory Considerations

### Default Processing

Standard export loads all records into memory:

```php
// This loads all records at once
$service->executeExport($layout);  // May fail with 100k+ records
```

### Memory-Efficient Options

| Method | Use Case | Memory Usage |
|--------|----------|--------------|
| Chunked | Processing callbacks | Low |
| Streaming | Direct download | Very low |
| Background | Large files, async | Low (server-side) |

All three derive rows chunk by chunk, so none of them supports a layout with an
`is_expanded` column: expanded columns need the full dataset to determine the column
set, and chunked, streamed, paginated, and queued exports throw a `RuntimeException`.
Use `executeExport()` for those layouts.

## Chunked Processing

Process records in batches:

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();

$service->executeExportChunked(
    $layout,
    $requestData,
    500,  // Chunk size
    function ($chunk) {
        // Process each chunk of 500 records
        foreach ($chunk as $row) {
            // Do something with each row
        }
    }
);
```

### Chunk Size Guidelines

| Dataset Size | Recommended Chunk Size |
|--------------|------------------------|
| 10,000 - 50,000 | 1000 |
| 50,000 - 200,000 | 500 |
| 200,000 - 1,000,000 | 200 |
| 1,000,000+ | 100 |

### Configuration

Set default chunk size:

```env
EXPORT_CHUNK_SIZE=500
```

Or in config:

```php
'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
```

## Streaming Exports

Stream the export directly to the browser:

```php
public function downloadLargeExport(Request $request, string $layoutId)
{
    $layout = ExportLayout::findOrFail($layoutId);
    $service = new DynamicExportService();

    return $service->streamAs(
        $layout,
        'csv',
        'large-export.csv',
        $request->all(),    // Request data
        ['delimiter' => ','], // CSV options
        500                   // Chunk size
    );
}
```

### How Streaming Works

1. Headers sent immediately
2. Records processed in chunks
3. Each chunk written to output
4. Browser receives data progressively

### Benefits

- No memory limit issues
- User sees download progress
- Works with any dataset size

## Background Jobs

Queue exports for asynchronous processing.

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

// Return the export ID to the client
return response()->json([
    'export_id' => $exportId,
    'message' => 'Export started',
]);
```

Any format registered with `ExportFactory` can be queued. `csv` and `json` are written chunk by chunk to a temp file and then streamed to the disk, so memory stays flat; queued `json` is always a plain array of row objects. xlsx and custom handlers buffer the full result set in memory and call the handler's `export()`, with `$options` passed through:

```php
$exportId = $service->queueExport($layout, 'xlsx', $requestData, ['sheet_by' => 'Region']);
```

Prefer csv for very large exports. An unregistered format, or `xlsx` without `phpoffice/phpspreadsheet`, marks the export `failed` with the reason in `error`.

### Check Status

```php
use HexagonLabsLLC\LaravelExports\Jobs\ProcessExportJob;

// Get status
$status = ProcessExportJob::getStatus($exportId);

// Status structure (always: status, progress, export_id, layout_id, format, updated_at):
// [
//     'status' => 'processing',   // processing, completed, failed
//     'progress' => 45,           // Percentage (0-100)
//     'processed_rows' => 4500,   // While processing
//     'total_rows' => 10000,      // While processing
// ]
// On completion: row_count, path, disk, url, filename, completed_at
// On failure: error, failed_at

// Check if complete
if (ProcessExportJob::isComplete($exportId)) {
    // Export finished (success or failure)
}

// Check if successful
if (ProcessExportJob::isSuccessful($exportId)) {
    $url = ProcessExportJob::getDownloadUrl($exportId);
    $path = ProcessExportJob::getFilePath($exportId);
}
```

### Configuration

```env
# Queue name
EXPORT_QUEUE=exports

# Storage disk for completed files
EXPORT_DISK=local

# Path prefix on disk
EXPORT_PATH=exports

# Status cache TTL (seconds)
EXPORT_STATUS_TTL=86400

# Chunk size
EXPORT_CHUNK_SIZE=1000
```

### Queue Worker

Run a dedicated worker:

```bash
php artisan queue:work --queue=exports
```

Or with other queues:

```bash
php artisan queue:work --queue=high,default,exports
```

### Controller Example

```php
public function startExport(Request $request, string $layoutId)
{
    $layout = ExportLayout::findOrFail($layoutId);
    $service = new DynamicExportService();

    $exportId = $service->queueExport(
        $layout,
        $request->input('format', 'csv'),
        $request->except('format')
    );

    return response()->json([
        'export_id' => $exportId,
    ]);
}

public function checkStatus(string $exportId)
{
    $status = ProcessExportJob::getStatus($exportId);

    if (!$status) {
        return response()->json(['error' => 'Export not found'], 404);
    }

    return response()->json($status);
}

public function download(string $exportId)
{
    if (!ProcessExportJob::isSuccessful($exportId)) {
        return response()->json(['error' => 'Export not ready'], 400);
    }

    $path = ProcessExportJob::getFilePath($exportId);

    return Storage::disk(config('laravel-exports.disk'))
        ->download($path);
}
```

### Frontend Integration

```javascript
// Start export
const response = await fetch('/api/exports/start', {
    method: 'POST',
    body: JSON.stringify({ layout_id: layoutId, format: 'csv' }),
});
const { export_id } = await response.json();

// Poll for status
const pollStatus = async () => {
    const status = await fetch(`/api/exports/status/${export_id}`).then(r => r.json());

    if (status.status === 'processing') {
        updateProgressBar(status.progress);
        setTimeout(pollStatus, 2000);  // Poll every 2 seconds
    } else if (status.status === 'completed') {
        window.location.href = `/api/exports/download/${export_id}`;
    } else {
        showError(status.error);
    }
};

pollStatus();
```

## Paginated Exports

For API responses with pagination:

```php
$result = $service->executeExportPaginated(
    $layout,
    $requestData,
    100,  // Per page
    1     // Page number
);

// Response structure:
// [
//     'data' => [...],
//     'meta' => [
//         'current_page' => 1,
//         'last_page' => 10,
//         'per_page' => 100,
//         'total' => 1000,
//         'from' => 1,
//         'to' => 100,
//     ],
// ]
```

### API Controller

```php
public function exportPage(Request $request, string $layoutId)
{
    $layout = ExportLayout::findOrFail($layoutId);
    $service = new DynamicExportService();

    return response()->json(
        $service->executeExportPaginated(
            $layout,
            $request->except(['page', 'per_page']),
            $request->input('per_page', 100),
            $request->input('page', 1)
        )
    );
}
```

## Performance Tips

### Database Indexes

Add indexes for filtered and sorted columns:

```php
Schema::table('users', function (Blueprint $table) {
    $table->index('status');
    $table->index('created_at');
    $table->index(['status', 'created_at']);
});
```

### Optimize Eager Loading

The service auto-loads relationships, but you can help:

1. Keep relationships simple
2. Avoid deeply nested paths (4+ levels)
3. Use `select()` in relationships if possible

### Reduce Column Count

Fewer columns = faster processing:

```php
// Instead of 50 columns, create focused layouts
$summaryLayout = ExportLayout::firstWhere('name', 'orders_summary');   // 10 columns for quick exports
$detailedLayout = ExportLayout::firstWhere('name', 'orders_detailed'); // 50 columns for full exports
```

### Use Appropriate Chunk Sizes

Test different sizes:

```php
// Measure processing time
$start = microtime(true);

$service->executeExportChunked($layout, [], 500, function ($chunk) {
    // Process
});

$time = microtime(true) - $start;
logger("Chunk 500: {$time}s");
```

## Example: Large Order Export

```php
// Layout for 1M+ orders
$layout = ExportLayout::create([
    'export_model_id' => $orderModel->id,
    'name' => 'all_orders_export',
    'title' => 'All Orders Export',
]);

// Keep columns minimal for performance
$columns = [
    ['title' => 'Order #', 'value_path' => 'order_number', 'position' => 1],
    ['title' => 'Date', 'value_path' => 'created_at', 'position' => 2],
    ['title' => 'Customer', 'value_path' => 'customer.name', 'position' => 3],
    ['title' => 'Total', 'value_path' => 'total', 'position' => 4],
    ['title' => 'Status', 'value_path' => 'status', 'position' => 5],
];

foreach ($columns as $col) {
    ExportColumn::create(array_merge($col, ['export_layout_id' => $layout->id]));
}

// Add required date filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => true,  // Prevent accidental full exports
]);

// Export with background job
$exportId = $service->queueExport($layout, 'csv', [
    'created_at' => ['2024-01-01', '2024-12-31'],
]);
```

## Monitoring

Track export performance:

```php
// In your job or service
Log::info('Export started', [
    'layout_id' => $layout->id,
    'estimated_rows' => $service->getExportCount($layout, $requestData),
]);

$start = microtime(true);
// ... export logic ...
$duration = microtime(true) - $start;

Log::info('Export completed', [
    'layout_id' => $layout->id,
    'rows' => $rowCount,
    'duration' => $duration,
    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024 . 'MB',
]);
```

## Related Documentation

- [Configuration](../configuration.md) - Queue and storage settings
- [Chunked Processing Example](../examples/large-scale/chunked-processing.md)
- [Streaming Exports Example](../examples/large-scale/streaming-exports.md)
- [Background Jobs Example](../examples/large-scale/background-jobs.md)
