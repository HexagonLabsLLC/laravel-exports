# Laravel Exports - Usage Examples

This document provides practical examples of using the Laravel Exports package.

## Table of Contents

1. [Basic Setup](#basic-setup)
2. [Importing Models](#importing-models)
3. [Creating Export Layouts](#creating-export-layouts)
4. [Defining Columns](#defining-columns)
5. [Applying Filters](#applying-filters)
6. [Sorting Data](#sorting-data)
7. [Using Transformation Functions](#using-transformation-functions)
8. [Exporting Data](#exporting-data)
9. [Advanced Examples](#advanced-examples)

## Basic Setup

### Installation

```bash
composer require hexlabs/laravel-exports
```

### Publish Configuration

```bash
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider" --tag="config"
```

### Run Migrations

```bash
php artisan migrate
```

### Seed Transformation Functions

```bash
php artisan export:seed-functions
```

## Importing Models

### Basic Import

Import all models from the default directory:

```php
# Import models with basic relationship discovery
php artisan export:import-models

# Import models without any relationship discovery
php artisan export:import-models --skip-relations
```

### Import with Deep Relationship Discovery

```php
# Discover nested relationships up to 2 levels deep (default)
php artisan export:import-models --deep

# Discover nested relationships up to 3 levels deep
php artisan export:import-models --deep --deep-level=3

# Example: This will discover relationships like:
# - user.posts (level 1)
# - user.posts.comments (level 2)
# - user.posts.comments.author (level 3, if --deep-level=3)
```

### Import from Custom Directory

```php
php artisan export:import-models --path=app/Domain/Models --namespace=App\\Domain\\Models
```

### Filter Specific Models

```php
# Basic filtering with relation sync
php artisan export:import-models --filter=*User*

# Filter with deep relationship discovery
php artisan export:import-models --filter=*User* --deep
```

## Creating Export Layouts

### Example: User Export Layout

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;

// Get the User model
$userModel = ExportModel::where('title', 'User')->first();

// Access the actual model instance if needed
$userModelInstance = $userModel->instance; // Returns App\Models\User class instance

// Create a layout for user exports
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'title' => 'Active Users Report',
    'description' => 'Export active users with their profile information',
]);
```

### Working with Model Relations

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;

// Find nested relationships using whereNested scope
$orderCustomerRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->whereNested('customer.contact')
    ->first();

// This finds the relation path: Order -> customer -> contact

// You can also search for deeper nested relationships
$deepRelation = ExportModelRelation::where('export_model_id', $laborPayModel->id)
    ->whereNested('workItem.workOrder.customer')
    ->first();
```

## Defining Columns

### Basic Columns

```php
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

// Direct model attributes
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Username',
    'value_path' => 'username',
    'position' => 2,
]);
```

### Related Model Columns

```php
// First, ensure relations are synced
$profileRelation = $userModel->relations()
    ->where('relation', 'profile')
    ->first();

// Add profile columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $profileRelation->id,
    'title' => 'Full Name',
    'value_path' => 'profile.full_name',
    'position' => 3,
]);

// Nested relations
$companyRelation = $userModel->relations()
    ->where('relation', 'profile.company')
    ->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $companyRelation->id,
    'title' => 'Company',
    'value_path' => 'profile.company.name',
    'position' => 4,
]);
```

### Columns with Transformation Functions

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;

// Get transformation functions
$formatDate = ExportFunction::where('name', 'Format Date')->first();
$uppercase = ExportFunction::where('name', 'Uppercase')->first();

// Date column with formatting
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Member Since',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => json_encode(['d/m/Y']), // Format parameter
    'position' => 5,
]);

// Text column with uppercase transformation
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Status',
    'value_path' => 'status',
    'export_function_id' => $uppercase->id,
    'position' => 6,
]);
```

### Columns with Aggregation

```php
// Count of user's posts
$postsRelation = $userModel->relations()
    ->where('relation', 'posts')
    ->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $postsRelation->id,
    'title' => 'Total Posts',
    'value_path' => 'posts',
    'aggregator' => 'count',
    'position' => 7,
]);

// Sum of order totals
$ordersRelation = $userModel->relations()
    ->where('relation', 'orders')
    ->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $ordersRelation->id,
    'title' => 'Total Spent',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'position' => 8,
]);
```

## Applying Filters

### Static Filters

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;

// Filter active users only
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'logical_operator' => 'AND',
]);
```

