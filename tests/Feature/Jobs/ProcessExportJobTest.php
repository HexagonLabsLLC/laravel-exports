<?php

use HexagonLabsLLC\LaravelExports\Exports\ExportFactory;
use HexagonLabsLLC\LaravelExports\Exports\Handlers\ExportHandler;
use HexagonLabsLLC\LaravelExports\Jobs\ProcessExportJob;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Custom handler registered under a format name that differs from its
 * extension, to prove queued exports run any registered handler.
 */
class QueuedTextHandler extends ExportHandler
{
    public function export(Collection $data): mixed
    {
        return $data->map(fn ($row) => implode('|', $row))->implode("\n");
    }

    public function download(mixed $export, string $filename): Response
    {
        return response($export);
    }

    public function store(mixed $export, string $path): bool
    {
        return Storage::put($path, $export);
    }

    public function stream(callable $dataCallback, string $filename): Response|StreamedResponse
    {
        return response()->stream(fn () => $dataCallback(fn () => null));
    }

    public function getExtension(): string
    {
        return 'txt';
    }

    public function getMimeType(): string
    {
        return 'text/plain';
    }
}

class QueuedArrayHandler extends QueuedTextHandler
{
    public function export(Collection $data): mixed
    {
        return $data->all();
    }
}

function loadQueuedWorkbook(string $path): Spreadsheet
{
    $tempPath = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tempPath, Storage::disk('local')->get($path));
    $spreadsheet = IOFactory::load($tempPath);
    unlink($tempPath);

    return $spreadsheet;
}

beforeEach(function () {
    config()->set('cache.default', 'array');
    Storage::fake('local');

    // ExportFactory keeps its handler map in a static property
    $this->registeredHandlers = (new ReflectionProperty(ExportFactory::class, 'handlers'))->getValue();

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

afterEach(function () {
    (new ReflectionProperty(ExportFactory::class, 'handlers'))->setValue(null, $this->registeredHandlers);
});

it('dispatches queued exports onto the configured queue', function () {
    Queue::fake();

    $exportId = app(DynamicExportService::class)->queueExport(
        $this->layout,
        'xlsx',
        ['published' => true],
        ['sheet_by' => 'Title']
    );

    Queue::assertPushedOn('exports', ProcessExportJob::class, function (ProcessExportJob $job) use ($exportId) {
        return $job->exportId === $exportId
            && $job->layoutId === $this->layout->id
            && $job->format === 'xlsx'
            && $job->requestData === ['published' => true]
            && $job->options === ['sheet_by' => 'Title']
            && $job->chunkSize === 1000
            && $job->disk === 'local'
            && $job->path === 'exports';
    });
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

it('processes a queued xlsx export across chunks', function () {
    $exportId = (string)Str::uuid();

    (new ProcessExportJob($exportId, $this->layout->id, 'xlsx', [], [], 2))->handle();

    $status = ProcessExportJob::getStatus($exportId);

    expect($status['status'])->toBe('completed')
        ->and($status['progress'])->toBe(100)
        ->and($status['row_count'])->toBe(3)
        ->and($status['total_rows'])->toBe(3)
        ->and($status['filename'])->toMatch('/^queued-posts_\d{4}-\d{2}-\d{2}_\d{6}\.xlsx$/');

    Storage::disk('local')->assertExists($status['path']);

    $sheet = loadQueuedWorkbook($status['path'])->getActiveSheet();

    expect($sheet->getTitle())->toBe('Queued Posts')
        ->and($sheet->getCell('A1')->getValue())->toBe('Title')
        ->and($sheet->getCell('A2')->getValue())->toBe('First Post')
        ->and($sheet->getCell('A3')->getValue())->toBe('Second Post')
        ->and($sheet->getCell('A4')->getValue())->toBe('Third Post')
        ->and($sheet->getCell('A5')->getValue())->toBeNull();
});

it('passes handler options through to queued exports', function () {
    $exportId = (string)Str::uuid();

    (new ProcessExportJob($exportId, $this->layout->id, 'xlsx', [], ['sheet_by' => 'Title'], 2))->handle();

    $spreadsheet = loadQueuedWorkbook(ProcessExportJob::getFilePath($exportId));

    expect($spreadsheet->getSheetNames())->toBe(['First Post', 'Second Post', 'Third Post'])
        ->and($spreadsheet->getSheetByName('Second Post')->getCell('A2')->getValue())->toBe('Second Post');
});

it('queues any format registered with the factory using the handler extension', function () {
    ExportFactory::register('text', QueuedTextHandler::class);

    $exportId = (string)Str::uuid();

    (new ProcessExportJob($exportId, $this->layout->id, 'text', [], [], 2))->handle();

    $status = ProcessExportJob::getStatus($exportId);

    expect($status['status'])->toBe('completed')
        ->and($status['format'])->toBe('text')
        ->and($status['filename'])->toEndWith('.txt')
        ->and(Storage::disk('local')->get($status['path']))
        ->toBe("First Post\nSecond Post\nThird Post");
});

it('fails queued exports when a handler returns something other than a string', function () {
    ExportFactory::register('rows', QueuedArrayHandler::class);

    $exportId = (string)Str::uuid();

    expect(fn () => (new ProcessExportJob($exportId, $this->layout->id, 'rows'))->handle())
        ->toThrow(RuntimeException::class, "Queued exports need a string from the rows handler's export() method.");

    expect(ProcessExportJob::getStatus($exportId)['status'])->toBe('failed')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('fails queued exports for unregistered formats without writing files', function () {
    $exportId = (string)Str::uuid();

    expect(fn () => (new ProcessExportJob($exportId, $this->layout->id, 'pdf'))->handle())
        ->toThrow(InvalidArgumentException::class, 'Unsupported export format: pdf');

    $status = ProcessExportJob::getStatus($exportId);

    expect($status['status'])->toBe('failed')
        ->and($status['error'])->toContain('pdf')
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
