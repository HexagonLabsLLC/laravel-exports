# Laravel Exports

A powerful, database-driven export system for Laravel applications that provides dynamic, configurable exports without writing code.

## Features

- 📊 **Database-Driven Configuration** - Define exports through database records, not code
- 🔍 **Dynamic Model Discovery** - Auto-import Eloquent models and their relationships with proper validation
- 🚀 **Advanced Filtering** - Static filters, request-based filters, and collection filters with relation operators
- 🔗 **Nested Relationship Support** - Export deeply nested data
- 🎯 **Collection Filtering** - Filter related collections by specific criteria (e.g., tags by category)
- ⚡ **Transformation Functions** - 22+ built-in functions for formatting dates, strings, numbers, etc.
- 📈 **Aggregations** - Sum, count, average, min, max on collections
- 🚄 **Performance Optimized** - Smart eager loading, chunking, and streaming for large datasets
- 📝 **Multiple Formats** - CSV and JSON out of the box, extensible for more
- 🐛 **Debug-Ready** - Comprehensive validation and debugging capabilities

## Quick Start

### Installation

```bash
composer require hexlabs/laravel-exports
```

### Setup

```bash
# Publish configuration
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider"

# Run migrations
php artisan migrate

# Import your models
php artisan export:import-models --sync-relations

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
    'title' => 'User Export',
]);

// 2. Define columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Created',
    'value_path' => 'created_at',
    'position' => 2,
]);

// 3. Export data
$exportService = new DynamicExportService();
return $exportService->downloadAs($layout, 'csv', 'users.csv');
```

## Advanced Features

### Nested Relationship Traversal

Export data from deeply nested relationships using dot notation:

```php
// Export customer name through multiple relationships
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer Name',
    'value_path' => 'workItem.workOrder.customer.contact.org_name',
    'position' => 3,
]);

// Export user name through simple relationship
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Assigned User',
    'value_path' => 'user.name',
    'position' => 4,
]);
```

### Collection Filtering

Filter collections to extract specific items based on related criteria:

```php
// Create filters for specific identifier types
$containerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $identifierModel->id,
    'export_model_relation_id' => $typeRelation->id, // Points to type.title
    'operator' => 'relation',
    'value' => 'Container',
]);

// Create column that shows only Container identifier values
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $containerFilter->id,
    'title' => 'Container ID',
    'value_path' => 'workItem.identifiers.value',
    'default' => '0', // Show 0 if no Container identifier found
    'position' => 5,
]);
```

### Transformation Functions

Apply formatting to your data:

```php
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Joined Date',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => json_encode(['F j, Y']), // January 1, 2025
    'position' => 6,
]);
```

### Request-Based Filtering

Add dynamic filters that users can control:

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => false,
    'request_key' => 'date_range', // Key to look for in request data
]);

// In your controller
$exportService->downloadAs($layout, 'csv', 'filtered-users.csv', [
    'date_range' => ['2025-01-01', '2025-12-31']
]);
```

### Performance Optimization

For memory-efficient exports of large datasets:

```php
// Streaming export with chunking
return $exportService->streamAs(
    $layout,
    'csv',
    'large-export.csv',
    $requestData,
    ['delimiter' => ','],
    1000 // Chunk size
);

// Export with custom eager loading
$exportService = new DynamicExportService();
$exportService->loadLayout($layout);
// The service automatically optimizes eager loading based on your column configurations
```

## Architecture

### Database Schema

The package uses 7 interconnected tables (all with UUID primary keys):

- **export_models** - Registered exportable Eloquent models
- **export_model_relations** - Model columns and relationships (supports dot notation)
- **export_layouts** - Named export configurations
- **export_columns** - Output columns with transformations and filters
- **export_filters** - Query constraints (layout-level and column-level)
- **export_sorts** - Ordering configuration
- **export_functions** - Reusable transformation functions

### Filter Architecture

The package supports three types of filters:

1. **Layout Filters** - Applied to the main query as WHERE conditions
2. **Column Filters (Regular)** - Applied to main query for specific columns
3. **Column Filters (Relation)** - Used only for constraining eager-loaded collections

### Key Services

- **DynamicExportService** - Main export execution engine with smart relation handling
- **ExportInspector** - Validates configurations and syncs model relationships
- **ModelRelationInspector** - Discovers model columns and relationships using reflection

## Commands

### Import Models

```bash
# Basic usage - scans app/Models directory
php artisan export:import-models

# Scan custom directory with namespace
php artisan export:import-models --path=app/Domain/Models --namespace=App\\Domain\\Models

# Force re-import existing models
php artisan export:import-models --force

# Import models without syncing relations
php artisan export:import-models --skip-relations

# Filter models by pattern
php artisan export:import-models --filter=*User*

# Omit specific models from relation inspection
php artisan export:import-models --omit=User,Post
```

### Seed Functions

```bash
# First time seeding
php artisan export:seed-functions

# Update existing functions
php artisan export:seed-functions --force
```

## Debugging

Enable debug mode to get detailed logging:

```php
// In your .env file
APP_DEBUG=true

// The service will automatically log:
// - Column processing details
// - Relation loading status
// - Query execution information
// - Collection filtering results
```

## Testing

```bash
# Run all tests
./vendor/bin/pest

# Run specific test suites
./vendor/bin/pest tests/Unit/Exports/Handlers/
./vendor/bin/pest tests/Feature/Services/
```

## Requirements

- PHP 8.1+
- Laravel 10.0+
- Database with UUID support (MySQL 5.7+, PostgreSQL 9.4+, SQLite 3.8+)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.