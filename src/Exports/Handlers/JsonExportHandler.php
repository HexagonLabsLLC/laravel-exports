<?php

namespace Hexlabs\LaravelExports\Exports\Handlers;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JsonExportHandler extends ExportHandler
{
    /**
     * Get default options for JSON export
     */
    protected function getDefaultOptions(): array
    {
        return [
            'pretty' => false,
            'unescaped_slashes' => true,
            'unescaped_unicode' => true,
            'wrap_data' => true, // Wrap in 'data' key
            'include_meta' => true, // Include metadata
        ];
    }

    /**
     * Export data to JSON format
     */
    public function export(Collection $data): string
    {
        $output = [];

        // Add metadata if requested
        if ($this->options['include_meta']) {
            $output['meta'] = [
                'exported_at' => now()->toIso8601String(),
                'total_records' => $data->count(),
                'layout' => $this->layout->name,
                'model' => $this->layout->exportModel->name,
            ];
        }

        // Add data
        if ($this->options['wrap_data']) {
            $output['data'] = $data->toArray();
        } else {
            $output = $data->toArray();
        }

        // Build JSON flags
        $flags = JSON_THROW_ON_ERROR;

        if ($this->options['pretty']) {
            $flags |= JSON_PRETTY_PRINT;
        }

        if ($this->options['unescaped_slashes']) {
            $flags |= JSON_UNESCAPED_SLASHES;
        }

        if ($this->options['unescaped_unicode']) {
            $flags |= JSON_UNESCAPED_UNICODE;
        }

        return json_encode($output, $flags);
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
     * Store the JSON to a file
     */
    public function store(mixed $export, string $path): bool
    {
        return Storage::put($path, $export);
    }

    /**
     * Stream JSON for large datasets
     */
    public function stream(callable $dataCallback, string $filename): Response|StreamedResponse
    {
        $filename = $this->sanitizeFilename($filename);

        return response()->stream(function () use ($dataCallback) {
            $isFirstChunk = true;
            $recordCount = 0;

            // Start JSON structure
            echo '{';

            // Add metadata if requested
            if ($this->options['include_meta']) {
                echo '"meta":{';
                echo '"exported_at":"'.now()->toIso8601String().'",';
                echo '"layout":"'.addslashes($this->layout->name).'",';
                echo '"model":"'.addslashes($this->layout->exportModel->name).'"';
                echo '}';

                if ($this->options['wrap_data']) {
                    echo ',';
                }
            }

            // Start data array
            if ($this->options['wrap_data']) {
                echo '"data":[';
            } elseif (! $this->options['include_meta']) {
                echo '[';
            }

            // Process data in chunks
            $dataCallback(function (Collection $chunk) use (&$isFirstChunk, &$recordCount) {
                foreach ($chunk as $row) {
                    if (! $isFirstChunk) {
                        echo ',';
                    }

                    $flags = JSON_THROW_ON_ERROR;
                    if ($this->options['unescaped_slashes']) {
                        $flags |= JSON_UNESCAPED_SLASHES;
                    }
                    if ($this->options['unescaped_unicode']) {
                        $flags |= JSON_UNESCAPED_UNICODE;
                    }

                    echo json_encode($row, $flags);

                    $isFirstChunk = false;
                    $recordCount++;
                }
            });

            // Close JSON structure
            echo ']';

            if ($this->options['wrap_data'] || $this->options['include_meta']) {
                echo '}';
            }

        }, 200, [
            'Content-Type' => $this->getMimeType(),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Get file extension
     */
    public function getExtension(): string
    {
        return 'json';
    }

    /**
     * Get MIME type
     */
    public function getMimeType(): string
    {
        return 'application/json';
    }
}
