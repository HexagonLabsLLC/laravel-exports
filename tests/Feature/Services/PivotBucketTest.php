<?php

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Category;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Tag;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;

beforeEach(function () {
    User::insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $userIds = User::orderBy('id')->pluck('id')->toArray();

    // Dates span month, quarter, and ISO-year boundaries; 2024-12-30 is a
    // Monday that falls in ISO week 2025-W01
    Post::insert([
        ['user_id' => $userIds[0], 'title' => 'P1', 'content' => 'c', 'published' => true, 'created_at' => '2025-01-15 10:00:00', 'updated_at' => now()],
        ['user_id' => $userIds[0], 'title' => 'P2', 'content' => 'c', 'published' => true, 'created_at' => '2025-02-20 10:00:00', 'updated_at' => now()],
        ['user_id' => $userIds[1], 'title' => 'P3', 'content' => 'c', 'published' => true, 'created_at' => '2025-07-04 10:00:00', 'updated_at' => now()],
        ['user_id' => $userIds[0], 'title' => 'P4', 'content' => 'c', 'published' => true, 'created_at' => '2024-12-30 10:00:00', 'updated_at' => now()],
    ]);
    $postIds = Post::orderBy('id')->pluck('id')->toArray();

    Category::insert([
        ['name' => 'Tech', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Life', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $categoryIds = Category::orderBy('id')->pluck('id')->toArray();

    Tag::insert([
        ['post_id' => $postIds[0], 'category_id' => $categoryIds[0], 'value' => '100', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[1], 'category_id' => $categoryIds[0], 'value' => '40', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[1], 'category_id' => $categoryIds[1], 'value' => '10', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[2], 'category_id' => $categoryIds[1], 'value' => '30', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[3], 'category_id' => $categoryIds[0], 'value' => '5', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->postExportModel = ExportModel::create(['title' => 'Post Export', 'model' => Post::class]);
    $this->service = app(DynamicExportService::class);
});

function makeBucketLayout(array $configOverrides): ExportLayout
{
    return ExportLayout::create([
        'export_model_id' => test()->postExportModel->id,
        'name' => 'Bucket Pivot',
        'is_pivot' => true,
        'pivot_config' => array_merge([
            'pivot_relation' => 'tags.category.name',
            'pivot_column' => 'name',
            'value_relation' => 'tags',
            'value_column' => 'value',
            'aggregation' => 'sum',
            'output_format' => 'flat',
        ], $configOverrides),
    ]);
}

it('mixes a relation path with a per-entry month bucket', function () {
    $layout = makeBucketLayout([
        'group_by' => [
            'user.name',
            ['path' => 'created_at', 'format' => 'month', 'header' => 'Month'],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(4);

    foreach ($result as $row) {
        expect(array_keys($row))->toBe(['User name', 'Month', 'Life', 'Tech', 'Total']);
    }

    $rows = collect($result)->keyBy(fn ($row) => $row['User name'].'|'.$row['Month']);

    expect($rows['Jane Smith|2025-07']['Life'])->toBe('30.00')
        ->and($rows['Jane Smith|2025-07']['Total'])->toBe('30.00')
        ->and($rows['John Doe|2024-12']['Tech'])->toBe('5.00')
        ->and($rows['John Doe|2025-01']['Tech'])->toBe('100.00')
        ->and($rows['John Doe|2025-02']['Tech'])->toBe('40.00')
        ->and($rows['John Doe|2025-02']['Life'])->toBe('10.00')
        ->and($rows['John Doe|2025-02']['Total'])->toBe('50.00');
});

it('buckets by quarter, year, and day', function ($format, $expected) {
    $layout = makeBucketLayout([
        'group_by' => [['path' => 'created_at', 'format' => $format, 'header' => 'Bucket']],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    $totals = collect($result)->pluck('Total', 'Bucket')->toArray();

    expect($totals)->toBe($expected);
})->with([
    'quarter' => ['quarter', ['2024-Q4' => '5.00', '2025-Q1' => '150.00', '2025-Q3' => '30.00']],
    'year' => ['year', ['2024' => '5.00', '2025' => '180.00']],
    'day' => ['day', ['2024-12-30' => '5.00', '2025-01-15' => '100.00', '2025-02-20' => '50.00', '2025-07-04' => '30.00']],
]);

it('keeps the global week_year format working on string entries across year boundaries', function () {
    $layout = makeBucketLayout([
        'group_by' => ['created_at'],
        'group_by_format' => 'week_year',
        'group_by_headers' => ['Week'],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    $totals = collect($result)->pluck('Total', 'Week')->toArray();

    // 2024-12-30 is a Monday belonging to ISO week 2025-W01
    expect($totals)->toBe([
        '2025-W01' => '5.00',
        '2025-W03' => '100.00',
        '2025-W08' => '50.00',
        '2025-W27' => '30.00',
    ]);
});

it('buckets sub_group_by entries independently of the global format', function () {
    $layout = makeBucketLayout([
        'group_by' => ['user.name'],
        'sub_group_by' => [['path' => 'created_at', 'format' => 'year', 'header' => 'Year']],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    $rows = collect($result)->keyBy(fn ($row) => $row['User name'].'|'.$row['Year']);

    expect($result)->toHaveCount(3)
        ->and($rows['Jane Smith|2025']['Total'])->toBe('30.00')
        ->and($rows['John Doe|2024']['Total'])->toBe('5.00')
        ->and($rows['John Doe|2025']['Total'])->toBe('150.00');
});

it('prefers entry headers over indexed custom headers and defaults', function () {
    $layout = makeBucketLayout([
        'group_by' => [
            'user.name',
            ['path' => 'created_at', 'format' => 'year'],
            ['path' => 'created_at', 'format' => 'month', 'header' => 'Month'],
        ],
        'group_by_headers' => ['Author', 'Custom Year'],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    // Author from group_by_headers[0], Custom Year from group_by_headers[1]
    // (entry has no header), Month from the entry itself
    expect(array_keys($result[0]))->toBe(['Author', 'Custom Year', 'Month', 'Life', 'Tech', 'Total']);
});

it('throws for sunday week starts off mysql', function () {
    $layout = makeBucketLayout([
        'group_by' => [['path' => 'created_at', 'format' => 'week', 'week_start' => 'sunday']],
    ]);

    $this->service->executeExport($layout->id);
})->throws(RuntimeException::class, 'Sunday-start weeks are only supported on mysql')
    ->skip(fn () => getenv('DB_DRIVER') === 'mysql', 'sunday weeks are supported on mysql');

it('throws for unknown buckets and entries without a path', function () {
    $layout = makeBucketLayout([
        'group_by' => [['path' => 'created_at', 'format' => 'fortnight']],
    ]);

    expect(fn () => $this->service->executeExport($layout->id))
        ->toThrow(RuntimeException::class, "Unsupported date bucket 'fortnight'");

    $layout = makeBucketLayout([
        'group_by' => [['format' => 'month']],
    ]);

    expect(fn () => $this->service->executeExport($layout->id))
        ->toThrow(RuntimeException::class, 'missing its path');
});
