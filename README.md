# Laravel Exports

A powerful, database-driven export system for Laravel applications that provides dynamic, configurable exports without writing code.

## Features

- **Database-Driven Configuration** - Define exports through database records; a single layout row can carry its columns, filters, and sorts as JSON
- **Lazy Catalog Sync** - Models and relations register themselves on first reference; no setup command required
- **Fluent Builder** - `ExportLayoutBuilder` composes and validates complete layouts in one chain
- **Validation** - Spot-check layouts before saving or via `php artisan export:validate`, with overridable, localizable messages
- **Advanced Filtering** - Static filters, request-based filters, smart dotted-path filters, and collection filters, with grouped or logic
- **Nested Relationship Support** - Export deeply nested data using dot notation
- **Dynamic Column Expansion** - One configured column fans out into a column per related value, titled by a `{value}` template
- **Format Templates** - Wrap cell values with templates like `Site {value}`
- **Pivot/Crosstab Reports** - Excel-style pivot exports with grouping, sub-groups, and dynamic columns
- **Pivot Table Data** - Access BelongsToMany pivot attributes via `.pivot.` notation
- **Transformation Functions** - 23 built-in functions for formatting dates, strings, numbers
- **Aggregations** - Sum, count, average, min, max, first, last on collections, including filtered subsets
- **Large Dataset Support** - Chunking, streaming, and background job processing
- **Multiple Formats** - CSV and JSON out of the box, multi-sheet XLSX via the optional phpoffice/phpspreadsheet package. Need PDF or something custom? Consume the array output from `executeExport()` and render it however you like, or register your own handler via `ExportFactory::register()`

## Documentation

Full documentation is available in the [docs](docs/index.md) directory:

- [Getting Started](docs/getting-started.md) - Installation and setup
- [Configuration](docs/configuration.md) - All configuration options, including schema sync modes
- [Guides](docs/guides/) - In-depth guides for each feature
- [Examples](docs/examples/) - Practical examples from basic to advanced
- [API Reference](docs/reference/api.md) - Complete class and method documentation
- [Troubleshooting](docs/troubleshooting.md) - Common issues and solutions

## Quick Start

### Installation

```bash
composer require hexagonlabsllc/laravel-exports
```

### Setup

```bash
# Publish configuration and migrations
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider"

# Run migrations
php artisan migrate

# Optional: seed the built-in transformation functions
php artisan export:seed-functions
```

That is the whole setup. Models and their relations sync into the export catalog automatically the first time a layout references them (configurable via `schema_sync`; `php artisan export:import-models --deep` still pre-populates everything at once if you prefer).

### Basic Usage

```php
use HexagonLabsLLC\LaravelExports\Builders\ExportLayoutBuilder;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

// 1. Build a layout - paths are validated, everything saves in one transaction
$layout = ExportLayoutBuilder::for(\App\Models\User::class)
    ->name('user_export')
    ->title('User Export')
    ->column('Name', 'name')
    ->column('Email', 'email')
    ->column('Department', 'department.name')
    ->save();

// 2. Export it
$service = app(DynamicExportService::class);

return $service->downloadAs($layout, 'csv', 'users.csv');
```

Layouts are plain database rows, so they can also come from anywhere else - an admin UI, a seeder, or raw SQL:

```php
ExportLayout::create([
    'model' => \App\Models\User::class,
    'name' => 'user_export',
    'column_definitions' => ['Name' => 'name', 'Email' => 'email'],
    'filter_definitions' => [['path' => 'active', 'operator' => '=', 'value' => true]],
    'sort_definitions' => [['path' => 'name']],
]);
```

### Validation

Spot-check a layout before saving, or audit every layout in CI:

```php
$problems = ExportLayoutBuilder::for(User::class)->column('Name', 'name')->validate();
$problems = app(LayoutValidator::class)->validateDraft($request->all());
```

```bash
php artisan export:validate            # exits non-zero when any layout has errors
```

Messages are translatable and overridable: publish the `lang` tag and edit `lang/vendor/laravel-exports/{locale}/validation.php` to give clients friendlier wording, or map each problem's `code` and `params` to your own frontend strings.

### Request-Based Filtering

```php
ExportLayout::create([
    'model' => \App\Models\User::class,
    'name' => 'filtered_users',
    'column_definitions' => ['Name' => 'name'],
    'filter_definitions' => [
        ['path' => 'status', 'operator' => '=', 'is_request' => true],
    ],
]);

// In your controller
$service->downloadAs($layout, 'csv', 'users.csv', ['status' => 'active']);
```

### Large Datasets

```php
// Streaming
return $service->streamAs($layout, 'csv', 'large.csv', [], [], 1000);

// Background job
$exportId = $service->queueExport($layout, 'csv');
$status = ProcessExportJob::getStatus($exportId);
```

### Multi-Sheet XLSX

```bash
composer require phpoffice/phpspreadsheet
```

```php
return $service->downloadAs($layout, 'xlsx', 'report.xlsx', [], ['sheet_by' => 'Author']);
```

## Requirements

- PHP 8.2+
- Laravel 12.12+ or 13
- Database with UUID support

## Learn More

See the [full documentation](docs/index.md) for:

- [Nested Relationships](docs/guides/nested-relationships.md) - Deep data traversal
- [Pivot Tables](docs/guides/pivot-tables.md) - BelongsToMany pivot data
- [Pivot Reports](docs/guides/pivot-reports.md) - Crosstab reports with grouping and date buckets
- [Transformation Functions](docs/guides/transformation-functions.md) - All 23 functions
- [Aggregations](docs/guides/aggregations.md) - Collection aggregation
- [Large Datasets](docs/guides/large-datasets.md) - Performance optimization
- [Background Jobs](docs/examples/large-scale/background-jobs.md) - Queue processing

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
