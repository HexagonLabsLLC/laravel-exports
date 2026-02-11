<?php

namespace HexagonLabsLLC\LaravelExports\Jobs;

use HexagonLabsLLC\LaravelExports\Exports\ExportFactory;
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
            $filename = $this->generateFilename($layout);
            $tempPath = sys_get_temp_dir().'/'.$this->exportId.'.'.$this->format;

            // Create export handler
            $handler = ExportFactory::create($this->format, $layout, $this->options);

            // Open temp file for writing
            $handle = fopen($tempPath, 'w');
            if ($handle === false) {
                throw new \RuntimeException("Failed to create temp file: {$tempPath}");
            }

            $processedRows = 0;
            $isFirstChunk = true;

            // Write headers for CSV
            if ($this->format === 'csv') {
                // We'll write headers with the first chunk
            } elseif ($this->format === 'json') {
                fwrite($handle, '[');
            }

            // Process in chunks
            $service->executeExportChunked(
                $layout,
                $this->requestData,
                $this->chunkSize,
                function ($chunk) use ($handle, &$processedRows, $totalCount, &$isFirstChunk) {
                    if ($this->format === 'csv') {
                        // Write headers on first chunk
                        if ($isFirstChunk && $chunk->isNotEmpty()) {
                            $headers = array_keys($chunk->first());
                            $delimiter = $this->options['delimiter'] ?? ',';
                            fputcsv($handle, $headers, $delimiter);
                        }

                        // Write rows
                        $delimiter = $this->options['delimiter'] ?? ',';
                        foreach ($chunk as $row) {
                            fputcsv($handle, array_values($row), $delimiter);
                        }
                    } elseif ($this->format === 'json') {
                        foreach ($chunk as $index => $row) {
                            if (! $isFirstChunk || $index > 0) {
                                fwrite($handle, ',');
                            }
                            fwrite($handle, json_encode($row, JSON_PRETTY_PRINT));
                        }
                    }

                    $isFirstChunk = false;
                    $processedRows += $chunk->count();

                    // Update progress
                    $progress = min(99, (int) (($processedRows / $totalCount) * 100));
                    $this->updateStatus('processing', [
                        'progress' => $progress,
                        'processed_rows' => $processedRows,
                        'total_rows' => $totalCount,
                    ]);
                }
            );

            // Close JSON array
            if ($this->format === 'json') {
                fwrite($handle, ']');
            }

            fclose($handle);

            // Move to final storage location
            $finalPath = $this->path.'/'.$filename;
            $fileContents = file_get_contents($tempPath);

            if ($fileContents === false) {
                throw new \RuntimeException("Failed to read temp file: {$tempPath}");
            }

            Storage::disk($this->disk)->put($finalPath, $fileContents);

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
    protected function generateFilename(ExportLayout $layout): string
    {
        $baseName = Str::slug($layout->title ?: 'export');
        $timestamp = now()->format('Y-m-d_His');

        return "{$baseName}_{$timestamp}.{$this->format}";
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

        if (! $status || ($status['status'] ?? null) !== 'completed') {
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

        if (! $status || ($status['status'] ?? null) !== 'completed') {
            return null;
        }

        return $status['path'] ?? null;
    }
}
