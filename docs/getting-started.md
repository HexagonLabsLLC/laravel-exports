# Getting Started

This guide walks you through installing Laravel Exports and creating your first export.

## Requirements

- PHP 8.2 or higher
- Laravel 12.12+ or 13
- Database with UUID support:
  - MySQL 5.7+
  - PostgreSQL 9.4+
  - SQLite 3.8+

## Installation

Install the package via Composer:

```bash
composer require hexagonlabsllc/laravel-exports
```

## Setup

### 1. Publish Configuration and Migrations

```bash
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider"
```

This publishes:
- `config/laravel-exports.php` - Package configuration
- Database migrations for the 7 export tables

### 2. Run Migrations

```bash
php artisan migrate
```

This creates the following tables:
- `export_models`
- `export_model_relations`
- `export_layouts`
- `export_columns`
- `export_filters`
- `export_sorts`
- `export_functions`

### 3. Import Your Models

Discover and register your Eloquent models:

```bash
php artisan export:import-models
```

This scans your `app/Models` directory and registers each model in the `export_models` table. It also discovers columns and relationships for each model.

**Options:**

```bash
# Scan a custom directory
php artisan export:import-models --path=app/Domain/Models --namespace=App\\Domain\\Models

# Discover nested relationships (e.g., user.posts.comments)
php artisan export:import-models --deep

# Control nesting depth (default: 2, max: 5)
php artisan export:import-models --deep --deep-level=3

# Force re-import existing models
php artisan export:import-models --force

# Filter by pattern
php artisan export:import-models --filter=*User*
```

### 4. Seed Transformation Functions

Add the 23 built-in transformation functions:

```bash
php artisan export:seed-functions
```

## Your First Export

Let's create a simple user export.

### Step 1: Create a Layout

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;

// Get the User export model
$userModel = ExportModel::where('title', 'User')->first();

// Create an export layout
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'active_users_export',
    'title' => 'Active Users Export',
    'description' => 'Export all active users',
]);
```

### Step 2: Define Columns

```php
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

// Name column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'position' => 1,
]);

// Email column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 2,
]);

// Created date column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Joined',
    'value_path' => 'created_at',
    'position' => 3,
]);
```

### Step 3: Execute the Export

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$exportService = new DynamicExportService();

// Get data as a collection
$data = $exportService->executeExport($layout);

// Or download as CSV
return $exportService->downloadAs($layout, 'csv', 'users.csv');

// Or download as JSON
return $exportService->downloadAs($layout, 'json', 'users.json');
```

## Adding Filters

Filter the exported data:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;

// Get the status relation (column)
$statusRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'status')
    ->first();

// Add a static filter for active users only
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'logical_operator' => 'AND',
]);
```

## Adding Sorting

Sort the exported data:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportSort;

// Get the created_at relation
$createdAtRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'created_at')
    ->first();

// Sort by created_at descending (newest first)
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'direction' => 'desc',
    'priority' => 1,
]);
```

## Adding Transformations

Format column values with transformation functions:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;

// Get the Format Date function
$formatDate = ExportFunction::where('name', 'Format Date')->first();

// Update the Joined column to format the date
$joinedColumn = ExportColumn::where('export_layout_id', $layout->id)
    ->where('title', 'Joined')
    ->first();

$joinedColumn->update([
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'F j, Y'], // "January 1, 2025"
]);
```

## Controller Example

Here's a complete controller example:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

class ExportController extends Controller
{
    public function download(Request $request, string $layoutId)
    {
        $validated = $request->validate([
            'format' => 'in:csv,json',
        ]);

        $layout = ExportLayout::findOrFail($layoutId);
        $format = $validated['format'] ?? 'csv';
        $filename = str($layout->title)->slug() . '.' . $format;

        $exportService = new DynamicExportService();

        return $exportService->downloadAs(
            $layout,
            $format,
            $filename,
            $request->all() // Pass request data for dynamic filters
        );
    }
}
```

## Next Steps

Now that you have a basic export working, explore:

- [Creating Layouts](guides/creating-layouts.md) - Advanced column configuration
- [Filtering Data](guides/filtering-data.md) - All filter types and operators
- [Transformation Functions](guides/transformation-functions.md) - Format your data
- [Nested Relationships](guides/nested-relationships.md) - Export related model data
- [Large Datasets](guides/large-datasets.md) - Handle exports with millions of rows
