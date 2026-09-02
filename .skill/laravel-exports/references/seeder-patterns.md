# Seeder Patterns

Complete patterns for creating export layout seeders.

## Basic Seeder Template

```php
<?php

namespace Database\Seeders;

use App\Models\ExportLayout; // Or use the package model
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MyExportSeeder extends Seeder
{
    public function run(): void
    {
        // Get the base model
        $model = ExportModel::where('model', 'App\\Models\\MyModel')->first();

        if (!$model) {
            $this->command->error('MyModel not found. Run: php artisan export:import-models');
            return;
        }

        DB::transaction(function () use ($model) {
            // Create or update layout
            $layout = ExportLayout::updateOrCreate(
                ['name' => 'My Export Name'],
                [
                    'export_model_id' => $model->id,
                    'description' => 'Description of what this export does',
                ]
            );

            // Clear existing columns and filters for idempotency
            $layout->columns()->delete();
            $layout->filters()->delete();

            // Create filters first (columns may reference them)
            $this->createFilters($layout, $model);

            // Create columns
            $this->createColumns($layout);

            $this->command->info("Created My Export layout: {$layout->id}");
        });
    }

    private function createFilters(ExportLayout $layout, ExportModel $model): void
    {
        // Add filters here
    }

    private function createColumns(ExportLayout $layout): void
    {
        // Add columns here
    }
}
```

## Filter Patterns

### Required Request Filter

```php
private function createFilters(ExportLayout $layout, ExportModel $model): void
{
    // Customer ID - required from request
    $customerRelation = ExportModelRelation::firstOrCreate([
        'export_model_id' => $model->id,
        'relation' => 'customer_id',
    ], [
        'title' => 'Customer ID',
        'is_column' => true,
        'is_collection' => false,
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $customerRelation->id,
        'operator' => '=',
        'value_type' => 'integer',
        'is_request' => true,
        'is_required' => true,
        'logical_operator' => 'AND',
    ]);
}
```

### Optional Date Range Filter

```php
$dateRelation = ExportModelRelation::firstOrCreate([
    'export_model_id' => $model->id,
    'relation' => 'created_at',
], [
    'title' => 'Created Date',
    'is_column' => true,
    'is_collection' => false,
]);

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

### Nested Relation Filter

```php
// Filter on related model's column
$relation = ExportModelRelation::firstOrCreate([
    'export_model_id' => $model->id,
    'relation' => 'workOrder.customer_id',
], [
    'title' => 'Work Order Customer ID',
    'is_column' => true,
    'is_collection' => false,
]);

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

### Static Filter (Always Applied)

```php
$statusRelation = ExportModelRelation::firstOrCreate([
    'export_model_id' => $model->id,
    'relation' => 'status',
], [
    'title' => 'Status',
    'is_column' => true,
    'is_collection' => false,
]);

ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'is_request' => false,
    'logical_operator' => 'AND',
]);
```

### Not Null Filter

```php
$timeRelation = ExportModelRelation::firstOrCreate([
    'export_model_id' => $model->id,
    'relation' => 'time_start',
], [
    'title' => 'Time Start',
    'is_column' => true,
    'is_collection' => false,
]);

ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $timeRelation->id,
    'operator' => 'not_null',
    'value_type' => 'string',  // Required even for null operators
    'is_request' => false,
    'logical_operator' => 'AND',
]);
```

## Layout Construction

### Fluent Builder (preferred for seeders and UI backends)

`ExportLayoutBuilder` lazy-syncs the model's catalog rows on entry, validates every path, and persists layout + columns + filters + sorts in one transaction. `php artisan export:import-models` is no longer required first (default `lazy` schema sync):

```php
use HexagonLabsLLC\LaravelExports\Builders\ExportLayoutBuilder;

$layout = ExportLayoutBuilder::for(\App\Models\Post::class)
    ->name('posts_report')->title('Posts Report')
    ->column('Title', 'title')
    ->column('Author', 'user.name')
    ->column('Tag Total', ['relation' => 'tags', 'value_path' => 'tags.value', 'aggregator' => 'sum', 'default' => '0'])
    ->filter('published', '=', true)
    ->requestFilter('user.name', 'in', required: true)
    ->sort('created_at', 'desc')
    ->save();
```

