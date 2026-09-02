<?php

namespace HexagonLabsLLC\LaravelExports\Jobs;

use HexagonLabsLLC\LaravelExports\Exports\ExportFactory;
use HexagonLabsLLC\LaravelExports\Exports\Handlers\ExportHandler;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $exportId,
        public string $layoutId,
        public string $format = 'csv',
        public array $requestData = [],
        public array $options = [],
        public int $chunkSize = 1000,
        public ?string $disk = null,
        public ?string $path = null,
    ) {
        $this->format = strtolower($format);
        $this->tries = config('laravel-exports.job_tries', 3);
        $this->timeout = config('laravel-exports.job_timeout', 3600);
        $this->disk = $disk ?? config('laravel-exports.disk', 'local');
        $this->path = $path ?? config('laravel-exports.path', 'exports');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->updateStatus('processing', ['progress' => 0, 'started_at' => now()->toIso8601String()]);

        try {
            // Load the layout
            $layout = ExportLayout::findOrFail($this->layoutId);

            // csv and json are written row by row below so memory stays flat.
            // Every other registered format is built by its handler, which
            // needs the whole result set at once.
            $handler = in_array($this->format, ['csv', 'json'], true)
                ? null
                : ExportFactory::create($this->format, $layout, $this->options);

            // Create the export service using DI
            $service = app(DynamicExportService::class);

            // Get total count for progress tracking
            $totalCount = $service->getExportCount($layout, $this->requestData);

            if ($totalCount === 0) {
                $this->updateStatus('completed', [
                    'progress' => 100,
                    'row_count' => 0,
                    'message' => 'No records to export',
                    'completed_at' => now()->toIso8601String(),
                ]);

                return;
            }

            // Generate filename
            $extension = $handler ? $handler->getExtension() : $this->format;
            $filename = $this->generateFilename($layout, $extension);
            $tempPath = sys_get_temp_dir().'/'.$this->exportId.'.'.$extension;

            $processedRows = $handler
                ? $this->writeWithHandler($handler, $service, $layout, $tempPath, $totalCount)
                : $this->writeChunked($service, $layout, $tempPath, $totalCount);

            // Move to final storage location as a stream so large exports don't load into memory
            $finalPath = $this->path.'/'.$filename;
            $stream = fopen($tempPath, 'r');

            if ($stream === false) {
                throw new \RuntimeException("Failed to read temp file: {$tempPath}");
            }

            Storage::disk($this->disk)->put($finalPath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            // Clean up temp file
            @unlink($tempPath);

            // Generate URL if possible
            $url = null;
            try {
                $url = Storage::disk($this->disk)->url($finalPath);
            } catch (\Exception $e) {
                // URL generation not supported for this disk
                Log::debug("URL generation not supported for disk {$this->disk}");
            }

            // Mark as completed
            $this->updateStatus('completed', [
                'progress' => 100,
                'row_count' => $processedRows,
                'path' => $finalPath,
                'disk' => $this->disk,
                'url' => $url,
                'filename' => $filename,
                'completed_at' => now()->toIso8601String(),
            ]);

        } catch (\Throwable $e) {
            Log::error("Export job failed: {$e->getMessage()}", [
                'export_id' => $this->exportId,
                'layout_id' => $this->layoutId,
                'exception' => $e,
            ]);

            $this->updateStatus('failed', [
                'error' => $e->getMessage(),
                'failed_at' => now()->toIso8601String(),
            ]);

            throw $e;
        }
    }

    /**
     * Write csv or json to the temp file one chunk at a time.
     */
    protected function writeChunked(DynamicExportService $service, ExportLayout $layout, string $tempPath, int $totalCount): int
    {
        $handle = fopen($tempPath, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Failed to create temp file: {$tempPath}");
        }

        $processedRows = 0;
        $isFirstChunk = true;

        if ($this->format === 'json') {
            fwrite($handle, '[');
        }

        $service->executeExportChunked(
            $layout,
            $this->requestData,
            $this->chunkSize,
            function ($chunk) use ($handle, &$processedRows, $totalCount, &$isFirstChunk) {
                if ($this->format === 'csv') {
                    $delimiter = $this->options['delimiter'] ?? ',';
                    $enclosure = $this->options['enclosure'] ?? '"';
                    $escape = $this->options['escape'] ?? '\\';

                    // Write headers on first chunk
                    if ($isFirstChunk && $chunk->isNotEmpty()) {
                        fputcsv($handle, array_keys($chunk->first()), $delimiter, $enclosure, $escape);
                    }

                    foreach ($chunk as $row) {
                        fputcsv($handle, $this->sanitizeCsvRow(array_values($row)), $delimiter, $enclosure, $escape);
                    }
                } else {
                    foreach ($chunk as $index => $row) {
                        if (!$isFirstChunk || $index > 0) {
                            fwrite($handle, ',');
                        }
                        fwrite($handle, json_encode($row, JSON_PRETTY_PRINT));
                    }
                }

                $isFirstChunk = false;
                $processedRows += $chunk->count();

                $this->reportProgress($processedRows, $totalCount);
            }
        );

        if ($this->format === 'json') {
            fwrite($handle, ']');
        }

        fclose($handle);

        return $processedRows;
    }

    /**
     * Build the file through an export handler. Chunking still bounds query
     * memory, but the handler is handed every row at once, so peak memory
     * grows with the result set. Prefer csv for very large queued exports.
     */
    protected function writeWithHandler(ExportHandler $handler, DynamicExportService $service, ExportLayout $layout, string $tempPath, int $totalCount): int
    {
        $rows = collect();
        $processedRows = 0;

        $service->executeExportChunked(
            $layout,
            $this->requestData,
            $this->chunkSize,
            function ($chunk) use ($rows, &$processedRows, $totalCount) {
                foreach ($chunk as $row) {
                    $rows->push($row);
                }

                $processedRows += $chunk->count();

                $this->reportProgress($processedRows, $totalCount);
            }
        );

        $content = $handler->export($rows);

        if (!is_string($content)) {
            throw new \RuntimeException("Queued exports need a string from the {$this->format} handler's export() method.");
        }

        if (file_put_contents($tempPath, $content) === false) {
            throw new \RuntimeException("Failed to create temp file: {$tempPath}");
        }

        return $processedRows;
    }

    /**
     * Report chunk progress, capped below 100 until the file is stored.
     */
    protected function reportProgress(int $processedRows, int $totalCount): void
    {
        $this->updateStatus('processing', [
            'progress' => min(99, (int)(($processedRows / $totalCount) * 100)),
            'processed_rows' => $processedRows,
            'total_rows' => $totalCount,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->updateStatus('failed', [
            'error' => $exception->getMessage(),
            'failed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Neutralize cells that spreadsheet apps would execute as formulas.
     */
    protected function sanitizeCsvRow(array $row): array
    {
        if (!($this->options['escape_formulas'] ?? true)) {
            return $row;
        }

        return array_map(static function ($value) {
            if (is_string($value) && $value !== '' && !is_numeric($value)
                && strpbrk($value[0], "=+-@\t\r") !== false) {
                return "'".$value;
            }

            return $value;
        }, $row);
    }

    /**
     * Update the export status in cache.
     */
    protected function updateStatus(string $status, array $data = []): void
    {
        $ttl = config('laravel-exports.status_ttl', 86400);

        $currentStatus = Cache::get("export_status:{$this->exportId}", []);

        $newStatus = array_merge($currentStatus, $data, [
            'status' => $status,
            'export_id' => $this->exportId,
            'layout_id' => $this->layoutId,
            'format' => $this->format,
            'updated_at' => now()->toIso8601String(),
        ]);

        Cache::put("export_status:{$this->exportId}", $newStatus, $ttl);
    }

    /**
     * Generate a filename for the export.
     */
    protected function generateFilename(ExportLayout $layout, string $extension): string
    {
        $baseName = Str::slug($layout->title ?: 'export');
        $timestamp = now()->format('Y-m-d_His');

        return "{$baseName}_{$timestamp}.{$extension}";
    }

    /**
     * Get the status of an export.
     */
    public static function getStatus(string $exportId): ?array
    {
        return Cache::get("export_status:{$exportId}");
    }

    /**
     * Check if an export is complete.
     */
    public static function isComplete(string $exportId): bool
    {
        $status = static::getStatus($exportId);

        return $status && in_array($status['status'] ?? null, ['completed', 'failed']);
    }

    /**
     * Check if an export was successful.
     */
    public static function isSuccessful(string $exportId): bool
    {
        $status = static::getStatus($exportId);

        return $status && ($status['status'] ?? null) === 'completed';
    }

    /**
     * Get the download URL for a completed export.
     */
    public static function getDownloadUrl(string $exportId): ?string
    {
        $status = static::getStatus($exportId);

        if (!$status || ($status['status'] ?? null) !== 'completed') {
            return null;
        }

        return $status['url'] ?? null;
    }

    /**
     * Get the file path for a completed export.
     */
    public static function getFilePath(string $exportId): ?string
    {
        $status = static::getStatus($exportId);

        if (!$status || ($status['status'] ?? null) !== 'completed') {
            return null;
        }

        return $status['path'] ?? null;
    }
}