### Dynamic Filters (Request-based)

```php
// Filter by date range from request
$createdAtRelation = $userModel->relations()
    ->where('relation', 'created_at')
    ->first();

ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => false,
    'logical_operator' => 'AND',
]);
```

### Related Model Filters

```php
// Filter users by company
$companyRelation = $userModel->relations()
    ->where('relation', 'profile.company.name')
    ->first();

ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'export_model_relation_id' => $companyRelation->id,
    'operator' => 'like',
    'value' => '%Tech%',
    'value_type' => 'string',
    'logical_operator' => 'AND',
]);
```

### Advanced Operators

```php
// JSON contains operator
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'operator' => 'json_contains',
    'value' => json_encode(['role' => 'admin']),
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);

// Relation operator - users with verified emails
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'operator' => 'relation',
    'value' => json_encode(['profile', 'email_verified_at', 'not_null']),
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);
```

## Sorting Data

### Basic Sorting

```php
use HexagonLabsLLC\LaravelExports\Models\ExportSort;

// Sort by created date (newest first)
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'direction' => 'desc',
    'priority' => 1,
]);
```

### Related Column Sorting

```php
// Sort by company name
$companyNameRelation = $userModel->relations()
    ->where('relation', 'profile.company.name')
    ->first();

ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'export_model_relation_id' => $companyNameRelation->id,
    'direction' => 'asc',
    'priority' => 2,
]);
```

### Collection Sorting (by count)

```php
// Sort by number of posts
$postsRelation = $userModel->relations()
    ->where('relation', 'posts')
    ->first();

ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_id' => $userModel->id,
    'export_model_relation_id' => $postsRelation->id,
    'direction' => 'desc',
    'priority' => 3,
]);
```

## Using Transformation Functions

### Date Formatting

```php
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Registration Date',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => json_encode(['F j, Y']), // January 1, 2025
    'position' => 10,
]);
```

### String Manipulation

```php
$truncate = ExportFunction::where('name', 'Truncate')->first();
$mask = ExportFunction::where('name', 'Mask')->first();

// Truncate long descriptions
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Bio',
    'value_path' => 'profile.bio',
    'export_function_id' => $truncate->id,
    'export_function_values' => json_encode([100, '...']),
    'position' => 11,
]);

// Mask sensitive data
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Phone',
    'value_path' => 'profile.phone',
    'export_function_id' => $mask->id,
    'export_function_values' => json_encode([4, '*']), // 1234******
    'position' => 12,
]);
```

### Number Formatting

```php
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();
$percentage = ExportFunction::where('name', 'Percentage')->first();

// Format as currency
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $ordersRelation->id,
    'title' => 'Lifetime Value',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => json_encode(['USD', 'en_US']),
    'position' => 13,
]);

// Show completion percentage
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Profile Completion',
    'value_path' => 'profile.completion_score',
    'export_function_id' => $percentage->id,
    'export_function_values' => json_encode([1]), // 1 decimal place
    'position' => 14,
]);
```

## Exporting Data

### Basic Export

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$exportService = new DynamicExportService();

// Export to CSV
$csvContent = $exportService->exportTo($layout, 'csv');

// Export to JSON
$jsonContent = $exportService->exportTo($layout, 'json');
```

### Export with Request Parameters

```php
// With dynamic filters
$requestData = [
    'created_at' => ['2025-01-01', '2025-12-31'], // For between filter
    'status' => 'active',
];