Layouts can also carry filters and sorts as JSON on the row itself (`filter_definitions` entries {path, operator, value, ...}; `sort_definitions` entries {path, direction, priority?, sort_column?}), making a single INSERT a complete export. UI picklists come from `app(SchemaSync::class)->describe($modelClass)`.

Validate before saving: `$builder->validate()` returns a problem list (`severity`/`code`/`source`/`params`/`message`) without persisting; `LayoutValidator::validateDraft($formPayload)` does the same for raw UI payloads with zero DB writes; `save()` throws listing every error at once. `php artisan export:validate` audits all layouts (non-zero exit on errors). Messages are lang-based (`laravel-exports::validation.*`) - publish the `lang` tag to override wording or add locales; UIs can map `code` + `params` to their own strings.

## Column Patterns

### Database-Driven Columns (no seeder needed)

A layout row can carry its columns in the `column_definitions` JSON field, so layouts inserted through an admin UI or raw SQL need no export_columns rows and no PHP at all. Same entry shapes as addColumns(), merged with persisted columns by position:

```php
ExportLayout::create([
    'export_model_id' => $model->id,
    'name' => 'db_driven_export',
    'column_definitions' => [
        'Title' => 'title',
        'Tag Total' => ['value_path' => 'tags.value', 'relation' => 'tags', 'aggregator' => 'sum'],
    ],
]);
```

Note: these columns have no UUIDs, so request `defaults`/`overrides` cannot target them; set `default` inside the definition instead.

### Format Templates and Expanded Columns

`format` wraps a cell's final value with `{value}` (applied after aggregation/functions/defaults, skipped when empty). On an `is_expanded` column it instead templates the titles of generated columns - one per distinct related value across the dataset, cells aggregated per row:

```php
$layout->addColumns([
    'Customer' => ['value_path' => 'customer.name', 'format' => 'Site {value}'],  // cell: "Site Customer Ltd."
    'Sites' => [
        'relation' => 'laborEntries',
        'is_expanded' => true,
        'format' => 'Site {value}',                          // column titles: "Site Acme Corp", ...
        'expansion_data' => ['header_path' => 'site.name'],
        'value_path' => 'hours',
        'aggregator' => 'sum',
        'default' => '0',
    ],
]);
```

Expanded columns need the full dataset (column set is a union), so chunked/queued/streamed/paginated exports throw for them.

### Basic Columns

Prefer `ExportLayout::addColumns()` for bulk creation: one array, auto-incremented positions, and `relation` keys resolved to export_model_relation_id automatically (throws for unregistered relations):

```php
private function createColumns(ExportLayout $layout): void
{
    $layout->addColumns([
        'ID' => 'id',
        'Name' => 'name',
        'Email' => ['value_path' => 'email', 'default' => 'N/A'],
        'Post Count' => ['value_path' => 'posts', 'relation' => 'posts', 'aggregator' => 'count'],
    ]);
}
```

Entries may be `'Title' => 'value.path'` shorthand, `'Title' => [attributes]`, or list-style attribute arrays. Individual `ExportColumn::create([...])` calls still work when a column needs many attributes:

```php
    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Email',
        'value_path' => 'email',
        'default' => 'N/A',
        'position' => 3,
    ]);
```

### Related Data Column

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer Name',
    'value_path' => 'workOrder.customer.name',
    'default' => 'Unknown',
    'position' => 4,
]);
```

### Formatted Column

```php
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Created',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'M j, Y'],
    'position' => 5,
]);
```

### Aggregated Column

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Order Count',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'position' => 6,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Amount',
    'value_path' => 'orders.amount',
    'aggregator' => 'sum',
    'position' => 7,
]);
```

### Column with Custom Function

```php
// Register function first
$this->registerFunctions();

// Then use it
$hoursFunction = ExportFunction::where(
    'callable',
    'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'
)->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Hours',
    'value_path' => 'hours',
    'export_function_id' => $hoursFunction?->id,
    'position' => 8,
]);
```

## Custom Function Registration

```php
private function registerFunctions(): void
{
    ExportFunction::updateOrCreate(
        ['callable' => 'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'],
        [
            'name' => 'Format Hours Decimal',
            'parameter_count' => 1,
            'value_parameter_index' => 0,
        ]
    );
}
```

