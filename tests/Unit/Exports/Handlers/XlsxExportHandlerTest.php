<?php

use HexagonLabsLLC\LaravelExports\Exports\Handlers\XlsxExportHandler;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports without headers when disabled', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new XlsxExportHandler($layout, ['include_headers' => false]);

    $binary = $handler->export(Collection::make([
        ['Name' => 'John Doe'],
        ['Name' => 'Jane Smith'],
    ]));

    $tmp = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tmp, $binary);
    $sheet = IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    expect($sheet->getCell('A1')->getValue())->toBe('John Doe')
        ->and($sheet->getCell('A2')->getValue())->toBe('Jane Smith');
});
