<?php

use HexagonLabsLLC\LaravelExports\Builders\ExportLayoutBuilder;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Services\LayoutValidator;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->postExportModel = ExportModel::create(['title' => 'Post Export', 'model' => Post::class]);
    $this->validator = app(LayoutValidator::class);
});

it('passes a valid layout', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'valid_layout',
        'column_definitions' => [
            'Title' => 'title',
            'Tag Total' => ['relation' => 'tags', 'value_path' => 'tags.value', 'aggregator' => 'sum'],
        ],
        'filter_definitions' => [['path' => 'published', 'operator' => '=', 'value' => true]],
        'sort_definitions' => [['path' => 'title', 'direction' => 'desc']],
    ]);

    expect($this->validator->validate($layout))->toBe([]);
});

it('spot checks drafts without writing anything', function () {
    $draft = [
        'model' => Post::class,
        'name' => 'draft',
        'column_definitions' => [
            'Total' => ['relation' => 'tags', 'value_path' => 'tags.value', 'aggregator' => 'summ'],
        ],
        'filter_definitions' => [['path' => 'user.nmae', 'operator' => 'equals-ish']],
    ];

    $problems = $this->validator->validateDraft($draft);
    $codes = array_column($problems, 'code');

    expect($codes)->toContain('unknown_aggregator', 'unknown_path', 'unknown_operator')
        ->and(collect($problems)->firstWhere('code', 'unknown_aggregator')['params'])->toBe(['aggregator' => 'summ'])
        ->and(ExportLayout::count())->toBe(0)
        ->and(ExportModelRelation::count())->toBe(0);

    config()->set('laravel-exports.schema_sync', 'manual');
    $this->validator->validateDraft($draft);

    expect(ExportModelRelation::count())->toBe(0);
});

it('flags broken persisted configuration', function () {
    $tagsRelation = ExportModelRelation::create([
        'export_model_id' => $this->postExportModel->id,
        'title' => 'tags',
        'relation' => 'tags',
        'is_collection' => true,
    ]);

    $function = ExportFunction::create([
        'name' => 'Broken Fn',
        'callable' => 'Not\\A\\Class::nope',
        'parameter_count' => 1,
        'value_parameter_index' => 0,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'broken_layout',
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Agg',
        'value_path' => 'title',
        'aggregator' => 'summ',
        'position' => 1,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Fn',
        'value_path' => 'title',
        'export_function_id' => $function->id,
        'position' => 2,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Fmt',
        'value_path' => 'title',
        'format' => 'no placeholder',
        'position' => 3,
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'operator' => '=',
        'is_request' => true,
        'is_required' => true,
        'logical_operator' => 'or',
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $tagsRelation->id,
        'operator' => 'between',
        'value' => '["1","2","3"]',
        'value_type' => 'dates',
    ]);

    ExportSort::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $tagsRelation->id,
        'direction' => 'up',
        'priority' => 1,
    ]);

    $problems = $this->validator->validate($layout);
    $codes = array_column($problems, 'code');

    expect($codes)->toContain(
        'unknown_aggregator',
        'function_not_callable',
        'missing_placeholder',
        'required_without_relation',
        'leading_or',
        'between_requires_two',
        'unknown_value_type',
        'unknown_direction',
        'collection_sort_count'
    );
});

it('flags unresolvable pivot paths and config values', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'broken_pivot',
        'is_pivot' => true,
        'pivot_config' => [
            'group_by' => ['user.nmae'],
            'pivot_relation' => 'tags.category.name',
            'value_relation' => 'tags',
            'value_column' => 'value',
            'aggregation' => 'summ',
            'output_format' => 'wide',
        ],
    ]);

    $problems = $this->validator->validate($layout);
    $codes = array_column($problems, 'code');

    expect($codes)->toContain('unknown_pivot_path', 'unknown_pivot_aggregation', 'unknown_output_format')
        ->and(collect($problems)->firstWhere('code', 'unknown_pivot_path')['params']['path'])->toBe('user.nmae')
        ->and($codes)->not->toContain('no_columns');
});

it('spot checks staged builder layouts without saving', function () {
    $problems = ExportLayoutBuilder::for(Post::class)
        ->name('staged')
        ->column('Total', ['relation' => 'nope', 'value_path' => 'x'])
        ->filter('title', 'wrongop', 'x')
        ->validate();

    $codes = array_column($problems, 'code');

    expect($codes)->toContain('unknown_path', 'unknown_operator')
        ->and(ExportLayout::count())->toBe(0);
});

it('reports every error when saving an invalid builder layout', function () {
    $caught = null;

    try {
        ExportLayoutBuilder::for(Post::class)
            ->name('multi_broken')
            ->column('A', ['relation' => 'nope', 'value_path' => 'x'])
            ->column('B', ['value_path' => 'title', 'aggregator' => 'summ'])
            ->save();
    } catch (InvalidArgumentException $e) {
        $caught = $e->getMessage();
    }

    expect($caught)->toContain("'nope' does not resolve")
        ->and($caught)->toContain("'summ' is not supported")
        ->and(ExportLayout::where('name', 'multi_broken')->exists())->toBeFalse();
});

it('honors overridden and localized validation messages', function () {
    $draft = [
        'model' => Post::class,
        'name' => 'lang_draft',
        'column_definitions' => ['A' => ['value_path' => 'title', 'aggregator' => 'summ']],
    ];

    app('translator')->addLines(['validation.unknown_aggregator' => 'Pick a real aggregation (:aggregator)'], 'en', 'laravel-exports');

    $problems = $this->validator->validateDraft($draft);

    expect(collect($problems)->firstWhere('code', 'unknown_aggregator')['message'])
        ->toBe('Pick a real aggregation (summ)');

    app('translator')->addLines(['validation.unknown_aggregator' => 'Agregacion :aggregator no soportada'], 'es', 'laravel-exports');
    app()->setLocale('es');

    $problems = $this->validator->validateDraft($draft);

    expect(collect($problems)->firstWhere('code', 'unknown_aggregator')['message'])
        ->toBe('Agregacion summ no soportada');
});

it('validates layouts from the console with ci friendly exit codes', function () {
    ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'broken_cmd',
        'column_definitions' => ['A' => ['value_path' => 'title', 'aggregator' => 'summ']],
    ]);

    expect(Artisan::call('export:validate'))->toBe(1);

    $output = Artisan::output();

    expect($output)->toContain("'summ' is not supported")
        ->and($output)->toContain('1 layouts checked, 1 errors, 0 warnings');

    ExportLayout::query()->delete();

    ExportLayout::create([
        'export_model_id' => $this->postExportModel->id,
        'name' => 'warn_cmd',
        'column_definitions' => ['A' => ['value_path' => 'title', 'format' => 'no placeholder']],
    ]);

    expect(Artisan::call('export:validate', ['--layout' => 'warn_cmd']))->toBe(0)
        ->and(Artisan::output())->toContain('no {value} placeholder')
        ->and(Artisan::call('export:validate', ['--layout' => 'missing_layout']))->toBe(1);
});
