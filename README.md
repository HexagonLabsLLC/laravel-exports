# Laravel Exports

A powerful, database-driven export system for Laravel applications that provides dynamic, configurable exports without writing code.

## Features

- **Database-Driven Configuration** - Define exports through database records, not code
- **Dynamic Model Discovery** - Auto-import Eloquent models and their relationships
- **Advanced Filtering** - Static filters, request-based filters, and collection filters
- **Nested Relationship Support** - Export deeply nested data using dot notation
- **Pivot Table Data** - Access BelongsToMany pivot attributes via `.pivot.` notation
- **Transformation Functions** - 22 built-in functions for formatting dates, strings, numbers
- **Aggregations** - Sum, count, average, min, max, first, last on collections
- **Large Dataset Support** - Chunking, streaming, and background job processing
- **Multiple Formats** - CSV and JSON out of the box, extensible for more

## Documentation

Full documentation is available in the [docs](docs/index.md) directory:

- [Getting Started](docs/getting-started.md) - Installation and setup
- [Configuration](docs/configuration.md) - All configuration options
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

# Import your models with relationships
php artisan export:import-models --deep

# Seed transformation functions
php artisan export:seed-functions
```

### Basic Usage

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn};
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

// 1. Create an export layout
$userModel = ExportModel::where('title', 'User')->first();
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'user_export',
    'title' => 'User Export',
]);

// 2. Define columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 2,
]);

// 3. Export data
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'users.csv');
```

### Related Data

Export data from relationships using dot notation:

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Department',
    'value_path' => 'department.name',
    'position' => 3,
]);
```

### Request-Based Filtering

Add dynamic filters controlled by request parameters:

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'is_request' => true,
]);

// In your controller
$service->downloadAs($layout, 'csv', 'users.csv', [
    'status' => 'active',
]);
```

### Large Datasets

For large exports, use streaming or background jobs:

```php
// Streaming
return $service->streamAs($layout, 'csv', 'large.csv', [], [], 1000);

// Background job
$exportId = $service->queueExport($layout, 'csv');
$status = ProcessExportJob::getStatus($exportId);
```

## Requirements

- PHP 8.2+
- Laravel 12 or 13
- Database with UUID support

## Learn More

See the [full documentation](docs/index.md) for:

- [Nested Relationships](docs/guides/nested-relationships.md) - Deep data traversal
- [Pivot Tables](docs/guides/pivot-tables.md) - BelongsToMany pivot data
- [Transformation Functions](docs/guides/transformation-functions.md) - All 22 functions
- [Aggregations](docs/guides/aggregations.md) - Collection aggregation
- [Large Datasets](docs/guides/large-datasets.md) - Performance optimization
- [Background Jobs](docs/examples/large-scale/background-jobs.md) - Queue processing

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
