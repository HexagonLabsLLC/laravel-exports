<?php

use HexagonLabsLLC\LaravelExports\Builders\ExportLayoutBuilder;
use HexagonLabsLLC\LaravelExports\Exports\ExportFactory;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Services\TransformationFunctions;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Category;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Tag;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

it('renders grouped pivot output with header rows and custom headers', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Grouped Pivot Export',
        'is_pivot' => true,
        'pivot_config' => [
            'group_by' => ['user.name'],
            'sub_group_by' => ['title'],
            'pivot_relation' => 'tags.category.name',
            'pivot_column' => 'name',
            'value_relation' => 'tags',
            'value_column' => 'value',
            'aggregation' => 'sum',
            'output_format' => 'grouped',
            'group_by_headers' => ['Author'],
            'sub_group_by_headers' => ['Post'],
            'total_header' => 'Sum',
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    // Two group header rows (Jane, John) plus three sub-rows
    expect($result)->toHaveCount(5);

    foreach ($result as $row) {
        expect(array_keys($row))->toBe(['Author', 'Post', 'Business', 'Lifestyle', 'Technology', 'Sum']);
    }

    // Group header rows carry only the group value
    expect($result[0]['Author'])->toBe('Jane Smith')
        ->and($result[0]['Post'])->toBe('')
        ->and($result[0]['Sum'])->toBe('');

    // Sub-rows blank the group column and carry the aggregated values
    expect($result[1]['Author'])->toBe('')
        ->and($result[1]['Post'])->toBe('Third Post')
        ->and($result[1]['Lifestyle'])->toBe('30.00')
        ->and($result[1]['Sum'])->toBe('30.00');

    expect($result[2]['Author'])->toBe('John Doe')
        ->and($result[3]['Post'])->toBe('First Post')
        ->and($result[3]['Technology'])->toBe('120.00')
        ->and($result[3]['Lifestyle'])->toBe('50.00')
        ->and($result[3]['Sum'])->toBe('170.00')
        ->and($result[4]['Post'])->toBe('Second Post')
        ->and($result[4]['Business'])->toBe('200.00')
        ->and($result[4]['Technology'])->toBe('75.00')
        ->and($result[4]['Sum'])->toBe('275.00');
});

it('aggregates values plucked from a collection relation and formats the result', function () {
    $function = ExportFunction::create([
        'name' => 'Format Number',
        'callable' => TransformationFunctions::class.'::formatNumber',
        'parameter_count' => 3,
        'value_parameter_index' => 0,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Summed Tags Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->tagsRelation->id,
        'export_function_id' => $function->id,
        'export_function_values' => [null, 2, ','],
        'title' => 'Tag Total',
        'value_path' => 'tags.value',
        'aggregator' => 'sum',
        'position' => 1,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0]['Tag Total'])->toBe('170.00')
        ->and($result[1]['Tag Total'])->toBe('275.00')
        ->and($result[2]['Tag Total'])->toBe('30.00');
});

it('aggregates over relation-filtered collection subsets', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Filtered Sum Export',
    ]);

    $filter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->categoryRelation->id,
        'operator' => 'relation',
        'value' => 'Technology',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->tagsRelation->id,
        'export_filter_id' => $filter->id,
        'export_filter_values' => 'Technology',
        'title' => 'Tech Total',
        'value_path' => 'tags.value',
        'aggregator' => 'sum',
        'position' => 1,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0]['Tech Total'])->toBe(120)
        ->and($result[1]['Tech Total'])->toBe(75)
        ->and($result[2]['Tech Total'])->toBe(0);
});

