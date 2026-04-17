<?php

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;

it('can create an export model', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    expect($exportModel)->toBeInstanceOf(ExportModel::class)
        ->and($exportModel->title)->toBe('User Export')
        ->and($exportModel->model)->toBe(User::class);
});

it('can instantiate the model class', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    $modelInstance = $exportModel->instance;

    expect($modelInstance)->toBeInstanceOf(User::class);
});

it('has relations relationship', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    $relation = ExportModelRelation::create([
        'export_model_id' => $exportModel->id,
        'title' => 'email',
        'relation' => 'email',
        'is_column' => true,
        'is_collection' => false,
    ]);

    expect($exportModel->relations)->toHaveCount(1)
        ->and($exportModel->relations->first()->id)->toBe($relation->id);
});

it('has layouts relationship', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $exportModel->id,
        'name' => 'Default Layout',
    ]);

    expect($exportModel->layouts)->toHaveCount(1)
        ->and($exportModel->layouts->first()->id)->toBe($layout->id);
});

it('has filters relationship', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $exportModel->id,
        'name' => 'Default Layout',
    ]);

    $filter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_id' => $exportModel->id,
        'operator' => '=',
        'value' => 'test@example.com',
    ]);

    expect($exportModel->filters)->toHaveCount(1)
        ->and($exportModel->filters->first()->id)->toBe($filter->id);
});

it('has sorts relationship', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $exportModel->id,
        'name' => 'Default Layout',
    ]);

    $sort = ExportSort::create([
        'export_layout_id' => $layout->id,
        'export_model_id' => $exportModel->id,
        'direction' => 'asc',
        'priority' => 1,
    ]);

    expect($exportModel->sorts)->toHaveCount(1)
        ->and($exportModel->sorts->first()->id)->toBe($sort->id);
});

it('has columns through layouts', function () {
    $exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    $layout = ExportLayout::create([
        'export_model_id' => $exportModel->id,
        'name' => 'Default Layout',
    ]);

    $column = ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Email',
        'value_path' => 'email',
        'position' => 1,
    ]);

    expect($layout->columns)->toHaveCount(1)
        ->and($layout->columns->first()->id)->toBe($column->id);
});
