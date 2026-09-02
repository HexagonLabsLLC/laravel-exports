<?php

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    User::insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $userIds = User::orderBy('id')->pluck('id')->toArray();

    Post::insert([
        ['user_id' => $userIds[0], 'title' => 'First Post', 'content' => 'C1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $userIds[0], 'title' => 'Second Post', 'content' => 'C2', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $userIds[1], 'title' => 'Third Post', 'content' => 'C3', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->userExportModel = ExportModel::create(['title' => 'User Export', 'model' => User::class]);
    $this->postExportModel = ExportModel::create(['title' => 'Post Export', 'model' => Post::class]);
    $this->service = app(DynamicExportService::class);
});

function captureStream($response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean();
}

it('streams valid json across chunk boundaries', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'json_stream',
        'column_definitions' => ['Name' => 'name'],
    ]);

    $response = $this->service->streamAs($layout->id, 'json', 'users', [], [], 2);
    $decoded = json_decode(captureStream($response), true);

    expect($decoded)->not->toBeNull()
        ->and(array_keys($decoded))->toBe(['meta', 'data'])
        ->and($decoded['meta']['layout'])->toBe('json_stream')
        ->and($decoded['meta']['model'])->toBe('User Export')
        ->and($decoded['data'])->toHaveCount(3)
        ->and($decoded['data'][0]['Name'])->toBe('John Doe')
        ->and($decoded['data'][2]['Name'])->toBe('Bob Johnson')
        ->and($response->headers->get('Content-Disposition'))->toContain('users.json');
});

it('streams a bare json array when meta and wrapping are disabled', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'bare_json_stream',
        'column_definitions' => ['Name' => 'name'],
    ]);

    $response = $this->service->streamAs($layout->id, 'json', 'users', [], [
        'wrap_data' => false,
        'include_meta' => false,
    ], 2);
    $decoded = json_decode(captureStream($response), true);

    expect($decoded)->toHaveCount(3)
        ->and(array_is_list($decoded))->toBeTrue()
        ->and($decoded[2]['Name'])->toBe('Bob Johnson');
});

it('streams csv with the header exactly once and sanitized formulas', function () {
    User::insert([
        ['name' => '=2+2', 'email' => 'evil@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'csv_stream',
        'column_definitions' => ['Name' => 'name'],
    ]);

    $response = $this->service->streamAs($layout->id, 'csv', 'users', [], [], 2);
    $csv = captureStream($response);

    expect($csv)->toBe("Name\n\"John Doe\"\n\"Jane Smith\"\n\"Bob Johnson\"\n'=2+2\n")
        ->and(substr_count($csv, "Name\n"))->toBe(1);

    $bomResponse = $this->service->streamAs($layout->id, 'csv', 'users', [], ['bom' => true], 2);
    $bom = captureStream($bomResponse);

    expect(substr($bom, 0, 8))->toBe("\xEF\xBB\xBFName\n");
});

it('streams xlsx sheets routed across chunk boundaries', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Sheeted Export',
        'column_definitions' => [
            'Author' => 'user.name',
            'Title' => 'title',
        ],
    ]);

    $response = $this->service->streamAs($layout->id, 'xlsx', 'sheets', [], ['sheet_by' => 'Author'], 2);
    $binary = captureStream($response);

    $tmp = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tmp, $binary);
    $spreadsheet = IOFactory::load($tmp);
    unlink($tmp);

    expect($spreadsheet->getSheetNames())->toBe(['John Doe', 'Jane Smith']);

    $john = $spreadsheet->getSheetByName('John Doe');
    $jane = $spreadsheet->getSheetByName('Jane Smith');

    expect($john->getCell('A1')->getValue())->toBe('Author')
        ->and($john->getCell('B2')->getValue())->toBe('First Post')
        ->and($john->getCell('B3')->getValue())->toBe('Second Post')
        ->and($john->getCell('B4')->getValue())->toBeNull()
        ->and($jane->getCell('B1')->getValue())->toBe('Title')
        ->and($jane->getCell('B2')->getValue())->toBe('Third Post');
});

it('streams an empty xlsx export as a workbook titled from the layout', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Sheeted Export',
        'column_definitions' => ['Title' => 'title'],
        'filter_definitions' => [
            ['path' => 'title', 'operator' => '=', 'value' => 'No Such Post'],
        ],
    ]);

    $response = $this->service->streamAs($layout->id, 'xlsx', 'empty', [], [], 2);
    $binary = captureStream($response);

    $tmp = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tmp, $binary);
    $spreadsheet = IOFactory::load($tmp);
    unlink($tmp);

    expect($spreadsheet->getSheetNames())->toBe(['Sheeted Export']);
});