it('treats logical_operator case-insensitively for or filters', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Or Filter Export',
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
        'operator' => '=',
        'value' => 'John Doe',
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'operator' => '=',
        'value' => 'Jane Smith',
        'logical_operator' => 'or',
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(2)
        ->and(collect($result)->pluck('Name')->all())->toContain('John Doe', 'Jane Smith');
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

it('exports xlsx with formula-safe string cells', function () {
    User::insert([
        ['name' => '=2+2', 'email' => 'evil2@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Xlsx Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    $xlsx = $this->service->exportTo($layout->id, 'xlsx');

    expect(substr($xlsx, 0, 2))->toBe('PK');

    $tmp = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tmp, $xlsx);
    $sheet = IOFactory::load($tmp)->getActiveSheet();
    unlink($tmp);

    expect($sheet->getCell('A1')->getValue())->toBe('Name')
        ->and($sheet->getCell('A2')->getValue())->toBe('John Doe')
        ->and($sheet->getCell('A5')->getValue())->toBe('=2+2')
        ->and($sheet->getCell('A5')->getDataType())->toBe(DataType::TYPE_STRING);
});

it('bulk creates columns from an array definition', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Bulk Columns',
    ]);

    $layout->addColumns([
        'Name' => ['value_path' => 'name', 'relation' => 'name'],
        'Email' => 'email',
        ['title' => 'Signup Year', 'value_path' => 'created_at', 'default' => 'unknown'],
    ]);

    $columns = $layout->columns()->get();

    expect($columns)->toHaveCount(3)
        ->and($columns->pluck('title')->all())->toBe(['Name', 'Email', 'Signup Year'])
        ->and($columns->pluck('position')->all())->toBe([1, 2, 3])
        ->and($columns->first()->export_model_relation_id)->toBe($this->nameRelation->id);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0])->toHaveKeys(['Name', 'Email', 'Signup Year'])
        ->and($result[0]['Name'])->toBe('John Doe')
        ->and($result[0]['Email'])->toBe('john@example.com');
});

it('rejects bulk columns referencing unregistered relations', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Bad Bulk Columns',
    ]);

    $layout->addColumns([
        'Broken' => ['value_path' => 'x', 'relation' => 'not_a_relation'],
    ]);
})->throws(InvalidArgumentException::class, "Relation 'not_a_relation' is not registered");

it('builds columns from the layout column_definitions json field', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Defined Columns',
        'column_definitions' => [
            'Title' => 'title',
            'Tag Total' => ['value_path' => 'tags.value', 'relation' => 'tags', 'aggregator' => 'sum'],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(3)
        ->and(array_keys($result[0]))->toBe(['Title', 'Tag Total'])
        ->and($result[0]['Title'])->toBe('First Post')
        ->and($result[0]['Tag Total'])->toBe(170)
        ->and($layout->columns()->count())->toBe(0);
});

it('merges column_definitions with persisted columns by position', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Mixed Columns',
        'column_definitions' => [
            'Source' => ['value_path' => 'source_system', 'default' => 'CRM', 'position' => 1],
        ],
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 2,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(array_keys($result[0]))->toBe(['Source', 'Name'])
        ->and($result[0]['Source'])->toBe('CRM')
        ->and($result[0]['Name'])->toBe('John Doe');
});

it('runs exports from a layout referencing a model class with an empty catalog', function () {
    ExportModelRelation::query()->delete();
    ExportModel::query()->delete();

    $layout = ExportLayout::create([
        'model' => Post::class,
        'name' => 'lazy_layout',
        'column_definitions' => [
            'Title' => 'title',
            'Tag Total' => ['relation' => 'tags', 'value_path' => 'tags.value', 'aggregator' => 'sum', 'default' => '0'],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(3)
        ->and($result[0]['Title'])->toBe('First Post')
        ->and($result[0]['Tag Total'])->toBe(170)
        ->and(ExportModel::where('model', Post::class)->exists())->toBeTrue()
        ->and(ExportModelRelation::where('relation', 'tags')->where('is_column', false)->exists())->toBeTrue();
});

it('throws for model-class layouts in manual sync mode', function () {
    config()->set('laravel-exports.schema_sync', 'manual');
    ExportModelRelation::query()->delete();
    ExportModel::query()->delete();

    $layout = ExportLayout::create([
        'model' => Post::class,
        'name' => 'manual_layout',
        'column_definitions' => ['Title' => 'title'],
    ]);

    (new DynamicExportService)->executeExport($layout->id);
})->throws(RuntimeException::class, 'not registered in the export catalog');

it('throws for layouts with neither a model class nor an export model', function () {
    $layout = ExportLayout::create([
        'name' => 'orphan_layout',
        'column_definitions' => ['Title' => 'title'],
    ]);

    $this->service->executeExport($layout->id);
})->throws(InvalidArgumentException::class, 'neither an export model nor a model class');

it('lazily syncs referenced nested paths instead of ad hoc inserts', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'nested_path_layout',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->tagsRelation->id,
        'title' => 'Category',
        'value_path' => 'tags.category.name',
        'aggregator' => 'first',
        'position' => 1,
    ]);

    config()->set('laravel-exports.schema_sync', 'manual');
    $before = ExportModelRelation::count();
    (new DynamicExportService)->executeExport($layout->id);

    expect(ExportModelRelation::count())->toBe($before);

    config()->set('laravel-exports.schema_sync', 'lazy');
    (new DynamicExportService)->executeExport($layout->id);

    expect(ExportModelRelation::where('relation', 'tags.category')->exists())->toBeTrue();
});

