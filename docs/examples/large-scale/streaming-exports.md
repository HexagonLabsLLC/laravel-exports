# Streaming Exports

Stream large exports directly to the browser without memory issues.

## Scenario

Allow users to download exports with millions of records.

## Basic Streaming

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

public function download(Request $request, string $layoutId)
{
    $layout = ExportLayout::findOrFail($layoutId);
    $service = new DynamicExportService();

    return $service->streamAs(
        $layout,
        'csv',
        'large-export.csv',
        $request->all(),  // Request data for filters
        [],               // CSV options
        1000              // Chunk size
    );
}
```

## How It Works

1. **Response starts immediately** - Headers sent before data
2. **Query chunked** - Records fetched in batches
3. **Output flushed** - Each chunk written and sent
4. **Memory stays low** - Only one chunk in memory at a time

## Controller Implementation

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

class ExportController extends Controller
{
    public function streamExport(Request $request, string $layoutId)
    {
        $validated = $request->validate([
            'format' => 'in:csv,json',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $layout = ExportLayout::findOrFail($layoutId);
        $service = new DynamicExportService();

        $format = $validated['format'] ?? 'csv';
        $filename = 'export-' . now()->format('Y-m-d') . '.' . $format;

        // Check estimated size
        $count = $service->getExportCount($layout, $request->all());

        if ($count > 1000000) {
            return response()->json([
                'error' => 'Export too large. Use background processing.',
                'count' => $count,
            ], 400);
        }

        return $service->streamAs(
            $layout,
            $format,
            $filename,
            $request->except('format'),
            $this->getFormatOptions($format),
            $this->getChunkSize($count)
        );
    }

    private function getFormatOptions(string $format): array
    {
        return match ($format) {
            'csv' => ['delimiter' => ',', 'headers' => true],
            'json' => ['pretty' => false],
            default => [],
        };
    }

    private function getChunkSize(int $count): int
    {
        return match (true) {
            $count < 10000 => 1000,
            $count < 100000 => 500,
            default => 200,
        };
    }
}
```

## Streaming CSV Options

```php
return $service->streamAs(
    $layout,
    'csv',
    'export.csv',
    $requestData,
    [
        'delimiter' => ',',      // Field delimiter
        'enclosure' => '"',      // Text enclosure
        'escape' => '\\',        // Escape character
        'headers' => true,       // Include header row
    ],
    500  // Chunk size
);
```

## Streaming JSON Options

```php
return $service->streamAs(
    $layout,
    'json',
    'export.json',
    $requestData,
    [
        'pretty' => false,  // Compact output for streaming
    ],
    500
);
```

## Custom Streaming Response

Build your own streaming response:

```php
use Symfony\Component\HttpFoundation\StreamedResponse;

public function customStream(Request $request, string $layoutId)
{
    $layout = ExportLayout::findOrFail($layoutId);
    $service = new DynamicExportService();

    $response = new StreamedResponse(function () use ($service, $layout, $request) {
        $handle = fopen('php://output', 'w');
        $headerWritten = false;

        $service->executeExportChunked(
            $layout,
            $request->all(),
            500,
            function ($chunk) use ($handle, &$headerWritten) {
                foreach ($chunk as $row) {
                    if (!$headerWritten) {
                        fputcsv($handle, array_keys($row));
                        $headerWritten = true;
                    }
                    fputcsv($handle, array_values($row));
                }
                flush();
            }
        );

        fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="export.csv"');

    return $response;
}
```

## Progress with Streaming

Streaming doesn't support progress indicators during download. For progress, use background jobs instead.

However, you can show the count before starting:

```php
public function prepareExport(Request $request, string $layoutId)
{
    $layout = ExportLayout::findOrFail($layoutId);
    $service = new DynamicExportService();

    $count = $service->getExportCount($layout, $request->all());

    return response()->json([
        'count' => $count,
        'estimated_size' => $this->estimateSize($count),
        'download_url' => route('exports.stream', ['layout' => $layoutId]),
    ]);
}

private function estimateSize(int $count): string
{
    // Rough estimate: ~100 bytes per row for typical data
    $bytes = $count * 100;
    return number_format($bytes / 1024 / 1024, 1) . ' MB';
}
```

## Frontend Integration

```javascript
async function downloadExport(layoutId, filters) {
    // First, check the size
    const prep = await fetch(`/api/exports/${layoutId}/prepare`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(filters),
    }).then(r => r.json());

    // Confirm if large
    if (prep.count > 100000) {
        if (!confirm(`This export has ${prep.count.toLocaleString()} records (~${prep.estimated_size}). Continue?`)) {
            return;
        }
    }

    // Start download
    showLoadingIndicator();

    const response = await fetch(`/api/exports/${layoutId}/stream`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...filters, format: 'csv' }),
    });

    // Create download
    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `export-${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);

    hideLoadingIndicator();
}
```

## Timeout Handling

Long streams may hit timeouts. Increase limits:

```php
// In controller
public function streamExport(Request $request, string $layoutId)
{
    // Extend execution time
    set_time_limit(600);  // 10 minutes

    // Disable output buffering
    if (ob_get_level()) {
        ob_end_clean();
    }

    // ... streaming code
}
```

Or in nginx:

```nginx
location /api/exports {
    proxy_read_timeout 600s;
    proxy_buffering off;
}
```

## Memory Comparison

| Approach | 100k Records | 1M Records |
|----------|--------------|------------|
| Standard | ~2.5 GB (fails) | N/A |
| Streaming (500 chunk) | ~50 MB | ~50 MB |

## When to Use Streaming

Use streaming when:
- User is waiting for immediate download
- Records are under ~500k
- Network is stable

Use background jobs when:
- Records exceed 500k
- You need progress tracking
- Network may be unreliable
- Export takes more than a few minutes

## Related Documentation

- [Sample Data Setup](sample-data.md) - Create test data
- [Chunked Processing](chunked-processing.md) - Understanding chunks
- [Background Jobs](background-jobs.md) - Async processing
