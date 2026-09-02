# Filter Architecture

Laravel Exports has a sophisticated filtering system that supports static filters, dynamic request-based filters, and collection filters. Understanding how these work together is essential for building complex exports.

## Filter Types

### 1. Layout Filters

Layout filters are attached to the export layout and affect the main query. They constrain which records are exported.

```php
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

**SQL Result:**
```sql
SELECT * FROM users WHERE status = 'active'
```

### 2. Column Filters (Regular Operators)

Column filters with regular operators (=, !=, >, <, etc.) are also applied to the main query. They filter which records appear based on column values.

```php
// Create a filter
$filter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $verifiedRelation->id,
    'operator' => 'not_null',
]);

// Attach to a column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $filter->id,
    'title' => 'Verified Email',
    'value_path' => 'email_verified_at',
    'position' => 1,
]);
```

### 3. Column Filters (Relation Operator)

Filters using the `relation` operator are special. They don't affect the main query. Instead, they constrain **eager-loaded collections**. This lets you extract specific items from a collection.

```php
// Filter to get only "Container" type identifiers
$filter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifierTypeRelation->id,
    'operator' => 'relation',
    'value' => 'Container',  // Filter identifier.type.title = 'Container'
]);

// Column that shows Container identifier values
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $filter->id,
    'title' => 'Container ID',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'position' => 1,
]);
```

**How it works:**
1. All identifiers for each record are eager loaded
2. When extracting the column value, only identifiers matching the filter are considered
3. The `first` aggregator gets the first matching identifier's value

## Filter Operators

### Comparison Operators

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `=` | Equals | `"active"` |
| `!=` | Not equals | `"deleted"` |
| `>` | Greater than | `100` |
| `<` | Less than | `50` |
| `>=` | Greater than or equal | `18` |
| `<=` | Less than or equal | `65` |

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $ageRelation->id,
    'operator' => '>=',
    'value' => 18,
    'value_type' => 'integer',
]);
```

### Array Operators

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `in` | Value in array | `["active", "pending"]` |
| `not_in` | Value not in array | `["deleted", "banned"]` |
| `between` | Value between two values | `["2024-01-01", "2024-12-31"]` |

```php
// In operator
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => 'in',
    'value' => json_encode(['active', 'pending']),
    'value_type' => 'array',
]);

// Between operator
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $dateRelation->id,
    'operator' => 'between',
    'value' => json_encode(['2024-01-01', '2024-12-31']),
    'value_type' => 'array',
]);
```

### Pattern Operators

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `like` | SQL LIKE pattern | `"%@gmail.com"` |

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $emailRelation->id,
    'operator' => 'like',
    'value' => '%@company.com',
    'value_type' => 'string',
]);
```

### Null Operators

| Operator | Description | Example Value |
|----------|-------------|---------------|
| `null` | Is null | (value ignored) |
| `not_null` | Is not null | (value ignored) |

```php
// Check for verified users
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $verifiedAtRelation->id,
    'operator' => 'not_null',
]);
```

### Special Operators

| Operator | Description | Use Case |
|----------|-------------|----------|
| `json_contains` | JSON field contains value | JSONB columns |
| `relation` | Filter eager-loaded collections | Collection filtering |

```php
// JSON contains
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $settingsRelation->id,
    'operator' => 'json_contains',
    'value' => json_encode(['notifications' => true]),
    'value_type' => 'array',
]);
```

## Static vs. Request-Based Filters

### Static Filters

Static filters have a fixed value defined in the database:

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',          // Fixed value
    'value_type' => 'string',
    'is_request' => false,        // Static
]);
```

### Request-Based Filters

Request-based filters get their value from the request data:

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $dateRelation->id,
    'operator' => 'between',
    'value' => null,              // No static value
    'value_type' => 'array',
    'is_request' => true,         // From request
    'is_required' => true,        // Must be provided
]);
```

**Usage:**

```php
$requestData = [
    'created_at' => ['2024-01-01', '2024-12-31'],
];
$service->executeExport($layout, $requestData);
```

### Required vs. Optional

- `is_required = true`: Export fails if value not provided
- `is_required = false`: Filter is skipped if value not provided

```php
// Required filter - export fails without it
ExportFilter::create([
    'is_request' => true,
    'is_required' => true,
]);

// Optional filter - skipped if not provided
ExportFilter::create([
    'is_request' => true,
    'is_required' => false,
]);
```

## Logical Operators

Filters apply in creation order. An `or` filter groups with the filter **before** it,
and a run of consecutive `or` filters extends the same group; the groups are then ANDed
together. So `A, or B, C` becomes `(A OR B) AND C` - an or-pair can never disjoin an
unrelated scoping filter. An `or` on the very first filter has nothing to attach to and
simply starts a group (`export:validate` reports it as a warning).

```php
// Filter 1: status = 'active'
ExportFilter::create([
    'operator' => '=',
    'value' => 'active',
    'logical_operator' => 'AND',
]);

