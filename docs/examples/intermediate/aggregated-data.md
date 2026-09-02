# Aggregated Data Export

Use aggregators to summarize collection data.

## Scenario

Export a customer summary with order statistics.

## Sample Data

```php
// customers table
$customers = [
    ['id' => 1, 'name' => 'John Doe'],
    ['id' => 2, 'name' => 'Jane Smith'],
    ['id' => 3, 'name' => 'Bob Wilson'],
];

// orders table
$orders = [
    ['customer_id' => 1, 'total' => 100.00, 'created_at' => '2024-01-15'],
    ['customer_id' => 1, 'total' => 200.00, 'created_at' => '2024-02-20'],
    ['customer_id' => 1, 'total' => 150.00, 'created_at' => '2024-03-10'],
    ['customer_id' => 2, 'total' => 500.00, 'created_at' => '2024-02-01'],
    ['customer_id' => 2, 'total' => 75.00, 'created_at' => '2024-03-15'],
    // Bob has no orders
];
```

## Setup

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFunction};

$customerModel = ExportModel::where('title', 'Customer')->first();

$layout = ExportLayout::create([
    'export_model_id' => $customerModel->id,
    'name' => 'customer_summary',
    'title' => 'Customer Summary',
]);

// Get functions for formatting
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();
$formatDate = ExportFunction::where('name', 'Format Date')->first();
```

## Create Aggregated Columns

```php
// Customer name
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer',
    'value_path' => 'name',
    'position' => 1,
]);

// Order count
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Orders',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'default' => '0',
    'position' => 2,
]);

// Total spent (sum)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Spent',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'default' => '$0.00',
    'position' => 3,
]);

// Average order value
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Avg Order',
    'value_path' => 'orders.total',
    'aggregator' => 'avg',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'default' => '$0.00',
    'position' => 4,
]);

// Largest order
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Largest Order',
    'value_path' => 'orders.total',
    'aggregator' => 'max',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'default' => '-',
    'position' => 5,
]);

// Smallest order
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Smallest Order',
    'value_path' => 'orders.total',
    'aggregator' => 'min',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'default' => '-',
    'position' => 6,
]);

// First order date
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'First Order',
    'value_path' => 'orders.created_at',
    'aggregator' => 'first',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'M j, Y'],
    'default' => 'Never',
    'position' => 7,
]);

// Last order date
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Last Order',
    'value_path' => 'orders.created_at',
    'aggregator' => 'last',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'M j, Y'],
    'default' => 'Never',
    'position' => 8,
]);
```

## Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'customer-summary.csv');
```

## Expected Output

```csv
Customer,Orders,Total Spent,Avg Order,Largest Order,Smallest Order,First Order,Last Order
John Doe,3,$450.00,$150.00,$200.00,$100.00,"Jan 15, 2024","Mar 10, 2024"
Jane Smith,2,$575.00,$287.50,$500.00,$75.00,"Feb 1, 2024","Mar 15, 2024"
Bob Wilson,0,$0.00,$0.00,-,-,Never,Never
```

## Aggregator Reference

| Aggregator | Description | Input | Output |
|------------|-------------|-------|--------|
| `count` | Count items | Collection | Integer |
| `sum` | Sum values | Collection of numbers | Number |
| `avg` | Average | Collection of numbers | Number |
| `min` | Minimum | Collection of numbers | Number |
| `max` | Maximum | Collection of numbers | Number |
| `first` | First item | Collection | Single value |
| `last` | Last item | Collection | Single value |

## Using First/Last with Objects

Extract specific attributes from first/last items:

```php
// First order's status
ExportColumn::create([
    'title' => 'First Order Status',
    'value_path' => 'orders.status',
    'aggregator' => 'first',
]);

// Last order's shipping address
ExportColumn::create([
    'title' => 'Recent Ship City',
    'value_path' => 'orders.shipping_address.city',
    'aggregator' => 'last',
]);
```

## Filtered Aggregations

Combine with filters to aggregate subsets:

```php
// Create filter for completed orders
$completedFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $orderStatusRelation->id,
    'operator' => 'relation',
    'value' => 'completed',
]);

// Count only completed orders
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $completedFilter->id,
    'title' => 'Completed Orders',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'default' => '0',
    'position' => 9,
]);

// Sum only completed order totals
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $completedFilter->id,
    'title' => 'Completed Revenue',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'default' => '$0.00',
    'position' => 10,
]);
```

## Nested Collection Aggregations

Aggregate deeply nested collections:

```php
// Count all comments across all posts
ExportColumn::create([
    'title' => 'Total Comments',
    'value_path' => 'posts.comments',
    'aggregator' => 'count',
]);

// Sum revenue from all order items
ExportColumn::create([
    'title' => 'Item Revenue',
    'value_path' => 'orders.items.subtotal',
    'aggregator' => 'sum',
]);
```

## Notes

- Default values are important for empty collections
- Functions are applied after aggregation
- Use `first`/`last` to extract single items from collections
- See [Aggregations Guide](../../guides/aggregations.md) for more details