$data = $exportService->executeExport($layout, $requestData);
```

### Override Column Defaults via Request

You can override column default values at runtime by passing a `defaults` array keyed by column UUID:

```php
// Get column IDs from the layout
$layout = ExportLayout::with('columns')->find($layoutId);
$customerNameColumn = $layout->columns->where('title', 'Customer Name')->first();
$statusColumn = $layout->columns->where('title', 'Status')->first();

// Execute export with default overrides
$requestData = [
    'defaults' => [
        $customerNameColumn->id => 'Unknown Customer',
        $statusColumn->id => 'N/A',
    ],
    // Other filters...
    'date_range' => ['2025-01-01', '2025-12-31'],
];

$data = $exportService->executeExport($layout, $requestData);
```

Request-based defaults take priority over the static `default` field configured on the column. This is useful when:
- Different API consumers need different fallback values
- Default values depend on request context (e.g., locale, user preferences)
- You want to override defaults without modifying the export configuration

### Force Column Values with Overrides

Unlike defaults (which only apply when values are empty), **overrides always replace** the column value regardless of what data exists:

```php
$layout = ExportLayout::with('columns')->find($layoutId);
$customerColumn = $layout->columns->where('title', 'Customer Name')->first();
$statusColumn = $layout->columns->where('title', 'Status')->first();

$requestData = [
    'overrides' => [
        $customerColumn->id => 'REDACTED',    // Always shows "REDACTED"
        $statusColumn->id => 'Processing',     // Always shows "Processing"
    ],
];

$data = $exportService->executeExport($layout, $requestData);
```

You can combine defaults and overrides:

```php
$requestData = [
    // Use this when customer name is missing
    'defaults' => [
        $customerColumn->id => 'Unknown Customer',
    ],
    // Always use this for status (regardless of actual value)
    'overrides' => [
        $statusColumn->id => 'Processing',
    ],
];
```

**Key differences:**
| Feature | When Applied | Use Case |
|---------|--------------|----------|
| `defaults` | Only when extracted value is null/empty | Fallback for missing data |
| `overrides` | Always, replaces any extracted value | Force specific values regardless of data |

### Download Response

```php
// In a controller
public function exportUsers(Request $request)
{
    $layout = ExportLayout::find($request->layout_id);
    $exportService = new DynamicExportService();
    
    return $exportService->downloadAs(
        $layout,
        'csv',
        'users-export.csv',
        $request->all()
    );
}
```

### Streaming Large Exports

```php
// For large datasets
return $exportService->streamAs(
    $layout,
    'csv',
    'large-export.csv',
    $request->all(),
    ['delimiter' => ','],
    1000 // Chunk size
);
```

### Paginated Export

```php
$result = $exportService->executeExportPaginated(
    $layout,
    $request->all(),
    100, // Per page
    $request->get('page', 1)
);

// Returns:
// [
//     'data' => [...],
//     'meta' => [
//         'current_page' => 1,
//         'last_page' => 10,
//         'per_page' => 100,
//         'total' => 1000,
//         'from' => 1,
//         'to' => 100
//     ]
// ]
```

## Advanced Examples

### Product Catalog Export

```php
// Import product model
php artisan export:import-models --filter=*Product* --sync-relations

// Create layout
$productModel = ExportModel::where('title', 'Product')->first();

$catalogLayout = ExportLayout::create([
    'export_model_id' => $productModel->id,
    'title' => 'Product Catalog Export',
]);

// Add columns with various features
$columns = [
    ['title' => 'SKU', 'value_path' => 'sku', 'position' => 1],
    ['title' => 'Name', 'value_path' => 'name', 'position' => 2],
    ['title' => 'Category', 'value_path' => 'category.name', 'position' => 3],
    ['title' => 'Price', 'value_path' => 'price', 'position' => 4, 'function' => 'Format Currency'],
    ['title' => 'Stock', 'value_path' => 'inventory.quantity', 'position' => 5],
    ['title' => 'Status', 'value_path' => 'is_active', 'position' => 6, 'function' => 'Boolean Text'],
];