it('applies filter definitions from the layout row', function () {
    ExportModelRelation::query()->delete();
    ExportModel::query()->delete();

    $layout = ExportLayout::create([
        'model' => Post::class,
        'name' => 'filtered_definitions',
        'column_definitions' => ['Title' => 'title'],
        'filter_definitions' => [
            ['path' => 'published', 'operator' => '=', 'value' => true],
            ['path' => 'user.name', 'operator' => '=', 'value' => 'John Doe'],
            ['path' => 'tags', 'operator' => '=', 'value' => '120', 'column' => 'value'],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(1)
        ->and($result[0]['Title'])->toBe('First Post');
});

it('applies required request filters defined on the layout row', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'request_definitions',
        'column_definitions' => ['Title' => 'title'],
        'filter_definitions' => [
            ['path' => 'user.name', 'operator' => '=', 'is_request' => true, 'is_required' => true],
        ],
    ]);

    $result = $this->service->executeExport($layout->id, ['user.name' => 'Jane Smith'])->toArray();

    expect($result)->toHaveCount(1)
        ->and($result[0]['Title'])->toBe('Third Post');

    expect(fn () => $this->service->executeExport($layout->id))
        ->toThrow(Exception::class, 'Required filter');
});

it('applies sort definitions from the layout row', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'sorted_definitions',
        'column_definitions' => ['Title' => 'title'],
        'sort_definitions' => [
            ['path' => 'user', 'sort_column' => 'name'],
            ['path' => 'title', 'direction' => 'desc'],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(collect($result)->pluck('Title')->all())
        ->toBe(['Third Post', 'Second Post', 'First Post']);
});

it('lets an active request filter suppress a static filter on the same column', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Conflict Filters',
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
        'operator' => '=',
        'value' => 'John Doe',
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'operator' => '=',
        'is_request' => true,
    ]);

    $withRequest = $this->service->executeExport($layout->id, ['name' => 'Jane Smith'])->toArray();
    $withoutRequest = $this->service->executeExport($layout->id)->toArray();

    expect($withRequest)->toHaveCount(1)
        ->and($withRequest[0]['Name'])->toBe('Jane Smith')
        ->and($withoutRequest)->toHaveCount(1)
        ->and($withoutRequest[0]['Name'])->toBe('John Doe');
});

it('groups or filters with the preceding filter instead of escaping the scope', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Or Grouping',
        'column_definitions' => ['Title' => 'title'],
        'filter_definitions' => [
            ['path' => 'title', 'operator' => '=', 'value' => 'Second Post'],
            ['path' => 'title', 'operator' => '=', 'value' => 'Third Post', 'logical_operator' => 'or'],
            ['path' => 'published', 'operator' => '=', 'value' => true],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(collect($result)->pluck('Title')->all())->toBe(['Third Post']);
});

it('limits pivot columns to the request param and excludes hidden columns from totals', function () {
    $ids = Category::pluck('id', 'name');

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Filtered Pivot',
        'is_pivot' => true,
        'pivot_config' => [
            'group_by' => ['user.name'],
            'pivot_relation' => 'tags.category.name',
            'pivot_column' => 'name',
            'pivot_filter_param' => 'category_ids',
            'value_relation' => 'tags',
            'value_column' => 'value',
            'aggregation' => 'sum',
            'output_format' => 'flat',
        ],
    ]);

    $result = $this->service->executeExport($layout->id, [
        'category_ids' => $ids['Technology'].','.$ids['Lifestyle'],
    ])->toArray();

    foreach ($result as $row) {
        expect(array_keys($row))->toBe(['User name', 'Lifestyle', 'Technology', 'Total']);
    }

    $rows = collect($result)->keyBy('User name');

    expect($rows['Jane Smith']['Lifestyle'])->toBe('30.00')
        ->and($rows['Jane Smith']['Total'])->toBe('30.00')
        ->and($rows['John Doe']['Lifestyle'])->toBe('50.00')
        ->and($rows['John Doe']['Technology'])->toBe('195.00')
        ->and($rows['John Doe']['Total'])->toBe('245.00');

    $lifestyleOnly = collect($this->service->executeExport($layout->id, [
        'category_ids' => [$ids['Lifestyle']],
    ])->toArray())->keyBy('User name');

    expect(array_keys($lifestyleOnly['John Doe']))->toBe(['User name', 'Lifestyle', 'Total'])
        ->and($lifestyleOnly['John Doe']['Total'])->toBe('50.00');
});

