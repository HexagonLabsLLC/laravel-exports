<?php

use HexagonLabsLLC\LaravelExports\Exports\Handlers\CsvExportHandler;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Support\Collection;

it('exports data as CSV with headers', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $data = Collection::make([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25],
    ]);

    $result = $handler->export($data);

    expect($result)->toBeString()
        ->and($result)->toContain('name,email,age')
        ->and($result)->toContain('"John Doe",john@example.com,30')
        ->and($result)->toContain('"Jane Smith",jane@example.com,25');
});

it('handles empty data', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $result = $handler->export(Collection::make([]));

    expect($result)->toBe('');
});

it('handles special characters in CSV', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $data = Collection::make([
        ['name' => 'John, Doe', 'description' => 'He said "Hello"'],
        ['name' => 'Jane
Smith', 'description' => 'Line break test'],
    ]);

    $result = $handler->export($data);

    expect($result)->toContain('"John, Doe"')
        ->and($result)->toContain('"He said ""Hello"""')
        ->and($result)->toContain('"Jane
Smith"');
});

it('handles null values', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $data = Collection::make([
        ['name' => 'John Doe', 'email' => null, 'age' => 30],
    ]);

    $result = $handler->export($data);

    expect($result)->toContain('"John Doe",,30');
});

it('preserves column order', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $data = Collection::make([
        ['age' => 30, 'name' => 'John Doe', 'email' => 'john@example.com'],
        ['email' => 'jane@example.com', 'age' => 25, 'name' => 'Jane Smith'],
    ]);

    $result = $handler->export($data);
    $lines = explode("\n", trim($result));

    expect($lines[0])->toBe('age,name,email');
});

it('handles numeric values correctly', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $data = Collection::make([
        ['id' => 1, 'price' => 99.99, 'quantity' => 10],
        ['id' => 2, 'price' => 149.50, 'quantity' => 5],
    ]);

    $result = $handler->export($data);

    expect($result)->toContain('1,99.99,10')
        ->and($result)->toContain('2,149.5,5');
});

it('handles boolean values', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $data = Collection::make([
        ['name' => 'John', 'active' => true, 'verified' => false],
    ]);

    $result = $handler->export($data);

    expect($result)->toContain('John,1,');
});

it('writes the bom before content and composes with disabled headers', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout, ['bom' => true, 'include_headers' => false]);

    $result = $handler->export(Collection::make([['name' => 'John Doe']]));

    expect($result)->toBe("\xEF\xBB\xBF\"John Doe\"\n");
});

it('lets formula escaping be disabled', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout, ['escape_formulas' => false]);

    $result = $handler->export(Collection::make([['v' => '=SUM(A1)']]));

    expect($result)->toContain('=SUM(A1)')
        ->and($result)->not->toContain("'=SUM(A1)");
});

it('escapes leading dashes by default', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new CsvExportHandler($layout);

    $result = $handler->export(Collection::make([['v' => '-5 apples']]));

    expect($result)->toContain("'-5 apples");
});
