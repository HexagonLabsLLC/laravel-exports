<?php

namespace HexagonLabsLLC\LaravelExports\Exports\Handlers;

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsxExportHandler extends ExportHandler
{
    public function __construct(ExportLayout $layout, array $options = [])
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new \RuntimeException(
                'The xlsx format requires the optional phpoffice/phpspreadsheet package. Install it with: composer require phpoffice/phpspreadsheet'
            );
        }

        parent::__construct($layout, $options);
    }

    protected function getDefaultOptions(): array
    {
        return [
            'include_headers' => true,
        ];
    }

    public function export(Collection $data): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;

        if ($this->options['include_headers'] && $data->isNotEmpty()) {
            $this->writeRow($sheet, array_keys($data->first()), $rowIndex++);
        }

        foreach ($data as $row) {
            $this->writeRow($sheet, array_values($row), $rowIndex++);
        }

        return $this->toXlsxString($spreadsheet);
    }

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

    public function store(mixed $export, string $path): bool
    {
        return Storage::put($path, $export);
    }

    public function stream(callable $dataCallback, string $filename): Response|StreamedResponse
    {
        $filename = $this->sanitizeFilename($filename);

        return response()->stream(function () use ($dataCallback) {
            // PhpSpreadsheet holds the whole workbook in memory; chunking only
            // bounds query memory. Prefer csv for very large exports.
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $rowIndex = 1;
            $isFirstChunk = true;

            $dataCallback(function (Collection $chunk) use ($sheet, &$rowIndex, &$isFirstChunk) {
                if ($isFirstChunk && $this->options['include_headers'] && $chunk->isNotEmpty()) {
                    $this->writeRow($sheet, array_keys($chunk->first()), $rowIndex++);
                }
                $isFirstChunk = false;

                foreach ($chunk as $row) {
                    $this->writeRow($sheet, array_values($row), $rowIndex++);
                }
            });

            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => $this->getMimeType(),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function getExtension(): string
    {
        return 'xlsx';
    }

    public function getMimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /**
     * Strings are written with an explicit type so a value like '=SUM(A1)'
     * is stored as text instead of executing as a formula.
     */
    protected function writeRow(Worksheet $sheet, array $values, int $rowIndex): void
    {
        $column = 1;

        foreach ($values as $value) {
            if (!is_scalar($value) && $value !== null) {
                $value = json_encode($value);
            }

            if (is_string($value)) {
                $sheet->setCellValueExplicit([$column, $rowIndex], $value, DataType::TYPE_STRING);
            } else {
                $sheet->setCellValue([$column, $rowIndex], $value);
            }

            $column++;
        }
    }

    protected function toXlsxString(Spreadsheet $spreadsheet): string
    {
        $stream = fopen('php://temp', 'r+');
        (new Xlsx($spreadsheet))->save($stream);
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        return $content;
    }
}