it('requires request-backed column filters', function () {
    $valueRelation = ExportModelRelation::create([
        'export_model_id' => $this->postExportModel->id,
        'title' => 'Tag Value',
        'relation' => 'tags.value',
        'is_column' => true,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Required Column Filter',
    ]);

    $filter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $valueRelation->id,
        'operator' => 'in',
        'is_request' => true,
        'is_required' => true,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_filter_id' => $filter->id,
        'title' => 'Title',
        'value_path' => 'title',
        'position' => 1,
    ]);

    $this->service->executeExport($layout->id);
})->throws(Exception::class, "Required column filter 'tags.value' not provided");

it('treats comma strings like arrays for column filters', function () {
    $valueRelation = ExportModelRelation::create([
        'export_model_id' => $this->postExportModel->id,
        'title' => 'Tag Value',
        'relation' => 'tags.value',
        'is_column' => true,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Comma Column Filter',
    ]);

    $filter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $valueRelation->id,
        'operator' => 'in',
        'is_request' => true,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_filter_id' => $filter->id,
        'title' => 'Title',
        'value_path' => 'title',
        'position' => 1,
    ]);

    $asArray = $this->service->executeExport($layout->id, ['tags.value' => ['120', '30']])->toArray();
    $asString = $this->service->executeExport($layout->id, ['tags.value' => '120,30'])->toArray();

    expect(collect($asArray)->pluck('Title')->all())->toBe(['First Post', 'Third Post'])
        ->and(collect($asString)->pluck('Title')->all())->toBe(['First Post', 'Third Post']);
});

it('filters by relation existence without duplicating rows', function () {
    Post::insert([
        ['user_id' => User::where('name', 'John Doe')->first()->id, 'title' => 'Tagless Post', 'content' => 'C4', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Has Tags',
        'column_definitions' => ['Title' => 'title'],
        'filter_definitions' => [
            ['path' => 'tags', 'operator' => 'not_null', 'column' => 'value'],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(collect($result)->pluck('Title')->all())->toBe(['First Post', 'Second Post', 'Third Post'])
        ->and($this->service->getExportCount($layout->id))->toBe(3);
});

it('applies overrides after format templates and defaults before them', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Override Ordering',
    ]);

    $name = ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'format' => 'Site {value}',
        'position' => 1,
    ]);

    $nickname = ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Nickname',
        'value_path' => 'nickname',
        'format' => '{value} X',
        'position' => 2,
    ]);

    $result = $this->service->executeExport($layout->id, [
        'overrides' => [$name->id => 'FORCED'],
        'defaults' => [$nickname->id => 'N/A'],
    ])->toArray();

    expect($result[0])->toBe(['Name' => 'FORCED', 'Nickname' => 'N/A X']);
});

it('derives expanded column headers from the filtered result set', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Filtered Expansion',
        'column_definitions' => [
            'Title' => 'title',
            'Categories' => [
                'relation' => 'tags',
                'is_expanded' => true,
                'format' => '{value} Total',
                'expansion_data' => ['header_path' => 'category.name'],
                'value_path' => 'value',
                'aggregator' => 'sum',
                'default' => '0',
            ],
        ],
        'filter_definitions' => [
            ['path' => 'published', 'operator' => '=', 'value' => true],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(2);

    foreach ($result as $row) {
        expect(array_keys($row))->toBe(['Title', 'Lifestyle Total', 'Technology Total']);
    }

    expect($result[0]['Lifestyle Total'])->toBe(50)
        ->and($result[0]['Technology Total'])->toBe(120)
        ->and($result[1]['Lifestyle Total'])->toBe(30)
        ->and($result[1]['Technology Total'])->toBe(0);
});

it('interleaves defined and persisted sorts by priority', function () {
    $titleRelation = ExportModelRelation::create([
        'export_model_id' => $this->postExportModel->id,
        'title' => 'Title',
        'relation' => 'title',
        'is_column' => true,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Interleaved Sorts',
        'column_definitions' => ['Title' => 'title'],
        'sort_definitions' => [
            ['path' => 'user', 'sort_column' => 'name', 'priority' => 1],
        ],
    ]);

    ExportSort::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $titleRelation->id,
        'direction' => 'asc',
        'priority' => 5,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(collect($result)->pluck('Title')->all())->toBe(['Third Post', 'First Post', 'Second Post']);
});

