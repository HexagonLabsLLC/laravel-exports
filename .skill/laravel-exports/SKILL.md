---
name: laravel-exports
description: |
  Database-driven export system for Laravel with dynamic layouts, filters, transformations, and pivot/crosstab support.
  Use when: (1) Creating export layouts via seeders, (2) Configuring pivot/crosstab reports with grouping,
  (3) Setting up filters (static, request-based, or relation-based), (4) Applying transformation functions to columns,
  (5) Working with ExportModel, ExportLayout, ExportColumn, ExportFilter, ExportSort, or ExportFunction models,
  (6) Building aggregated reports (sum, count, avg, min, max, first, last),
  (7) User mentions "laravel-exports", "export layout", "pivot export", or "crosstab report".
---

# Laravel Exports

Database-driven export system with dynamic layouts, filtering, transformations, and pivot support.

## Quick Reference

```bash
# Import models from codebase
php artisan export:import-models --force

# Seed built-in functions
php artisan export:seed-functions --force

# Run seeder
php artisan db:seed --class=MyExportSeeder
```

## Core Tables

| Table | Purpose |
|-------|---------|
| `export_models` | Registered Eloquent models |
| `export_model_relations` | Model columns/relationships |
| `export_layouts` | Named export configurations |
| `export_columns` | Output columns with transformations |
| `export_filters` | Query constraints |
| `export_sorts` | Ordering configuration |
| `export_functions` | Transformation functions |

## Creating Export Seeders

See [references/seeder-patterns.md](references/seeder-patterns.md) for complete patterns.

### Basic Seeder Structure

```php
<?php

namespace Database\Seeders;

use HexagonLabsLLC\LaravelExports\Models\{
    ExportColumn, ExportFilter, ExportFunction,
    ExportLayout, ExportModel, ExportModelRelation
};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MyExportSeeder extends Seeder
{
    public function run(): void
    {
        $model = ExportModel::where('model', 'App\\Models\\MyModel')->first();
        if (!$model) {
            $this->command->error('Model not found. Run: php artisan export:import-models');
            return;
        }

        DB::transaction(function () use ($model) {
            $layout = ExportLayout::updateOrCreate(
                ['name' => 'My Export'],
                [
                    'export_model_id' => $model->id,
                    'description' => 'Description here',
                ]
            );

            $layout->columns()->delete();
            $layout->filters()->delete();

            $this->createFilters($layout, $model);
            $this->createColumns($layout);
        });
    }
}
```

## Filters

See [references/operators.md](references/operators.md) for all operators.

### Request-Based Filter (Dynamic)

```php
$relation = ExportModelRelation::firstOrCreate([
    'export_model_id' => $model->id,
    'relation' => 'customer_id',
], ['title' => 'Customer ID', 'is_column' => true, 'is_collection' => false]);

ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $relation->id,
    'operator' => '=',
    'value_type' => 'integer',
    'is_request' => true,
    'is_required' => true,
    'logical_operator' => 'AND',
]);
```

### Static Filter

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $relation->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'is_request' => false,
    'logical_operator' => 'AND',
]);
```

### Date Range Filter

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $dateRelation->id,
    'operator' => 'between',
    'value_type' => 'array',
    'is_request' => true,
    'is_required' => false,
    'logical_operator' => 'AND',
]);
```

### Null Check Filter

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $relation->id,
    'operator' => 'not_null',  // or 'null'
    'value_type' => 'string',  // Required even though value is ignored
    'is_request' => false,
    'logical_operator' => 'AND',
]);
```

## Columns

### Basic Column

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'position' => 1,
]);
```

### Related Data Column

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer Name',
    'value_path' => 'workOrder.customer.name',
    'position' => 2,
]);
```

### Aggregated Column

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Amount',
    'value_path' => 'orders.amount',
    'aggregator' => 'sum',  // sum, count, avg, min, max, first, last
    'position' => 3,
]);
```

### Column with Function

```php
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Created',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'M j, Y'],
    'position' => 4,
]);
```

## Pivot Exports

For crosstab/pivot reports. See [references/pivot-config.md](references/pivot-config.md).

### Pivot Layout

```php
$layout = ExportLayout::updateOrCreate(
    ['name' => 'Weekly Hours by Type'],
    [
        'export_model_id' => $model->id,
        'description' => 'Weekly hours pivoted by work type',
        'is_pivot' => true,
        'pivot_config' => [
            // Primary grouping (rows); entries take a per-entry date bucket
            // (day, week, month, quarter, year) or inherit group_by_format
            'group_by' => ['time_start'],
            'group_by_format' => 'week_year',  // Formats string entries as YYYY-WNN
            'week_start' => 'sunday',          // mysql only; monday (ISO) works everywhere
            'group_by_headers' => ['Work Week'],

            // Sub-grouping (nested rows)
            'sub_group_by' => ['worker.name'],
            'sub_group_by_headers' => ['Worker Name'],

            // Dynamic columns from relation
            'pivot_relation' => 'workItem.workType',
            'pivot_column' => 'name',
            'pivot_filter_param' => 'work_type_ids',

            // Value aggregation
            'value_relation' => '',  // Empty = base table
            'value_column' => 'hours',
            'aggregation' => 'sum',

            // Output format
            'output_format' => 'grouped',  // or 'flat'
            'total_header' => 'Total Hours',
        ],
    ]
);
```

### Pivot Column

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Hours',
    'value_path' => 'hours',
    'is_expanded' => true,
    'expansion_data' => [
        'type' => 'pivot',
        'format_function' => $hoursFunction?->id,
    ],
    'position' => 1,
]);
```

## Custom Functions

Register custom transformation functions:

```php
ExportFunction::updateOrCreate(
    ['callable' => 'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'],
    [
        'name' => 'Format Hours Decimal',
        'parameter_count' => 1,
        'value_parameter_index' => 0,
    ]
);
```

Implementation:

```php
// app/Services/Export/ExportFunctions.php
namespace App\Services\Export;

class ExportFunctions
{
    public static function formatHoursDecimal($hours): string
    {
        $hours = (float) ($hours ?? 0);
        return $hours > 0 ? number_format($hours, 2) : '';
    }
}
```

## Executing Exports

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();

// Execute with request data
$data = $service->executeExport($layout, [
    'customer_id' => 123,
    'work_start_at' => ['2025-01-01', '2025-12-31'],
]);

// Download as CSV
return $service->downloadAs($layout, 'csv', 'export.csv');

// For pivot exports
$data = $service->executeExport($layout, $requestData); // routes pivot layouts automatically
```

## Common Gotchas

1. **Date BETWEEN excludes end-of-day**: Use `'2025-12-31 23:59:59'` not `'2025-12-31'`
2. **Eloquent casts interfere with formatted SQL**: Library uses `toBase()->get()` for pivot exports
3. **Operator column is an enum**: Use `'not_null'` not `'is not null'`
4. **value_type required**: Even for null operators, use `'string'`
5. **Run import-models first**: Always run `php artisan export:import-models` before creating seeders

## Resources

- **Operators**: See [references/operators.md](references/operators.md)
- **Functions**: See [references/functions.md](references/functions.md)
- **Pivot Config**: See [references/pivot-config.md](references/pivot-config.md)
- **Seeder Patterns**: See [references/seeder-patterns.md](references/seeder-patterns.md)
