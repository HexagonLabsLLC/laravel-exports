# Laravel Exports Documentation

Laravel Exports is a powerful, database-driven export system for Laravel applications. Define exports through database records instead of code, enabling dynamic, configurable exports that can be modified without deployments.

## Key Features

- **Database-Driven Configuration** - All export definitions stored in database tables
- **Dynamic Model Discovery** - Auto-import Eloquent models and relationships
- **Advanced Filtering** - Static, request-based, and collection filters
- **Nested Relationships** - Export deeply nested data using dot notation
- **Transformation Functions** - 22 built-in functions for data formatting
- **Aggregations** - sum, count, avg, min, max, first, last on collections
- **Performance Optimized** - Smart eager loading, chunking, and streaming
- **Multiple Formats** - CSV and JSON with extensible architecture
- **Background Processing** - Queue large exports with status tracking
- **Pivot Table Support** - Export BelongsToMany pivot attributes

## Quick Navigation

### Getting Started
- [Getting Started](getting-started.md) - Installation, setup, and your first export
- [Configuration](configuration.md) - All configuration options explained

### Core Concepts
- [Database Schema](concepts/database-schema.md) - Tables, relationships, and UUIDs
- [Export Lifecycle](concepts/export-lifecycle.md) - How exports work end-to-end
- [Filter Architecture](concepts/filter-architecture.md) - Layout vs column filters

### Guides
- [Importing Models](guides/importing-models.md) - Model discovery and registration
- [Creating Layouts](guides/creating-layouts.md) - Layouts, columns, and value paths
- [Filtering Data](guides/filtering-data.md) - All filter types and operators
- [Sorting Data](guides/sorting-data.md) - Basic and relation-based sorting
- [Transformation Functions](guides/transformation-functions.md) - Data formatting
- [Aggregations](guides/aggregations.md) - Collection aggregation options
- [Nested Relationships](guides/nested-relationships.md) - Deep relationship traversal
- [Pivot Tables](guides/pivot-tables.md) - BelongsToMany pivot data
- [Large Datasets](guides/large-datasets.md) - Chunking, streaming, and background jobs

### Examples
**Basic**
- [Simple User Export](examples/basic/simple-user-export.md)
- [Filtered Export](examples/basic/filtered-export.md)
- [Formatted Export](examples/basic/formatted-export.md)

**Intermediate**
- [Related Data Export](examples/intermediate/related-data-export.md)
- [Dynamic Filters](examples/intermediate/dynamic-filters.md)
- [Aggregated Data](examples/intermediate/aggregated-data.md)

**Advanced**
- [Nested Relationships](examples/advanced/nested-relationships.md)
- [Collection Filtering](examples/advanced/collection-filtering.md)
- [Pivot Data Export](examples/advanced/pivot-data-export.md)
- [Multi-Format Export](examples/advanced/multi-format-export.md)

**Large Scale**
- [Sample Data Setup](examples/large-scale/sample-data.md)
- [Chunked Processing](examples/large-scale/chunked-processing.md)
- [Streaming Exports](examples/large-scale/streaming-exports.md)
- [Background Jobs](examples/large-scale/background-jobs.md)

### Reference
- [API Reference](reference/api.md) - All classes, methods, and parameters
- [Operators Reference](reference/operators.md) - Complete operator documentation
- [Functions Reference](reference/functions.md) - All transformation functions
- [Commands Reference](reference/commands.md) - Artisan commands

### Help
- [Troubleshooting](troubleshooting.md) - Common issues and solutions

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or higher
- Database with UUID support (MySQL 5.7+, PostgreSQL 9.4+, SQLite 3.8+)

## Quick Start

```bash
# Install the package
composer require hexagonlabsllc/laravel-exports

# Publish configuration and migrations
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider"

# Run migrations
php artisan migrate

# Import your models
php artisan export:import-models

# Seed transformation functions
php artisan export:seed-functions
```

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn};
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

// Create a layout
$userModel = ExportModel::where('title', 'User')->first();
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'title' => 'User Export',
]);

// Define columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 1,
]);

// Export data
$exportService = new DynamicExportService();
return $exportService->downloadAs($layout, 'csv', 'users.csv');
```

See the [Getting Started](getting-started.md) guide for a complete walkthrough.

## Architecture Overview

The package uses 7 interconnected database tables:

| Table | Purpose |
|-------|---------|
| `export_models` | Registered exportable Eloquent models |
| `export_model_relations` | Model columns and relationships |
| `export_layouts` | Named export configurations |
| `export_columns` | Output columns with transformations |
| `export_filters` | Query constraints and filters |
| `export_sorts` | Ordering configuration |
| `export_functions` | Reusable transformation functions |

All tables use UUIDs as primary keys for distributed system compatibility.

## Support

For issues and feature requests, visit the [GitHub repository](https://github.com/hexagonlabsllc/laravel-exports).
