# Creating Layouts

Export layouts define what data to export and how it should be formatted. This guide covers creating layouts and configuring columns.

## Creating a Layout

A layout is tied to a specific model and contains columns, filters, and sorts.

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;

// Get the model you want to export
$userModel = ExportModel::where('title', 'User')->first();

// Create a layout
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'active_users_report',
    'title' => 'Active Users Report',
    'description' => 'Export active users with their profile information',
]);
```

## Defining Columns

Columns define what data appears in your export.

### Basic Column

```php
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',           // Column header
    'value_path' => 'email',      // Model attribute
    'position' => 1,              // Display order
]);
```

### Column Properties

| Property | Type | Description |
|----------|------|-------------|
| `title` | string | Column header in export |
| `value_path` | string | Dot notation path to value |
| `position` | integer | Display order (1 = first) |
| `default` | string | Value when data is null/empty |
| `omit_on_empty` | boolean | Output an empty string when the value is empty (keeps CSV columns aligned) |
| `export_model_relation_id` | uuid | Link to relation (optional) |
| `export_function_id` | uuid | Transformation function |
| `export_function_values` | json | Positional function parameters (`null` in the value slot) |
| `export_filter_id` | uuid | Column-specific filter |
| `aggregator` | enum | Aggregation for collections |

## Value Paths

The `value_path` property uses dot notation to access data.

### Direct Attributes

```php
// Model attribute
'value_path' => 'email'

// Another attribute
'value_path' => 'name'
```

### Related Model Attributes

```php
// BelongsTo relationship
'value_path' => 'profile.bio'

// Nested relationship
'value_path' => 'company.address.city'
```

### Collection Attributes

```php
// HasMany relationship
'value_path' => 'posts'              // Returns collection

// Specific attribute from collection
'value_path' => 'posts.title'        // Needs aggregator
```

## Linking to Relations

For better validation and auto-complete, link columns to relations:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;

// Get the profile relation
$profileRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'profile')
    ->first();

// Create column with relation link
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $profileRelation->id,
    'title' => 'Bio',
    'value_path' => 'profile.bio',
    'position' => 1,
]);
```

## Default Values

Provide fallback values for empty data:

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Phone',
    'value_path' => 'profile.phone',
    'default' => 'N/A',           // Shown when phone is null
    'position' => 1,
]);
```

### Runtime Default Overrides

Override defaults at runtime via request data:

```php
$requestData = [
    'defaults' => [
        $phoneColumn->id => 'Not Provided',  // Overrides 'N/A'
    ],
];
$service->executeExport($layout, $requestData);
```

## Empty Column Values

Output an empty string when the value is empty (the key stays in the row, keeping columns aligned):

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Middle Name',
    'value_path' => 'middle_name',
    'omit_on_empty' => true,      // Empty string if empty
    'position' => 1,
]);
```

## Transformation Functions

Apply formatting to column values:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;

// Get a transformation function
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Joined',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'F j, Y'],  // "January 1, 2025"
    'position' => 1,
]);
```

See [Transformation Functions](transformation-functions.md) for all available functions.

## Aggregations

Aggregate collection values:

```php
// Count posts
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Post Count',
    'value_path' => 'posts',
    'aggregator' => 'count',
    'position' => 1,
]);

// Sum order totals
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Spent',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'position' => 2,
]);
```

See [Aggregations](aggregations.md) for all options.

## Overriding Values

Force specific values regardless of data:

```php
$requestData = [
    'overrides' => [
        $statusColumn->id => 'Processing',  // Always shows this
    ],
];
$service->executeExport($layout, $requestData);
```

**Defaults vs Overrides:**
- `defaults` - Only used when value is empty
- `overrides` - Always replaces the value

## Complete Column Example

```php
// Get related function and relation
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();
$ordersRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'orders')
    ->first();

// Create a fully configured column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $ordersRelation->id,
    'title' => 'Total Revenue',
    'value_path' => 'orders.amount',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'default' => '$0.00',
    'position' => 5,
]);
```

## Creating Multiple Layouts

One model can have multiple layouts for different purposes:

```php
// Detailed user report
$detailedLayout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'detailed_user_report',
    'title' => 'Detailed User Report',
    'description' => 'All user fields with related data',
]);

// Compact user list
$compactLayout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'user_list',
    'title' => 'User List',
    'description' => 'Simple name and email list',
]);

// Admin export with sensitive data
$adminLayout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'admin_user_export',
    'title' => 'Admin User Export',
    'description' => 'Complete user data including sensitive fields',
]);
```

## Layout Management

### Find a Layout

```php
// By title
$layout = ExportLayout::where('title', 'Active Users Report')->first();

// By ID
$layout = ExportLayout::find($layoutId);

// With columns
$layout = ExportLayout::with('columns')->find($layoutId);
```

### Update a Layout

```php
$layout->update([
    'title' => 'Updated Title',
    'description' => 'New description',
]);
```

### Delete a Layout

```php
// This also deletes related columns, filters, and sorts
$layout->delete();
```

### Clone a Layout

```php
// Clone the layout
$newLayout = $layout->replicate();
$newLayout->title = 'Copy of ' . $layout->title;
$newLayout->save();

// Clone all columns
foreach ($layout->columns as $column) {
    $newColumn = $column->replicate();
    $newColumn->export_layout_id = $newLayout->id;
    $newColumn->save();
}
```

## Column Ordering

Columns appear in order by `position`:

```php
// First column
ExportColumn::create([..., 'position' => 1]);

// Second column
ExportColumn::create([..., 'position' => 2]);

// Third column
ExportColumn::create([..., 'position' => 3]);
```

### Reordering Columns

```php
$columns = ExportColumn::where('export_layout_id', $layout->id)
    ->orderBy('position')
    ->get();

// Swap positions
$columns[0]->update(['position' => 2]);
$columns[1]->update(['position' => 1]);
```

## Using Layouts

### Execute Export

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();

// Get data as collection
$data = $service->executeExport($layout);

// Download as CSV
return $service->downloadAs($layout, 'csv', 'export.csv');

// Download as JSON
return $service->downloadAs($layout, 'json', 'export.json');
```

### With Request Data

```php
$requestData = [
    'date_range' => ['2024-01-01', '2024-12-31'],
    'status' => 'active',
];

$data = $service->executeExport($layout, $requestData);
```

## Best Practices

1. **Use Descriptive Titles**: Make layout names clear and specific
2. **Link to Relations**: Connect columns to `export_model_relation_id` for validation
3. **Order Logically**: Position important columns first
4. **Set Defaults**: Always provide defaults for nullable fields
5. **Use Functions**: Apply formatting at export time, not in database
6. **Create Purpose-Specific Layouts**: Different layouts for different audiences

## Related Documentation

- [Filtering Data](filtering-data.md) - Add filters to layouts
- [Sorting Data](sorting-data.md) - Add sorting to layouts
- [Transformation Functions](transformation-functions.md) - Format column values
- [Aggregations](aggregations.md) - Aggregate collection values
