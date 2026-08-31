<?php

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Services\TransformationFunctions;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Category;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Tag;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;

beforeEach(function () {
    User::insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $userIds = User::orderBy('id')->pluck('id')->toArray();

    Post::insert([
        ['user_id' => $userIds[0], 'title' => 'First Post', 'content' => 'Content 1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $userIds[0], 'title' => 'Second Post', 'content' => 'Content 2', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $userIds[1], 'title' => 'Third Post', 'content' => 'Content 3', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $postIds = Post::orderBy('id')->pluck('id')->toArray();

    Category::insert([
        ['name' => 'Technology', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Lifestyle', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Business', 'created_at' => now(), 'updated_at' => now()],
    ]);
    $categoryIds = Category::orderBy('id')->pluck('id')->toArray();

    Tag::insert([
        ['post_id' => $postIds[0], 'category_id' => $categoryIds[0], 'value' => '120', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[0], 'category_id' => $categoryIds[1], 'value' => '50', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[1], 'category_id' => $categoryIds[0], 'value' => '75', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[1], 'category_id' => $categoryIds[2], 'value' => '200', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[2], 'category_id' => $categoryIds[1], 'value' => '30', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->userExportModel = ExportModel::create(['title' => 'User Export', 'model' => User::class]);
    $this->postExportModel = ExportModel::create(['title' => 'Post Export', 'model' => Post::class]);
    $this->tagExportModel = ExportModel::create(['title' => 'Tag Export', 'model' => Tag::class]);

    $this->nameRelation = ExportModelRelation::create([
        'export_model_id' => $this->userExportModel->id,
        'title' => 'name',
        'relation' => 'name',
        'is_column' => true,
    ]);

    $this->tagsRelation = ExportModelRelation::create([
        'export_model_id' => $this->postExportModel->id,
        'title' => 'tags',
        'relation' => 'tags',
        'is_collection' => true,
        'related_model_id' => $this->tagExportModel->id,
    ]);

    $this->categoryRelation = ExportModelRelation::create([
        'export_model_id' => $this->tagExportModel->id,
        'title' => 'category',
        'relation' => 'category',
    ]);

    $this->service = app(DynamicExportService::class);
});

it('applies transformation functions with configured parameters', function () {
    $function = ExportFunction::create([
        'name' => 'Truncate',
        'callable' => TransformationFunctions::class.'::truncate',
        'parameter_count' => 3,
        'value_parameter_index' => 0,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Function Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'export_function_id' => $function->id,
        'export_function_values' => [null, 4, ''],
        'title' => 'Short Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0]['Short Name'])->toBe('John');
});

it('decodes static array filter values stored as json strings', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Static In Filter',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'operator' => 'in',
        'value' => '["John Doe","Bob Johnson"]',
        'value_type' => 'array',
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(2)
        ->and(collect($result)->pluck('Name')->all())->toContain('John Doe', 'Bob Johnson');
});

it('keeps rows rectangular when omit_on_empty columns are empty', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Rectangular Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Nickname',
        'value_path' => 'nickname',
        'omit_on_empty' => true,
        'position' => 2,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    foreach ($result as $row) {
        expect($row)->toHaveKeys(['Name', 'Nickname'])
            ->and($row['Nickname'])->toBe('');
    }
});

it('honors the operator in relation filter configs on collections', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Not Technology Export',
    ]);

    $filter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->categoryRelation->id,
        'operator' => 'relation',
        'value' => '["tags","category.name","!=","Technology"]',
        'value_type' => 'array',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->tagsRelation->id,
        'export_filter_id' => $filter->id,
        'title' => 'Non Tech Value',
        'value_path' => 'tags.value',
        'default' => '0',
        'position' => 1,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(3)
        ->and($result[0]['Non Tech Value'])->toBe('50')
        ->and($result[1]['Non Tech Value'])->toBe('200')
        ->and($result[2]['Non Tech Value'])->toBe('30');
});

it('executes pivot exports with a joined value relation', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Pivot Export',
        'title' => 'Tag Values by Category',
        'is_pivot' => true,
        'pivot_config' => [
            'group_by' => ['user.name'],
            'pivot_relation' => 'tags.category.name',
            'pivot_column' => 'name',
            'value_relation' => 'tags',
            'value_column' => 'value',
            'aggregation' => 'sum',
            'output_format' => 'flat',
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(2);

    $rows = collect($result)->keyBy('User name');

    expect($rows['John Doe']['Technology'])->toBe('195.00')
        ->and($rows['John Doe']['Lifestyle'])->toBe('50.00')
        ->and($rows['John Doe']['Business'])->toBe('200.00')
        ->and($rows['John Doe']['Total'])->toBe('445.00')
        ->and($rows['Jane Smith']['Lifestyle'])->toBe('30.00')
        ->and($rows['Jane Smith']['Total'])->toBe('30.00');
});

it('reports the export model title in json meta', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Json Meta Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    $decoded = json_decode($this->service->exportTo($layout->id, 'json'), true);

    expect($decoded['meta']['model'])->toBe('User Export');
});

it('neutralizes formula injection in csv output', function () {
    User::insert([
        ['name' => '=2+2', 'email' => 'evil@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Injection Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    $csv = $this->service->exportTo($layout->id, 'csv');

    expect($csv)->toContain("'=2+2")
        ->and($csv)->not->toContain("\n=2+2");
});