// Filter 2: OR status = 'pending' - groups with filter 1
ExportFilter::create([
    'operator' => '=',
    'value' => 'pending',
    'logical_operator' => 'OR',
]);
```

**SQL Result:**
```sql
SELECT * FROM users WHERE (status = 'active' or status = 'pending')
```

## Smart Relation Filter Parsing

For nested columns with `is_column = true`, the system automatically parses the path:

```php
// Create relation for nested column filter
$relation = ExportModelRelation::create([
    'export_model_id' => $workItemModel->id,
    'relation' => 'workOrder.invoice.custom_id',  // Full path
    'title' => 'Invoice Custom ID',
    'is_column' => true,                           // Mark as column
]);

// Create request filter
$filter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $relation->id,
    'operator' => 'in',
    'is_request' => true,
]);
```

**Request:**
```php
$requestData = [
    'workOrder.invoice.custom_id' => ['INV001', 'INV002'],
];
```

**What happens:**
1. System detects `is_column = true` with dots in path
2. Splits: relation = `workOrder.invoice`, column = `custom_id`
3. Builds nested `whereHas` query:

```sql
SELECT * FROM work_items
WHERE EXISTS (
    SELECT * FROM work_orders
    WHERE work_items.work_order_id = work_orders.id
    AND EXISTS (
        SELECT * FROM invoices
        WHERE work_orders.id = invoices.work_order_id
        AND invoices.custom_id IN ('INV001', 'INV002')
    )
)
```

## Collection Filtering

Collection filtering lets you extract specific items from a related collection.

### Use Case: Multiple Identifiers

Imagine a WorkItem has many Identifiers with different types:
- Container ID
- Tracking Number
- Reference Code

You want separate columns for each:

```php
// Filter for Container type
$containerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeRelation->id,
    'operator' => 'relation',
    'value' => 'Container',
]);

// Column showing Container ID
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $containerFilter->id,
    'title' => 'Container ID',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'position' => 1,
]);

// Filter for Tracking Number type
$trackingFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeRelation->id,
    'operator' => 'relation',
    'value' => 'Tracking Number',
]);

// Column showing Tracking Number
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $trackingFilter->id,
    'title' => 'Tracking Number',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'position' => 2,
]);
```

**Result:**

| Container ID | Tracking Number |
|--------------|-----------------|
| CNT12345 | TRK98765 |
| CNT67890 | TRK11111 |

## Filter Application Order

Filters are applied in this order:

1. **Layout filters** (main query WHERE), in creation order - `export_filters` rows ordered by id, then any entries from the layout's `filter_definitions` JSON
2. **Column filters with regular operators** (main query WHERE)
3. **Column filters with relation operator** (eager load constraints)

Because or-groups form against the preceding filter, keep the members of one group in a
single storage mechanism: all persisted rows, or all definitions.

```php
// Given these filters:

// 1. Layout filter: status = 'active'
// 2. Column filter: verified IS NOT NULL
// 3. Collection filter (relation): identifier.type = 'Container'

// The query becomes:
$query = User::query()
    ->where('status', 'active')           // Layout filter
    ->whereNotNull('verified')            // Column filter
    ->with(['identifiers' => function($q) {
        // Collection filter applied during processing, not here
    }]);
```

## Parameter Name Matching

For request-based filters, the system matches parameter names flexibly:

```php
// Filter configured for 'workOrder.invoice.custom_id'

// All of these request keys will match:
$requestData = ['workOrder.invoice.custom_id' => 'value'];     // Exact
$requestData = ['workorder.invoice.custom_id' => 'value'];     // Lowercase
$requestData = ['work_order.invoice.custom_id' => 'value'];    // Snake case
$requestData = ['workOrder_invoice_custom_id' => 'value'];     // Underscores
$requestData = [$filter->id => 'value'];                       // Filter UUID
```

## Debugging Filters

Use `getQuery()` to inspect the query with all filters applied:

```php
$query = $service->getQuery($layout, $requestData);
dd($query->toSql(), $query->getBindings());
```

Errors and warnings are still written to the log.

## Common Patterns

### Date Range Filter

```php
$dateFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => true,
    'value_type' => 'array',
]);

// Usage
$service->executeExport($layout, [
    'created_at' => ['2024-01-01', '2024-12-31'],
]);
```

### Status Filter with Multiple Values

```php
$statusFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => 'in',
    'is_request' => true,
    'is_required' => false,
    'value_type' => 'array',
]);

// Usage - can provide array or comma-separated string
$service->executeExport($layout, [
    'status' => ['active', 'pending'],  // Array
]);

$service->executeExport($layout, [
    'status' => 'active,pending',       // Auto-converted to array
]);
```

### Nested Relationship Filter

```php
$customerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerNameRelation->id,  // order.customer.name
    'operator' => 'like',
    'is_request' => true,
    'value_type' => 'string',
]);

// Usage
$service->executeExport($layout, [
    'order.customer.name' => '%Corp%',
]);
```

## Related Documentation

- [Filtering Data Guide](../guides/filtering-data.md) - Practical examples
- [Operators Reference](../reference/operators.md) - Complete operator list
- [Collection Filtering Example](../examples/advanced/collection-filtering.md)
