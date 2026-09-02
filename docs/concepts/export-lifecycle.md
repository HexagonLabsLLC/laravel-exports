# Export Lifecycle

This document explains how exports work from start to finish in Laravel Exports.

## Overview

When you execute an export, the system:

1. Loads the layout configuration from the database
2. Builds an Eloquent query with filters, sorts, and eager loading
3. Executes the query to retrieve records
4. Processes each record, extracting and transforming values
5. Outputs the data in the requested format

## The DynamicExportService

The `DynamicExportService` is the main engine that orchestrates exports.

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();
$data = $service->executeExport($layout, $requestData);
```

## Lifecycle Phases

### Phase 1: Layout Loading

The service loads the complete layout configuration including all related data.

```php
// What happens internally
$layout = ExportLayout::with([
    'exportModel',
    'columns.modelRelation',
    'columns.exportFunction',
    'columns.filter',
    'filters.modelRelation',
    'sorts.modelRelation',
])->find($layoutId);
```

**Key Operations:**
- Resolve the export model (a layout's `model` FQCN wins over `export_model_id` and lazy-syncs the catalog)
- Load all columns with their relations and functions
- Load layout-level filters (column-attached filters are excluded here)
- Load sorting configuration
- Append any `column_definitions`, `filter_definitions`, and `sort_definitions` carried by the layout row, after the persisted rows
- Validate the configuration

### Phase 2: Query Building

The service builds an Eloquent query based on the configuration.

#### Base Query

```php
// resolveExportModel() honors the layout's `model` FQCN first, then export_model_id
$modelClass = $layout->resolveExportModel()->model;
$query = $modelClass::query();
```

#### Eager Loading

The service analyzes all columns to determine which relationships need eager loading:

```php
// For columns with value paths like:
// - 'user.name'
// - 'workItem.workOrder.customer.name'
// - 'posts.comments.author'

// The service extracts and loads:
$query->with(['user', 'workItem.workOrder.customer', 'posts.comments.author']);
```

**Smart Intermediate Loading:**
For nested paths like `workItem.workOrder.customer`, the service also loads intermediate relations:
- `workItem`
- `workItem.workOrder`
- `workItem.workOrder.customer`

#### Filter Application

Filters are applied in order:

1. **Layout Filters** - Applied to the main query
2. **Column Filters (non-relation)** - Applied to the main query
3. **Column Filters (relation operator)** - Applied only to eager loading constraints

```php
// Static filter: status = 'active'
$query->where('status', '=', 'active');

// Request filter: date between values
$query->whereBetween('created_at', [$startDate, $endDate]);

// Relation filter: constraint on eager load
$query->with(['posts' => function($q) {
    $q->where('published', true);
}]);
```

#### Sort Application

Sorts are applied by priority:

```php
// Priority 1: Sort by created_at desc
$query->orderBy('created_at', 'desc');

// Priority 2: Sort by name asc
$query->orderBy('name', 'asc');
```

**Related Column Sorting:**
For BelongsTo/HasOne relationships, the service uses LEFT JOIN:

```php
// Sorting by customer.name
$query->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
      ->orderBy('customers.name', 'asc');
```

For HasMany/BelongsToMany, it uses aggregate subqueries:

```php
// Sorting by post count
$query->withCount('posts')->orderBy('posts_count', 'desc');
```

### Phase 3: Query Execution

The query is executed to retrieve records:

```php
// Standard execution
$results = $query->get();

// Chunked execution (for large datasets, and the basis of streaming)
$query->chunk(1000, function ($chunk) {
    // Process chunk
});
```

Streaming exports go through the same `chunk()` path: `streamAs()` hands
`executeExportChunked()` to the handler's stream callback.

### Phase 4: Result Processing

Each record is processed to extract column values.

#### Value Extraction Pipeline

For each column:

1. **Get the value path** (e.g., `user.profile.name`)
2. **Traverse the path** to extract the raw value
3. **Apply collection filtering** if configured
4. **Apply aggregation** if configured (sum, count, first, etc.)
5. **Apply transformation function** if configured
6. **Apply default value** if result is empty
7. **Apply the `format` template** (`{value}`) to non-empty scalar values
8. **Apply a request `override`** for the column, which replaces whatever came before

```php
// Example processing for a column with:
// value_path: 'orders.total'
// aggregator: 'sum'
// function: 'Format Currency'

