<?php

namespace Hexlabs\LaravelExports\Exports\Handlers;

use Hexlabs\LaravelExports\Models\ExportLayout;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class ExportHandler
{
    protected ExportLayout $layout;

    protected array $options = [];

    public function __construct(ExportLayout $layout, array $options = [])
    {
        $this->layout = $layout;
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * Export data to the specific format
     *
     * @param  Collection  $data  The processed export data
     * @return mixed The exported content (string, resource, etc.)
     */
    abstract public function export(Collection $data): mixed;

    /**
     * Create a download response for the export
     *
     * @param  mixed  $export  The exported content
     * @param  string  $filename  The filename for download
     */
    abstract public function download(mixed $export, string $filename): Response;

    /**
     * Store the export to a file
     *
     * @param  mixed  $export  The exported content
     * @param  string  $path  The storage path
     * @return bool Success status
     */
    abstract public function store(mixed $export, string $path): bool;

    /**
     * Stream the export for large datasets
     *
     * @param  callable  $dataCallback  Callback that provides data chunks
     */
    abstract public function stream(callable $dataCallback, string $filename): Response|StreamedResponse;

    /**
     * Get default options for the handler
     */
    protected function getDefaultOptions(): array
    {
        return [];
    }

    /**
     * Get file extension for this export type
     */
    abstract public function getExtension(): string;

    /**
     * Get MIME type for this export format
     */
    abstract public function getMimeType(): string;

    /**
     * Sanitize filename
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove extension if already present
        $filename = preg_replace('/\.'.preg_quote($this->getExtension(), '/').'$/i', '', $filename);

        // Clean filename
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);

        // Add extension
        return $filename.'.'.$this->getExtension();
    }
}
