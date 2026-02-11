# Chunked Processing

Process large exports in batches to avoid memory issues.

## Scenario

Export 100,000+ orders without running out of memory.

## The Problem

Standard export loads all records:

```php
// This may fail with large datasets
$data = $service->executeExport($layout);  // Memory exhausted!
```

## The Solution: Chunked Processing

Process records in batches:

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();

$service->executeExportChunked(
    $layout,
    $requestData,
    1000,  // Process 1000 records at a time
    function ($chunk) {
        // Handle each chunk of 1000 records
        foreach ($chunk as $row) {
            // Process row
        }
    }
);
```

## Complete Example: Export to File

```php
<?php

namespace App\Services;

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use Illuminate\Support\Facades\Storage;

class LargeExportService
{
    public function exportToFile(string $layoutId, array $requestData = []): string
    {
        $layout = ExportLayout::findOrFail($layoutId);
        $service = new DynamicExportService();

        // Create temp file
        $filename = 'exports/orders-' . now()->format('Y-m-d-His') . '.csv';
        $path = Storage::path($filename);
        $handle = fopen($path, 'w');

        $headerWritten = false;

        $service->executeExportChunked(
            $layout,
            $requestData,
            1000,
            function ($chunk) use ($handle, &$headerWritten) {
                foreach ($chunk as $row) {
                    // Write header on first row
                    if (!$headerWritten) {
                        fputcsv($handle, array_keys($row));
                        $headerWritten = true;
                    }
                    fputcsv($handle, array_values($row));
                }
            }
        );

        fclose($handle);

        return $filename;
    }
}
```

## Usage

```php
$exportService = new LargeExportService();
$filename = $exportService->exportToFile($layoutId, [
    'created_at' => ['2024-01-01', '2024-12-31'],
]);

// Return download link
return Storage::download($filename);
```

## Chunk Size Guidelines

| Records | Chunk Size | Memory ~Usage |
|---------|------------|---------------|
| 10,000 | 1000 | ~50 MB |
| 50,000 | 500 | ~30 MB |
| 100,000 | 500 | ~30 MB |
| 500,000 | 200 | ~20 MB |
| 1,000,000+ | 100 | ~15 MB |

## Configuration

Set default chunk size:

```env
EXPORT_CHUNK_SIZE=500
```

Or in config:

```php
// config/laravel-exports.php
'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),
```

## Progress Tracking

Track progress during chunked export:

```php
$service = new DynamicExportService();

// Get total count first
$total = $service->getExportCount($layout, $requestData);
$processed = 0;

$service->executeExportChunked(
    $layout,
    $requestData,
    1000,
    function ($chunk) use (&$processed, $total) {
        $processed += count($chunk);
        $percentage = round(($processed / $total) * 100, 1);

        logger("Progress: {$processed}/{$total} ({$percentage}%)");

        // Or update a progress tracker
        Cache::put("export_progress_{$exportId}", [
            'processed' => $processed,
            'total' => $total,
            'percentage' => $percentage,
        ]);
    }
);
```

## Memory Monitoring

Monitor memory usage during export:

```php
$service->executeExportChunked(
    $layout,
    $requestData,
    500,
    function ($chunk) {
        // Process chunk...

        // Log memory usage
        $memory = memory_get_usage(true) / 1024 / 1024;
        $peak = memory_get_peak_usage(true) / 1024 / 1024;
        logger("Memory: {$memory}MB, Peak: {$peak}MB");

        // Force garbage collection if needed
        gc_collect_cycles();
    }
);
```

## Error Handling

Handle errors gracefully:

```php
$errors = [];
$processed = 0;

try {
    $service->executeExportChunked(
        $layout,
        $requestData,
        1000,
        function ($chunk) use (&$processed, &$errors) {
            foreach ($chunk as $row) {
                try {
                    // Process row
                    $processed++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'row' => $processed,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }
    );

    logger("Export complete. Processed: {$processed}, Errors: " . count($errors));

} catch (\Exception $e) {
    logger("Export failed at row {$processed}: " . $e->getMessage());
}
```

## Chunked Export to S3

Write chunks directly to S3:

```php
use Illuminate\Support\Facades\Storage;

$filename = 'exports/large-export.csv';
$disk = Storage::disk('s3');

// Create file with header
$disk->put($filename, '');

$headerWritten = false;

$service->executeExportChunked(
    $layout,
    $requestData,
    1000,
    function ($chunk) use ($disk, $filename, &$headerWritten) {
        $content = '';

        foreach ($chunk as $row) {
            if (!$headerWritten) {
                $content .= implode(',', array_keys($row)) . "\n";
                $headerWritten = true;
            }
            $content .= implode(',', array_map(function ($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, array_values($row))) . "\n";
        }

        // Append to file
        $disk->append($filename, $content);
    }
);
```

## Comparison: Memory Usage

Without chunking (100k records):
```
Start: 50 MB
After query: 2,500 MB
Peak: 3,000 MB
Result: Memory exhausted
```

With chunking (100k records, 500 chunk):
```
Start: 50 MB
Per chunk: ~30 MB
Peak: 80 MB
Result: Success
```

## Best Practices

1. **Choose appropriate chunk size** based on record complexity
2. **Use progress tracking** for long-running exports
3. **Handle errors** to avoid losing partial progress
4. **Clear memory** between chunks if needed
5. **Write to disk** instead of building in memory
6. **Monitor memory** during development

## Related Documentation

- [Sample Data Setup](sample-data.md) - Create test data
- [Streaming Exports](streaming-exports.md) - Stream directly to client
- [Background Jobs](background-jobs.md) - Async processing
- [Large Datasets Guide](../../guides/large-datasets.md) - Overview
