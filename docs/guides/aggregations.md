# Aggregations

Aggregations process collection values to produce a single result. Use them when exporting HasMany or BelongsToMany relationships.

## Available Aggregators

| Aggregator | Description | Result Type |
|------------|-------------|-------------|
| `sum` | Sum of values | Number |
| `count` | Number of items | Integer |
| `avg` | Average of values | Number |
| `min` | Minimum value | Number |
| `max` | Maximum value | Number |
| `first` | First item | Mixed |
| `last` | Last item | Mixed |

## Basic Usage

```php
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

// Count posts
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Post Count',
    'value_path' => 'posts',
    'aggregator' => 'count',
    'position' => 1,
]);
```

## Numeric Aggregations

### Sum

Total of a numeric field across the collection.

```php
// Sum of order totals
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Revenue',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'position' => 1,
]);
```

**Data:**
```
orders: [
    {total: 100},
    {total: 200},
    {total: 150}
]
```
**Result:** 450

### Count

Number of items in the collection.

```php
// Count of orders
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Order Count',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'position' => 1,
]);
```

**Data:** 3 orders
**Result:** 3

### Average

Mean value of a numeric field.

```php
// Average order value
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Avg Order Value',
    'value_path' => 'orders.total',
    'aggregator' => 'avg',
    'position' => 1,
]);
```

**Data:** totals [100, 200, 150]
**Result:** 150

### Min

Smallest value.

```php
// Lowest order
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Min Order',
    'value_path' => 'orders.total',
    'aggregator' => 'min',
    'position' => 1,
]);
```

**Data:** totals [100, 200, 150]
**Result:** 100

### Max

Largest value.

```php
// Highest order
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Max Order',
    'value_path' => 'orders.total',
    'aggregator' => 'max',
    'position' => 1,
]);
```

**Data:** totals [100, 200, 150]
**Result:** 200

## Collection Extractors

### First

Get the first item from a collection.

```php
// First order date
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'First Order',
    'value_path' => 'orders.created_at',
    'aggregator' => 'first',
    'position' => 1,
]);
```

**Data:** orders sorted by date
**Result:** Date of first order

### Last

Get the last item from a collection.

```php
// Most recent order date
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Last Order',
    'value_path' => 'orders.created_at',
    'aggregator' => 'last',
    'position' => 1,
]);
```

**Result:** Date of most recent order

## Combining with Filters

Aggregations work with filtered collections:

```php
// Create filter for completed orders
$completedFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $orderStatusRelation->id,
    'operator' => 'relation',
    'value' => 'completed',
]);

// Sum only completed orders
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $completedFilter->id,
    'title' => 'Completed Revenue',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'position' => 1,
]);
```

This sums only orders where status = 'completed'.

## Combining with Functions

Apply transformation functions after aggregation:

```php
// Get the Format Currency function
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();

// Sum and format as currency
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Revenue',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => json_encode(['USD', 'en_US']),
    'position' => 1,
]);
```

**Processing order:**
1. Get orders collection
2. Sum the totals -> 1234.50
3. Format as currency -> "$1,234.50"

## Extracting Specific Items

Use `first` or `last` with filters to extract specific items:

```php
// Filter for primary email type
$primaryFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $emailTypeRelation->id,
    'operator' => 'relation',
    'value' => 'primary',
]);

// Get primary email address
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $primaryFilter->id,
    'title' => 'Primary Email',
    'value_path' => 'emails.address',
    'aggregator' => 'first',
    'position' => 1,
]);
```

## Nested Collections

Aggregate nested relationship data:

```php
// Count comments across all posts
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Comments',
    'value_path' => 'posts.comments',
    'aggregator' => 'count',
    'position' => 1,
]);
```

## Default Values

Provide defaults for empty collections:

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Order Count',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'default' => '0',  // Show 0 instead of empty
    'position' => 1,
]);
```

## Common Patterns

### Customer Summary

```php
// Order count
ExportColumn::create([
    'title' => 'Orders',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'position' => 1,
]);

// Total spent
ExportColumn::create([
    'title' => 'Total Spent',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'position' => 2,
]);

// Average order
ExportColumn::create([
    'title' => 'Avg Order',
    'value_path' => 'orders.total',
    'aggregator' => 'avg',
    'position' => 3,
]);

// First purchase
ExportColumn::create([
    'title' => 'First Purchase',
    'value_path' => 'orders.created_at',
    'aggregator' => 'first',
    'position' => 4,
]);

// Last purchase
ExportColumn::create([
    'title' => 'Last Purchase',
    'value_path' => 'orders.created_at',
    'aggregator' => 'last',
    'position' => 5,
]);
```

### Product Report

```php
// Stock total
ExportColumn::create([
    'title' => 'Total Stock',
    'value_path' => 'inventory.quantity',
    'aggregator' => 'sum',
    'position' => 1,
]);

// Warehouse count
ExportColumn::create([
    'title' => 'Warehouses',
    'value_path' => 'inventory',
    'aggregator' => 'count',
    'position' => 2,
]);

// Lowest stock level
ExportColumn::create([
    'title' => 'Min Stock',
    'value_path' => 'inventory.quantity',
    'aggregator' => 'min',
    'position' => 3,
]);
```

### Order Details

```php
// Line items
ExportColumn::create([
    'title' => 'Items',
    'value_path' => 'items',
    'aggregator' => 'count',
    'position' => 1,
]);

// Subtotal
ExportColumn::create([
    'title' => 'Subtotal',
    'value_path' => 'items.total',
    'aggregator' => 'sum',
    'position' => 2,
]);

// Highest item
ExportColumn::create([
    'title' => 'Most Expensive Item',
    'value_path' => 'items.price',
    'aggregator' => 'max',
    'position' => 3,
]);
```

## When to Use Aggregations

| Scenario | Aggregator |
|----------|------------|
| Count related items | `count` |
| Total a numeric field | `sum` |
| Calculate average | `avg` |
| Find lowest value | `min` |
| Find highest value | `max` |
| Get specific item | `first` or `last` |
| Extract from filtered collection | `first` with filter |

## Related Documentation

- [Creating Layouts](creating-layouts.md) - Column configuration
- [Filtering Data](filtering-data.md) - Filtering collections
- [Transformation Functions](transformation-functions.md) - Format aggregated values
- [Aggregated Data Example](../examples/intermediate/aggregated-data.md)
