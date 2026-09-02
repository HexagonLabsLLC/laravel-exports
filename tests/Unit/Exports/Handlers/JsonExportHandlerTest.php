<?php

use HexagonLabsLLC\LaravelExports\Exports\Handlers\JsonExportHandler;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Support\Collection;

it('exports data as JSON', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => false, 'wrap_data' => false]);

    $data = Collection::make([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25],
    ]);

    $result = $handler->export($data);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveCount(2)
        ->and($decoded[0])->toEqual(['name' => 'John Doe', 'email' => 'john@example.com', 'age' => 30])
        ->and($decoded[1])->toEqual(['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => 25]);
});

it('handles empty data', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => false, 'wrap_data' => false]);

    $result = $handler->export(Collection::make([]));
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toBeEmpty();
});

it('handles special characters', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => false, 'wrap_data' => false]);

    $data = Collection::make([
        ['name' => 'John "Doe"', 'description' => 'Line\nbreak'],
    ]);

    $result = $handler->export($data);
    $decoded = json_decode($result, true);

    expect($decoded[0]['name'])->toBe('John "Doe"')
        ->and($decoded[0]['description'])->toBe('Line\nbreak');
});

it('handles null values', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => false, 'wrap_data' => false]);

    $data = Collection::make([
        ['name' => 'John Doe', 'email' => null, 'age' => 30],
    ]);

    $result = $handler->export($data);
    $decoded = json_decode($result, true);

    expect($decoded[0]['email'])->toBeNull();
});

it('produces valid JSON with pretty print option', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['pretty' => true, 'include_meta' => false, 'wrap_data' => false]);

    $data = Collection::make([
        ['name' => 'John Doe', 'email' => 'john@example.com'],
    ]);

    $result = $handler->export($data);

    expect($result)->toContain("{\n")
        ->and($result)->toContain('    "name": "John Doe"')
        ->and(json_decode($result))->not->toBeNull();
});

it('handles nested data structures', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => false, 'wrap_data' => false]);

    $data = Collection::make([
        [
            'name' => 'John Doe',
            'contact' => [
                'email' => 'john@example.com',
                'phone' => '123-456-7890',
            ],
            'tags' => ['developer', 'senior'],
        ],
    ]);

    $result = $handler->export($data);
    $decoded = json_decode($result, true);

    expect($decoded[0]['contact']['email'])->toBe('john@example.com')
        ->and($decoded[0]['tags'])->toContain('developer', 'senior');
});

it('handles various data types', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => false, 'wrap_data' => false]);

    $data = Collection::make([
        [
            'string' => 'text',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'null' => null,
            'array' => [1, 2, 3],
            'object' => ['key' => 'value'],
        ],
    ]);

    $result = $handler->export($data);
    $decoded = json_decode($result, true);

    expect($decoded[0]['string'])->toBe('text')
        ->and($decoded[0]['integer'])->toBe(42)
        ->and($decoded[0]['float'])->toBe(3.14)
        ->and($decoded[0]['boolean_true'])->toBeTrue()
        ->and($decoded[0]['boolean_false'])->toBeFalse()
        ->and($decoded[0]['null'])->toBeNull()
        ->and($decoded[0]['array'])->toEqual([1, 2, 3])
        ->and($decoded[0]['object'])->toEqual(['key' => 'value']);
});

it('keeps meta when data wrapping is disabled', function () {
    $layout = new ExportLayout(['title' => 'Test Layout']);
    $handler = new JsonExportHandler($layout, ['include_meta' => true, 'wrap_data' => false]);

    $decoded = json_decode($handler->export(Collection::make([['name' => 'John Doe']])), true);

    expect(array_keys($decoded))->toBe(['meta', 'data']);
});
