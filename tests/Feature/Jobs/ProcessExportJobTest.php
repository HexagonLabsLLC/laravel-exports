<?php

use HexagonLabsLLC\LaravelExports\Jobs\ProcessExportJob;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    config()->set('cache.default', 'array');
    Storage::fake('local');

    User::insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);

    Post::insert([
        ['user_id' => User::first()->id, 'title' => 'First Post', 'content' => 'C1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => User::first()->id, 'title' => 'Second Post', 'content' => 'C2', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => User::first()->id, 'title' => 'Third Post', 'content' => 'C3', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $postExportModel = ExportModel::create(['title' => 'Post Export', 'model' => Post::class]);

    $this->layout = ExportLayout::create([
        'export_model_id' => $postExportModel->id,
        'name' => 'queued',
        'title' => 'Queued Posts',
        'column_definitions' => ['Title' => 'title'],
    ]);
});

it('processes a queued csv export across chunks', function () {
    $exportId = (string)Str::uuid();

    (new ProcessExportJob($exportId, $this->layout->id, 'csv', [], [], 2))->handle();

    $status = ProcessExportJob::getStatus($exportId);

    expect($status['status'])->toBe('completed')
        ->and($status['progress'])->toBe(100)
        ->and($status['row_count'])->toBe(3)
        ->and($status['disk'])->toBe('local')
        ->and($status['filename'])->toMatch('/^queued-posts_\d{4}-\d{2}-\d{2}_\d{6}\.csv$/')
        ->and($status['path'])->toBe('exports/'.$status['filename'])
        ->and(ProcessExportJob::isSuccessful($exportId))->toBeTrue()
        ->and(ProcessExportJob::getFilePath($exportId))->toBe($status['path']);

    Storage::disk('local')->assertExists($status['path']);

    expect(Storage::disk('local')->get($status['path']))
        ->toBe("Title\n\"First Post\"\n\"Second Post\"\n\"Third Post\"\n");
});

it('writes parseable json from a queued export', function () {
    $exportId = (string)Str::uuid();

    (new ProcessExportJob($exportId, $this->layout->id, 'json', [], [], 2))->handle();

    $status = ProcessExportJob::getStatus($exportId);
    $decoded = json_decode(Storage::disk('local')->get($status['path']), true);

    expect($decoded)->not->toBeNull()
        ->and($decoded)->toHaveCount(3)
        ->and($decoded[0]['Title'])->toBe('First Post')
        ->and($decoded[2]['Title'])->toBe('Third Post');
});

it('fails queued exports for unsupported formats without writing files', function () {
    $exportId = (string)Str::uuid();

    expect(fn () => (new ProcessExportJob($exportId, $this->layout->id, 'xlsx'))->handle())
        ->toThrow(InvalidArgumentException::class, 'Queued exports do not support format: xlsx');

    $status = ProcessExportJob::getStatus($exportId);

    expect($status['status'])->toBe('failed')
        ->and($status['error'])->toContain('xlsx')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('completes queued exports with no matching rows without writing files', function () {
    $this->layout->update([
        'filter_definitions' => [
            ['path' => 'title', 'operator' => '=', 'value' => 'No Such Post'],
        ],
    ]);

    $exportId = (string)Str::uuid();

    (new ProcessExportJob($exportId, $this->layout->id, 'csv'))->handle();

    $status = ProcessExportJob::getStatus($exportId);

    expect($status['status'])->toBe('completed')
        ->and($status['row_count'])->toBe(0)
        ->and($status['message'])->toBe('No records to export')
        ->and($status)->not->toHaveKey('path')
        ->and(ProcessExportJob::getFilePath($exportId))->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