it('defaults defined sorts to slot after persisted sorts', function () {
    $titleRelation = ExportModelRelation::create([
        'export_model_id' => $this->postExportModel->id,
        'title' => 'Title',
        'relation' => 'title',
        'is_column' => true,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Default Priority Sorts',
        'column_definitions' => ['Title' => 'title'],
        'sort_definitions' => [
            ['path' => 'user', 'sort_column' => 'name'],
        ],
    ]);

    ExportSort::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $titleRelation->id,
        'direction' => 'asc',
        'priority' => 5,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(collect($result)->pluck('Title')->all())->toBe(['First Post', 'Second Post', 'Third Post']);
});

it('paginates with correct meta and page slicing', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Paginated Export',
        'column_definitions' => ['Name' => 'name'],
        'sort_definitions' => [['path' => 'name']],
    ]);

    $page = $this->service->executeExportPaginated($layout->id, [], 2, 2);

    expect($page['data']->toArray())->toBe([['Name' => 'John Doe']])
        ->and($page['meta'])->toBe([
            'current_page' => 2,
            'last_page' => 2,
            'per_page' => 2,
            'total' => 3,
            'from' => 3,
            'to' => 3,
        ]);
});

it('applies smart dotted request filters with not_in and comma strings', function () {
    $authorRelation = ExportModelRelation::create([
        'export_model_id' => $this->tagExportModel->id,
        'title' => 'Author Name',
        'relation' => 'post.user.name',
        'is_column' => true,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->tagExportModel->id,
        'name' => 'Smart Not In',
        'column_definitions' => ['Value' => 'value'],
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $authorRelation->id,
        'operator' => 'not_in',
        'is_request' => true,
    ]);

    $excludeJohn = $this->service->executeExport($layout->id, ['post.user.name' => 'John Doe'])->toArray();
    $excludeBoth = $this->service->executeExport($layout->id, ['post.user.name' => 'John Doe,Jane Smith'])->toArray();
    $snakeKey = $this->service->executeExport($layout->id, ['post_user_name' => 'John Doe'])->toArray();

    expect(collect($excludeJohn)->pluck('Value')->all())->toBe(['30'])
        ->and($excludeBoth)->toHaveCount(0)
        ->and(collect($snakeKey)->pluck('Value')->all())->toBe(['30']);
});

it('advances auto positions past explicitly positioned definitions', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Position Clamp',
        'column_definitions' => [
            'Far' => ['value_path' => 'email', 'position' => 5],
            'Next' => 'name',
        ],
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $this->nameRelation->id,
        'title' => 'Persisted',
        'value_path' => 'name',
        'position' => 2,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect(array_keys($result[0]))->toBe(['Persisted', 'Far', 'Next']);
});

it('builds and saves catalog-backed layouts fluently', function () {
    ExportModelRelation::query()->delete();
    ExportModel::query()->delete();

    $layout = ExportLayoutBuilder::for(Post::class)
        ->name('built_report')
        ->title('Built Report')
        ->column('Title', 'title')
        ->column('Author', 'user.name')
        ->column('Tag Total', ['relation' => 'tags', 'value_path' => 'tags.value', 'aggregator' => 'sum', 'default' => '0'])
        ->filter('published', '=', true)
        ->sort('title', 'desc')
        ->save();

    expect($layout->columns()->count())->toBe(3)
        ->and(ExportFilter::where('export_layout_id', $layout->id)->count())->toBe(1)
        ->and(ExportSort::where('export_layout_id', $layout->id)->count())->toBe(1);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(2)
        ->and(collect($result)->pluck('Title')->all())->toBe(['Third Post', 'First Post'])
        ->and($result[0]['Author'])->toBe('Jane Smith')
        ->and($result[1]['Tag Total'])->toBe(170);
});

it('rolls back the whole builder save on invalid paths', function () {
    expect(fn () => ExportLayoutBuilder::for(Post::class)
        ->name('broken_report')
        ->column('Good', 'title')
        ->column('Bad', ['relation' => 'not_real'])
        ->save())
        ->toThrow(InvalidArgumentException::class, "Relation 'not_real'");

    expect(ExportLayout::where('name', 'broken_report')->exists())->toBeFalse();
});