## Pivot Export Seeder

```php
<?php

namespace Database\Seeders;

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeeklyHoursExportSeeder extends Seeder
{
    public function run(): void
    {
        $model = ExportModel::where('model', 'App\\Models\\WorkItemLaborPay')->first();

        if (!$model) {
            $this->command->error('Model not found. Run: php artisan export:import-models');
            return;
        }

        DB::transaction(function () use ($model) {
            $this->registerFunctions();

            $layout = ExportLayout::updateOrCreate(
                ['name' => 'Weekly Hours'],
                [
                    'export_model_id' => $model->id,
                    'description' => 'Weekly hours by worker',
                    'is_pivot' => true,
                    'pivot_config' => [
                        'group_by' => ['time_start'],
                        'group_by_format' => 'week_year',
                        'week_start' => 'sunday',
                        'group_by_headers' => ['Work Week'],

                        'sub_group_by' => ['worker.name'],
                        'sub_group_by_headers' => ['Worker Name'],

                        'pivot_relation' => '',
                        'pivot_column' => '',

                        'value_relation' => '',
                        'value_column' => 'hours',
                        'aggregation' => 'sum',

                        'total_header' => 'Total Hours',
                        'output_format' => 'grouped',
                    ],
                ]
            );

            $layout->columns()->delete();
            $layout->filters()->delete();

            $this->createFilters($layout, $model);
            $this->createColumns($layout);

            $this->command->info("Created layout: {$layout->id}");
        });
    }

    private function registerFunctions(): void
    {
        ExportFunction::updateOrCreate(
            ['callable' => 'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'],
            [
                'name' => 'Format Hours Decimal',
                'parameter_count' => 1,
                'value_parameter_index' => 0,
            ]
        );
    }

    private function createFilters(ExportLayout $layout, ExportModel $model): void
    {
        // Required customer filter
        $customerRelation = ExportModelRelation::firstOrCreate([
            'export_model_id' => $model->id,
            'relation' => 'workItem.workOrder.customer_id',
        ], [
            'title' => 'Customer ID',
            'is_column' => true,
            'is_collection' => false,
        ]);

        ExportFilter::create([
            'export_layout_id' => $layout->id,
            'export_model_relation_id' => $customerRelation->id,
            'operator' => '=',
            'value_type' => 'integer',
            'is_request' => true,
            'is_required' => true,
            'logical_operator' => 'AND',
        ]);

        // Optional date range
        $dateRelation = ExportModelRelation::firstOrCreate([
            'export_model_id' => $model->id,
            'relation' => 'workItem.workOrder.work_start_at',
        ], [
            'title' => 'Work Start Date',
            'is_column' => true,
            'is_collection' => false,
        ]);

        ExportFilter::create([
            'export_layout_id' => $layout->id,
            'export_model_relation_id' => $dateRelation->id,
            'operator' => 'between',
            'value_type' => 'array',
            'is_request' => true,
            'is_required' => false,
            'logical_operator' => 'AND',
        ]);

        // Exclude null time_start
        $timeRelation = ExportModelRelation::firstOrCreate([
            'export_model_id' => $model->id,
            'relation' => 'time_start',
        ], [
            'title' => 'Time Start',
            'is_column' => true,
            'is_collection' => false,
        ]);

        ExportFilter::create([
            'export_layout_id' => $layout->id,
            'export_model_relation_id' => $timeRelation->id,
            'operator' => 'not_null',
            'value_type' => 'string',
            'is_request' => false,
            'logical_operator' => 'AND',
        ]);
    }

    private function createColumns(ExportLayout $layout): void
    {
        $hoursFunction = ExportFunction::where(
            'callable',
            'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'
        )->first();

        ExportColumn::create([
            'export_layout_id' => $layout->id,
            'title' => 'Total Hours',
            'value_path' => 'hours',
            'is_expanded' => true,
            'expansion_data' => [
                'type' => 'pivot',
                'format_function' => $hoursFunction?->id,
            ],
            'position' => 1,
        ]);
    }
}
```

## Running Seeders

```bash
# Single seeder
php artisan db:seed --class=MyExportSeeder

# Re-run (updateOrCreate makes it idempotent)
php artisan db:seed --class=MyExportSeeder
```
