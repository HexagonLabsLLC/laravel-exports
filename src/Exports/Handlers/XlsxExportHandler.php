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
            'sheet_by' => null,
            'sheet_title' => null,
        ];
    }

    public function export(Collection $data): string
    {
        $spreadsheet = new Spreadsheet;
        $usedTitles = [];
        $isFirstSheet = true;

        foreach ($this->toSheets($data) as $title => $rows) {
            $sheet = $isFirstSheet ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($this->sanitizeSheetTitle((string)$title, $usedTitles));
            $isFirstSheet = false;

            $rows = collect($rows);
            $rowIndex = 1;

            if ($this->options['include_headers'] && $rows->isNotEmpty()) {
                $this->writeRow($sheet, array_keys($rows->first()), $rowIndex++);
            }

            foreach ($rows as $row) {
                $this->writeRow($sheet, array_values($row), $rowIndex++);
            }
        }

        return $this->toXlsxString($spreadsheet);
    }

    /**
     * Normalize export data into [sheet title => rows].
     *
     * Three shapes are supported:
     * - a string-keyed collection of row sets exports one sheet per key
     *   (e.g. collect(['Users' => $userRows, 'Orders' => $orderRows]))
     * - the sheet_by option splits a flat row collection into sheets by
     *   that column's value
     * - anything else exports a single sheet
     */
    protected function toSheets(Collection $data): array
    {
        if ($data->isNotEmpty()
            && $data->keys()->every(fn ($key) => is_string($key))
            && $data->every(fn ($rows) => is_iterable($rows))) {
            return $data->all();
        }

        if ($this->options['sheet_by']) {
            $sheetBy = $this->options['sheet_by'];

            return $data->groupBy(fn ($row) => (string)($row[$sheetBy] ?? ''))->all();
        }

        return [$this->defaultSheetTitle() => $data];
    }

    protected function defaultSheetTitle(): string
    {
        return $this->options['sheet_title'] ?: $this->layout->title ?: $this->layout->name ?: 'Export';
    }

    /**
     * Excel sheet titles cannot contain []:*?/\, are capped at 31 characters,
     * must be non-blank, and must be unique case-insensitively.
     */
    protected function sanitizeSheetTitle(string $title, array &$usedTitles): string
    {
        $title = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $title)) ?: 'Sheet';
        $title = mb_substr($title, 0, 31);

        $candidate = $title;
        for ($i = 2; isset($usedTitles[mb_strtolower($candidate)]); $i++) {
            $suffix = ' ('.$i.')';
            $candidate = mb_substr($title, 0, 31 - mb_strlen($suffix)).$suffix;
        }

        $usedTitles[mb_strtolower($candidate)] = true;

        return $candidate;
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
            $sheetBy = $this->options['sheet_by'];
            $usedTitles = [];
            $sheets = [];

            $dataCallback(function (Collection $chunk) use ($spreadsheet, $sheetBy, &$sheets, &$usedTitles) {
                foreach ($chunk as $row) {
                    $key = $sheetBy ? (string)($row[$sheetBy] ?? '') : $this->defaultSheetTitle();

                    if (!isset($sheets[$key])) {
                        $sheet = $sheets === [] ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
                        $sheet->setTitle($this->sanitizeSheetTitle($key, $usedTitles));

                        $rowIndex = 1;
                        if ($this->options['include_headers']) {
                            $this->writeRow($sheet, array_keys($row), $rowIndex++);
                        }

                        $sheets[$key] = ['sheet' => $sheet, 'row' => $rowIndex];
                    }

                    $this->writeRow($sheets[$key]['sheet'], array_values($row), $sheets[$key]['row']++);
                }
            });

            if ($sheets === []) {
                $spreadsheet->getActiveSheet()->setTitle(
                    $this->sanitizeSheetTitle($this->defaultSheetTitle(), $usedTitles)
                );
            }

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
