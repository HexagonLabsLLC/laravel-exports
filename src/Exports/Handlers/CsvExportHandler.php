<?php

namespace HexagonLabsLLC\LaravelExports\Exports\Handlers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportHandler extends ExportHandler
{
    /**
     * Get default options for CSV export
     */
    protected function getDefaultOptions(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'escape' => '\\',
            'include_headers' => true,
            'bom' => false, // Byte Order Mark for Excel compatibility
        ];
    }

    /**
     * Export data to CSV format
     */
    public function export(Collection $data): string
    {
        $output = '';

        // Add BOM if requested (for Excel UTF-8 compatibility)
        if ($this->options['bom']) {
            $output = "\xEF\xBB\xBF";
        }

        // Get headers from first row
        if ($this->options['include_headers'] && $data->isNotEmpty()) {
            $headers = array_keys($data->first());
            $output .= $this->arrayToCsv($headers);
        }

        // Convert each row
        foreach ($data as $row) {
            $output .= $this->arrayToCsv(array_values($row));
        }

        return $output;
    }

    /**
     * Create a download response
     */
    public function download(mixed $export, string $filename): Response
    {
        $filename = $this->sanitizeFilename($filename);

        return response($export)
            ->header('Content-Type', $this->getMimeType())
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Store the CSV to a file
     */
    public function store(mixed $export, string $path): bool
    {
        return Storage::put($path, $export);
    }

    /**
     * Stream CSV for large datasets
     */
    public function stream(callable $dataCallback, string $filename): Response|StreamedResponse
    {
        $filename = $this->sanitizeFilename($filename);

        /** @var StreamedResponse $response */
        $response = response()->stream(function () use ($dataCallback) {
            $handle = fopen('php://output', 'w');

            // Add BOM if requested
            if ($this->options['bom']) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            $isFirstChunk = true;

            // Process data in chunks
            $dataCallback(function (Collection $chunk) use ($handle, &$isFirstChunk) {
                // Write headers on first chunk
                if ($isFirstChunk && $this->options['include_headers'] && $chunk->isNotEmpty()) {
                    $headers = array_keys($chunk->first());
                    fputcsv($handle, $headers, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
                    $isFirstChunk = false;
                }

                // Write data rows
                foreach ($chunk as $row) {
                    fputcsv($handle, array_values($row), $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => $this->getMimeType(),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
        
        return $response;
    }

    /**
     * Convert array to CSV line
     */
    protected function arrayToCsv(array $data): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $data, $this->options['delimiter'], $this->options['enclosure'], $this->options['escape']);
        rewind($handle);
        $line = stream_get_contents($handle);
        fclose($handle);

        return $line;
    }

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        return 'csv';
    }

    /**
     * Get MIME type
     */
    public function getMimeType(): string
    {
        return 'text/csv';
    }
}
