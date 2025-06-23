<?php

return [
    'export_models' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportModel::class,
        'table' => 'export_models',
    ],

    'export_model_relations' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportModelRelation::class,
        'table' => 'export_model_relations',
    ],

    'export_layouts' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportLayout::class,
        'table' => 'export_layouts',
    ],

    'export_filters' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportFilter::class,
        'table' => 'export_filters',
    ],

    'export_sorts' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportSort::class,
        'table' => 'export_sorts',
    ],

    'export_functions' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportFunction::class,
        'table' => 'export_functions',
    ],

    'export_columns' => [
        'model' => \HexagonLabsLLC\LaravelExports\Models\ExportColumn::class,
        'table' => 'export_columns',
    ],
];
