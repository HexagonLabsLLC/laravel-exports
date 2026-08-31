# Filtering Data

Filters constrain which records appear in your export. This guide covers all filter types and operators.

## Filter Types

### Static Filters

Fixed filters defined in the database:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;

// Get the status column relation
$statusRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'status')
    ->first();

// Only export active users
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'logical_operator' => 'AND',
]);
```

### Request-Based Filters

Dynamic filters that get values from request data:

```php
// Date range filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,         // Get value from request
    'is_required' => true,        // Export fails without it
    'value_type' => 'date',
    'logical_operator' => 'AND',
]);
```

**Usage:**

```php
$requestData = [
    'created_at' => ['2024-01-01', '2024-12-31'],
];
$service->executeExport($layout, $requestData);
```

## Filter Operators

### Equality

```php
// Equals
ExportFilter::create([
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
]);

// Not equals
ExportFilter::create([
    'operator' => '!=',
    'value' => 'deleted',
    'value_type' => 'string',
]);
```

### Comparison

```php
// Greater than
ExportFilter::create([
    'operator' => '>',
    'value' => 100,
    'value_type' => 'number',
]);

// Less than or equal
ExportFilter::create([
    'operator' => '<=',
    'value' => 50,
    'value_type' => 'number',
]);

// Greater than or equal
ExportFilter::create([
    'operator' => '>=',
    'value' => 18,
    'value_type' => 'number',
]);

// Less than
ExportFilter::create([
    'operator' => '<',
    'value' => 100,
    'value_type' => 'number',
]);
```

### Array Operations

```php
// Value in array
ExportFilter::create([
    'operator' => 'in',
    'value' => json_encode(['active', 'pending', 'approved']),
    'value_type' => 'array',
]);

// Value not in array
ExportFilter::create([
    'operator' => 'not_in',
    'value' => json_encode(['deleted', 'banned']),
    'value_type' => 'array',
]);

// Value between two values
ExportFilter::create([
    'operator' => 'between',
    'value' => json_encode(['2024-01-01', '2024-12-31']),
    'value_type' => 'array',
]);
```

### Pattern Matching

```php
// SQL LIKE pattern
ExportFilter::create([
    'operator' => 'like',
    'value' => '%@company.com',
    'value_type' => 'string',
]);
```

### Null Checks

```php
// Is null
ExportFilter::create([
    'operator' => 'null',
    // value is ignored
]);

// Is not null
ExportFilter::create([
    'operator' => 'not_null',
    // value is ignored
]);
```

### JSON Operations

```php
// JSON contains
ExportFilter::create([
    'operator' => 'json_contains',
    'value' => json_encode(['role' => 'admin']),
    'value_type' => 'array',
]);
```

### Collection Filtering

```php
// Filter eager-loaded collections
ExportFilter::create([
    'operator' => 'relation',
    'value' => 'Container',  // Filter items where related field = value
    'value_type' => 'string',
]);
```

## Logical Operators

Combine filters with AND/OR:

```php
// Filter 1: status = 'active' AND
$filter1 = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',
    'logical_operator' => 'AND',
]);

// Filter 2: OR status = 'pending'
$filter2 = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'pending',
    'logical_operator' => 'OR',
]);
```

**Result:** Exports users where status is 'active' OR 'pending'

## Required vs Optional Filters

### Required Filters

The export fails if the value is not provided:

```php
ExportFilter::create([
    'is_request' => true,
    'is_required' => true,    // Must be provided
]);
```

### Optional Filters

The filter is skipped if the value is not provided:

```php
ExportFilter::create([
    'is_request' => true,
    'is_required' => false,   // Skip if not provided
]);
```

## Value Types

Specify the expected value type:

| Type | Description | Example |
|------|-------------|---------|
| `string` | Text value | `"active"` |
| `number` | Numeric value | `100` |
| `boolean` | True/false | `true` |
| `array` | JSON array | `["a", "b"]` |
| `date` | Date string | `"2024-01-01"` |

## Request Parameter Matching

The system matches request parameters flexibly:

```php
// Filter configured for 'created_at'

// All of these work:
$requestData = ['created_at' => ['2024-01-01', '2024-12-31']];
$requestData = ['createdat' => ['2024-01-01', '2024-12-31']];
$requestData = ['CREATED_AT' => ['2024-01-01', '2024-12-31']];
```

For nested relations:

```php
// Filter for 'workOrder.invoice.custom_id'

$requestData = ['workOrder.invoice.custom_id' => 'value'];
$requestData = ['work_order.invoice.custom_id' => 'value'];
$requestData = ['workOrder_invoice_custom_id' => 'value'];
```

## Filtering Related Data

### Filter by Related Column

```php
// Get nested relation
$companyNameRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->whereNested('profile.company.name')
    ->first();

// Filter users by company name
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $companyNameRelation->id,
    'operator' => 'like',
    'value' => '%Tech%',
    'value_type' => 'string',
]);
```

### Smart Nested Filters

For nested columns, the system automatically parses the path:

```php
// Create relation marked as column
$invoiceIdRelation = ExportModelRelation::create([
    'export_model_id' => $workItemModel->id,
    'relation' => 'workOrder.invoice.custom_id',
    'is_column' => true,  // Mark as column, not relation
]);

// Create request filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $invoiceIdRelation->id,
    'operator' => 'in',
    'is_request' => true,
    'value_type' => 'array',
]);
```

**Request:**

```php
$requestData = [
    'workOrder.invoice.custom_id' => ['INV001', 'INV002'],
];
```

## Column-Specific Filters

Attach filters to columns for collection filtering:

```php
// Create filter for identifier type
$typeFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifierTypeRelation->id,
    'operator' => 'relation',
    'value' => 'Container',
]);

// Attach to column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $typeFilter->id,  // Link filter
    'title' => 'Container ID',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'position' => 1,
]);
```

This extracts the value from the first identifier where type = 'Container'.

## Common Filter Patterns

### Date Range

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => true,
    'value_type' => 'date',
]);

// Usage
$service->executeExport($layout, [
    'created_at' => ['2024-01-01', '2024-12-31'],
]);
```

### Status Filter

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => 'in',
    'is_request' => true,
    'is_required' => false,  // Optional
    'value_type' => 'array',
]);

// Usage - array or comma-separated
$service->executeExport($layout, ['status' => ['active', 'pending']]);
$service->executeExport($layout, ['status' => 'active,pending']);
```

### Verified Users Only

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $emailVerifiedAtRelation->id,
    'operator' => 'not_null',
]);
```

### Exclude Deleted

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $deletedAtRelation->id,
    'operator' => 'null',
]);
```

### Minimum Order Value

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $orderTotalRelation->id,
    'operator' => '>=',
    'value' => 100,
    'value_type' => 'number',
]);
```

## Debugging Filters

Use `getQuery()` to inspect the query with all filters applied:

```php
$query = $service->getQuery($layout, $requestData);
dd($query->toSql(), $query->getBindings());
```

Errors and warnings are still written to the log.

## Best Practices

1. **Use Required Sparingly**: Make most filters optional for flexibility
2. **Provide Defaults**: Consider static fallback values
3. **Document Parameters**: Keep track of expected request keys
4. **Test Edge Cases**: Empty arrays, null values, etc.
5. **Use Appropriate Types**: Match value_type to actual data

## Related Documentation

- [Filter Architecture](../concepts/filter-architecture.md) - Deep dive into filtering
- [Operators Reference](../reference/operators.md) - Complete operator list
- [Collection Filtering Example](../examples/advanced/collection-filtering.md)
