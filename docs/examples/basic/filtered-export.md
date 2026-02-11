# Filtered Export

Export users with filters to include only specific records.

## Scenario

Export only active users who registered in 2024.

## Sample Data

```php
// users table
[
    ['id' => 1, 'name' => 'John', 'status' => 'active', 'created_at' => '2024-01-15'],
    ['id' => 2, 'name' => 'Jane', 'status' => 'inactive', 'created_at' => '2024-02-20'],
    ['id' => 3, 'name' => 'Bob', 'status' => 'active', 'created_at' => '2023-12-10'],
    ['id' => 4, 'name' => 'Alice', 'status' => 'active', 'created_at' => '2024-03-05'],
]
```

**Expected Result:** John and Alice (active AND registered in 2024)

## Setup

### 1. Create Layout and Columns

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFilter, ExportModelRelation};

$userModel = ExportModel::where('title', 'User')->first();

$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'title' => 'Active Users 2024',
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Status',
    'value_path' => 'status',
    'position' => 2,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Registered',
    'value_path' => 'created_at',
    'position' => 3,
]);
```

### 2. Add Static Filters

```php
// Get the status relation
$statusRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'status')
    ->first();

// Filter: status = 'active'
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'logical_operator' => 'AND',
]);

// Get the created_at relation
$createdAtRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'created_at')
    ->first();

// Filter: created_at between 2024-01-01 and 2024-12-31
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'value' => json_encode(['2024-01-01', '2024-12-31']),
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);
```

### 3. Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'active-users-2024.csv');
```

## Expected Output

```csv
Name,Status,Registered
John,active,2024-01-15 00:00:00
Alice,active,2024-03-05 00:00:00
```

## Request-Based Filters

Make filters dynamic so users can provide values:

```php
// Create a request-based date filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,       // Value comes from request
    'is_required' => false,     // Optional filter
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);
```

**Usage:**

```php
$requestData = [
    'created_at' => ['2024-01-01', '2024-06-30'],  // First half of 2024
];

$service->executeExport($layout, $requestData);
```

## Multiple Filter Operators

### Filter by Status List

```php
// Active OR pending users
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => 'in',
    'value' => json_encode(['active', 'pending']),
    'value_type' => 'array',
]);
```

### Exclude Deleted

```php
// Get deleted_at relation
$deletedAtRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'deleted_at')
    ->first();

// Only non-deleted (soft delete support)
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $deletedAtRelation->id,
    'operator' => 'null',
]);
```

### Pattern Matching

```php
// Only company emails
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $emailRelation->id,
    'operator' => 'like',
    'value' => '%@company.com',
    'value_type' => 'string',
]);
```

## Controller with Dynamic Filters

```php
public function export(Request $request)
{
    $layout = ExportLayout::where('title', 'Active Users 2024')->first();
    $service = new DynamicExportService();

    // Pass all request parameters
    return $service->downloadAs(
        $layout,
        'csv',
        'filtered-users.csv',
        $request->all()  // Includes filter values
    );
}
```

## Notes

- Static filters are always applied
- Request-based filters apply only when values are provided
- Required filters cause export to fail if missing
- Use `logical_operator` to combine filters with AND/OR