// Add filters
ExportFilter::create([
    'export_layout_id' => $catalogLayout->id,
    'export_model_id' => $productModel->id,
    'operator' => '=',
    'value' => true,
    'value_type' => 'boolean',
    'logical_operator' => 'AND',
]);

// Sort by category then name
ExportSort::create([
    'export_layout_id' => $catalogLayout->id,
    'export_model_relation_id' => $categoryRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);
```

### Sales Report with Aggregations

```php
$orderModel = ExportModel::where('title', 'Order')->first();

$salesLayout = ExportLayout::create([
    'export_model_id' => $orderModel->id,
    'title' => 'Monthly Sales Report',
]);

// Columns with aggregations
ExportColumn::create([
    'export_layout_id' => $salesLayout->id,
    'title' => 'Order Date',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => json_encode(['Y-m']),
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $salesLayout->id,
    'title' => 'Customer',
    'value_path' => 'customer.name',
    'position' => 2,
]);

ExportColumn::create([
    'export_layout_id' => $salesLayout->id,
    'title' => 'Items Count',
    'value_path' => 'order_items',
    'aggregator' => 'count',
    'position' => 3,
]);

ExportColumn::create([
    'export_layout_id' => $salesLayout->id,
    'title' => 'Total Amount',
    'value_path' => 'order_items.subtotal',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'position' => 4,
]);

// Filter by date range
ExportFilter::create([
    'export_layout_id' => $salesLayout->id,
    'export_model_id' => $orderModel->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => true,
    'logical_operator' => 'AND',
]);
```

### Multi-Format Export Endpoint

```php
// In a controller
public function export(Request $request)
{
    $validated = $request->validate([
        'layout_id' => 'required|exists:export_layouts,id',
        'format' => 'required|in:csv,json',
        'filters' => 'array',
    ]);
    
    $layout = ExportLayout::findOrFail($validated['layout_id']);
    $exportService = new DynamicExportService();
    
    $filename = Str::slug($layout->title) . '-' . now()->format('Y-m-d');
    
    return $exportService->downloadAs(
        $layout,
        $validated['format'],
        $filename . '.' . $validated['format'],
        $validated['filters'] ?? []
    );
}
```

### Dynamic Relationship Discovery

```php
// Discover and validate nested relationships
$laborPayModel = ExportModel::where('title', 'LaborPay')->first();

// Access the actual model instance to inspect available methods
$modelInstance = $laborPayModel->instance;

// Find a deeply nested relationship using whereNested
$customerRelation = ExportModelRelation::where('export_model_id', $laborPayModel->id)
    ->whereNested('workItem.workOrder.customer')
    ->first();

if ($customerRelation) {
    // Create a column for the nested customer name
    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $customerRelation->id,
        'title' => 'Customer Name',
        'value_path' => 'workItem.workOrder.customer.name',
        'position' => 10,
    ]);
}

// Find all relations that go through workItem
$workItemRelations = ExportModelRelation::where('export_model_id', $laborPayModel->id)
    ->whereNested('workItem')
    ->get();
```

## Tips and Best Practices

1. **Performance**: Use eager loading by syncing relations with `--sync-relations`
2. **Memory**: For large exports, use `streamAs()` instead of `exportTo()`
3. **Security**: Use column-specific filters for row-level security
4. **Flexibility**: Combine transformation functions for complex data formatting
5. **Reusability**: Create multiple layouts for different export scenarios
6. **Testing**: Test exports with small datasets before running on production data

## Troubleshooting

### Common Issues

1. **Missing Relations**: Run `php artisan export:import-models --sync-relations`
2. **Function Not Found**: Run `php artisan export:seed-functions`
3. **Memory Exhausted**: Use streaming exports for large datasets
4. **Slow Exports**: Add indexes to filtered columns, use eager loading

### Debug Mode

```php
// Get the query for debugging
$query = $exportService->getQuery($layout, $requestData);
dd($query->toSql(), $query->getBindings());
```