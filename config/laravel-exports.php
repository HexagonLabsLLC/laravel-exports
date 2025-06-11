<?php

return [
    'export_models' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportModel::class,
        'table' => 'export_models',
    ],

    'export_model_relations' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportModelRelation::class,
        'table' => 'export_model_relations',
    ],

    'export_layouts' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportLayout::class,
        'table' => 'export_layouts',
    ],

    'export_filters' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportFilter::class,
        'table' => 'export_filters',
    ],

    'export_sorts' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportSort::class,
        'table' => 'export_sorts',
    ],

    'export_functions' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportFunction::class,
        'table' => 'export_functions',
    ],

    'export_columns' => [
        'model' => \Hexlabs\LaravelExports\Models\ExportColumn::class,
        'table' => 'export_columns',
    ],
];