$value = $record->orders;           // Get the collection
$value = $value->sum('total');      // Apply aggregation
$value = formatCurrency($value);    // Apply transformation
```

#### Nested Relation Traversal

The service handles dot notation to traverse nested relationships:

```php
// value_path: 'workItem.workOrder.customer.name'

$value = $record;
foreach (['workItem', 'workOrder', 'customer', 'name'] as $segment) {
    if ($value === null) break;
    $value = $value->$segment ?? data_get($value, $segment);
}
// Result: The customer's name
```

#### Collection Value Extraction

When the path includes a collection, the service extracts values:

```php
// value_path: 'posts.title'
// Without aggregator, gets first item's title

// value_path: 'posts.title' with aggregator: 'count'
// Counts the number of posts

// value_path: 'identifiers.value' with filter: type = 'container'
// Gets value from first identifier where type is 'container'
```

### Phase 5: Output Generation

Processed data is converted to the requested format.

#### CSV Output

```php
$handler = new CsvExportHandler($layout, [
    'delimiter' => ',',
    'enclosure' => '"',
    'include_headers' => true,
]);
$csv = $handler->export($data);
```

#### JSON Output

```php
$handler = new JsonExportHandler($layout, [
    'pretty' => true,
]);
$json = $handler->export($data);
```

#### Download Response

```php
return $service->downloadAs($layout, 'csv', 'export.csv');
```

#### Streaming Response

For large datasets:

```php
return $service->streamAs($layout, 'csv', 'large-export.csv', [], [], 1000);
```

## Request Data Flow

Request data affects exports in several ways:

### Dynamic Filter Values

```php
// Filter configured as: is_request = true

$requestData = ['date_range' => ['2024-01-01', '2024-12-31']];
$service->executeExport($layout, $requestData);

// The filter uses the provided date range
```

### Default Overrides

```php
// Override column defaults at runtime
$requestData = [
    'defaults' => [
        $column->id => 'N/A',
    ],
];
```

### Value Overrides

```php
// Force specific values regardless of data
$requestData = [
    'overrides' => [
        $column->id => 'REDACTED',
    ],
];
```

## Debugging

Use `getQuery()` to inspect the fully built query (filters, sorts, and eager loads applied) without executing it:

```php
$query = $service->getQuery($layout, $requestData);
dd($query->toSql(), $query->getBindings(), $query->getEagerLoads());
```

Errors and warnings are still written to the log.

## Performance Considerations

### Eager Loading Optimization

The service automatically optimizes eager loading to prevent N+1 queries:

```php
// Without optimization: 1 + N queries
foreach ($users as $user) {
    echo $user->profile->name; // Each access triggers a query
}

// With eager loading: 2 queries total
$users = User::with('profile')->get();
```

### Chunked Processing

For large datasets, use chunked execution:

```php
$service->executeExportChunked($layout, [], 1000, function ($chunk) {
    // Process 1000 records at a time
});
```

### Streaming

For very large exports, streaming avoids memory limits:

```php
return $service->streamAs($layout, 'csv', 'huge-export.csv');
```

Both paths derive their rows chunk by chunk, so a layout with an `is_expanded` column
throws a `RuntimeException`: expanded columns need the full dataset to know the column
set. Chunked, streamed, paginated, and queued exports are all affected; use
`executeExport()` for those layouts.

## Error Handling

The service handles various error conditions:

- **Missing Relations**: Logged and skipped gracefully
- **Invalid Paths**: Returns null with debug logging
- **Function Errors**: Falls back to original value
- **Database Errors**: Propagated with context

## Sequence Diagram

```
User          Controller       DynamicExportService    Database
  |                |                    |                  |
  |--- Request --->|                    |                  |
  |                |--- executeExport ->|                  |
  |                |                    |--- Load Layout ->|
  |                |                    |<-- Layout -------|
  |                |                    |                  |
  |                |                    |--- Build Query --|
  |                |                    |                  |
  |                |                    |--- Apply Filters-|
  |                |                    |                  |
  |                |                    |--- Apply Sorts --|
  |                |                    |                  |
  |                |                    |--- Execute ----->|
  |                |                    |<-- Results ------|
  |                |                    |                  |
  |                |                    |--- Process ------|
  |                |                    |   Results        |
  |                |                    |                  |
  |                |<-- Formatted Data -|                  |
  |<-- Response ---|                    |                  |
```

## Related Documentation

- [Filter Architecture](filter-architecture.md) - Deep dive into filtering
- [Database Schema](database-schema.md) - Table structure
- [API Reference](../reference/api.md) - Method documentation