it('formats column values with the {value} template', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Formatted Export',
    ]);

    $layout->addColumns([
        'Author' => ['value_path' => 'user.name', 'format' => 'Site {value}'],
        'Tag Sum' => ['value_path' => 'tags.value', 'relation' => 'tags', 'aggregator' => 'sum', 'format' => '{value} Items'],
        'Missing' => ['value_path' => 'nickname', 'format' => '{value} X'],
        'Fallback' => ['value_path' => 'nickname', 'default' => '0', 'format' => '{value} Items'],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0]['Author'])->toBe('Site John Doe')
        ->and($result[0]['Tag Sum'])->toBe('170 Items')
        ->and($result[0]['Missing'])->toBe('')
        ->and($result[0]['Fallback'])->toBe('0 Items');
});

it('expands a collection column into per-value columns', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Expanded Export',
        'column_definitions' => [
            'Title' => 'title',
            'Categories' => [
                'relation' => 'tags',
                'is_expanded' => true,
                'format' => '{value} Total',
                'expansion_data' => ['header_path' => 'category.name'],
                'value_path' => 'value',
                'aggregator' => 'sum',
                'default' => '0',
            ],
        ],
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(3);

    foreach ($result as $row) {
        expect(array_keys($row))->toBe(['Title', 'Business Total', 'Lifestyle Total', 'Technology Total']);
    }

    expect($result[0]['Technology Total'])->toBe(120)
        ->and($result[0]['Lifestyle Total'])->toBe(50)
        ->and($result[0]['Business Total'])->toBe(0)
        ->and($result[1]['Technology Total'])->toBe(75)
        ->and($result[1]['Business Total'])->toBe(200)
        ->and($result[2]['Lifestyle Total'])->toBe(30)
        ->and($result[2]['Technology Total'])->toBe(0);
});

it('rejects expanded columns in chunked exports', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Expanded Chunked Export',
        'column_definitions' => [
            'Categories' => [
                'relation' => 'tags',
                'is_expanded' => true,
                'expansion_data' => ['header_path' => 'category.name'],
                'value_path' => 'value',
                'aggregator' => 'sum',
            ],
        ],
    ]);

    $this->service->executeExportChunked($layout->id);
})->throws(RuntimeException::class, 'Expanded columns require a full-dataset export');

it('splits xlsx exports into sheets by column value', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'Sheeted Export',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Author',
        'value_path' => 'user.name',
        'position' => 1,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Title',
        'value_path' => 'title',
        'position' => 2,
    ]);

    $xlsx = $this->service->exportTo($layout->id, 'xlsx', [], ['sheet_by' => 'Author']);

    $tmp = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tmp, $xlsx);
    $spreadsheet = IOFactory::load($tmp);
    unlink($tmp);

    expect($spreadsheet->getSheetNames())->toBe(['John Doe', 'Jane Smith']);

    $john = $spreadsheet->getSheetByName('John Doe');
    $jane = $spreadsheet->getSheetByName('Jane Smith');

    expect($john->getCell('B1')->getValue())->toBe('Title')
        ->and($john->getCell('B2')->getValue())->toBe('First Post')
        ->and($john->getCell('B3')->getValue())->toBe('Second Post')
        ->and($jane->getCell('B2')->getValue())->toBe('Third Post');
});

it('exports one xlsx sheet per key with sanitized titles', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->userExportModel->id,
        'name' => 'Multi Sheet Export',
    ]);

    $handler = ExportFactory::create('xlsx', $layout);

    $longTitle = str_repeat('Quarterly Numbers ', 3);
    $xlsx = $handler->export(collect([
        'Summary [2026/Q1]' => [['Metric' => 'Total', 'Value' => 10]],
        $longTitle.'A' => [['Metric' => 'A', 'Value' => 1]],
        $longTitle.'B' => [['Metric' => 'B', 'Value' => 2]],
    ]));

    $tmp = tempnam(sys_get_temp_dir(), 'lex').'.xlsx';
    file_put_contents($tmp, $xlsx);
    $spreadsheet = IOFactory::load($tmp);
    unlink($tmp);

    expect($spreadsheet->getSheetNames())->toBe([
        'Summary  2026 Q1',
        'Quarterly Numbers Quarterly Num',
        'Quarterly Numbers Quarterly (2)',
    ])
        ->and($spreadsheet->getSheetByName('Summary  2026 Q1')->getCell('B2')->getValue())->toBe(10);
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
