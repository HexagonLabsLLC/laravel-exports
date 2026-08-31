# Related Data Export

Export data from related models using relationships.

## Scenario

Export orders with customer information and shipping address.

## Sample Data

```php
// orders table
[
    ['id' => 1, 'order_number' => 'ORD-001', 'customer_id' => 1, 'total' => 150.00],
    ['id' => 2, 'order_number' => 'ORD-002', 'customer_id' => 2, 'total' => 275.50],
]

// customers table
[
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
]

// addresses table (customer.address)
[
    ['customer_id' => 1, 'city' => 'New York', 'country' => 'USA'],
    ['customer_id' => 2, 'city' => 'London', 'country' => 'UK'],
]
```

## Models

```php
// Order.php
class Order extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

// Customer.php
class Customer extends Model
{
    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }
}
```

## Setup

### 1. Import Models with Deep Discovery

```bash
php artisan export:import-models --deep --deep-level=2
```

This discovers:
- Order -> customer
- Order -> customer.address

### 2. Create Layout

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn};

$orderModel = ExportModel::where('title', 'Order')->first();

$layout = ExportLayout::create([
    'export_model_id' => $orderModel->id,
    'name' => 'orders_with_customer_details',
    'title' => 'Orders with Customer Details',
]);
```

### 3. Create Columns

```php
// Order columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Order Number',
    'value_path' => 'order_number',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total',
    'value_path' => 'total',
    'position' => 2,
]);

// Customer columns (BelongsTo)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer Name',
    'value_path' => 'customer.name',
    'position' => 3,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer Email',
    'value_path' => 'customer.email',
    'position' => 4,
]);

// Nested relationship (customer.address)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'City',
    'value_path' => 'customer.address.city',
    'position' => 5,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Country',
    'value_path' => 'customer.address.country',
    'position' => 6,
]);
```

### 4. Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'orders-with-customers.csv');
```

## Expected Output

```csv
Order Number,Total,Customer Name,Customer Email,City,Country
ORD-001,150.00,John Doe,john@example.com,New York,USA
ORD-002,275.50,Jane Smith,jane@example.com,London,UK
```

## Relationship Types

### BelongsTo

```php
// Order belongs to Customer
'value_path' => 'customer.name'
```

### HasOne

```php
// User has one Profile
'value_path' => 'profile.bio'
```

### HasMany (with aggregator)

```php
// User has many Orders
ExportColumn::create([
    'title' => 'Order Count',
    'value_path' => 'orders',
    'aggregator' => 'count',
]);

// Sum of order totals
ExportColumn::create([
    'title' => 'Total Spent',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
]);
```

### Nested Relationships

```php
// Three levels deep
'value_path' => 'order.customer.company.name'

// Four levels deep
'value_path' => 'workItem.workOrder.customer.contact.email'
```

## Handling Missing Relations

Set default values for records without related data:

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Company',
    'value_path' => 'customer.company.name',
    'default' => 'Individual',  // Used when company is null
    'position' => 7,
]);
```

## Linking to Model Relations

For better validation:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;

$customerRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->where('relation', 'customer')
    ->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerRelation->id,
    'title' => 'Customer Name',
    'value_path' => 'customer.name',
    'position' => 3,
]);
```

## Full Example: User Profile Report

```php
// Users with profile, company, and order summary

$columns = [
    // User direct attributes
    ['title' => 'Name', 'value_path' => 'name', 'position' => 1],
    ['title' => 'Email', 'value_path' => 'email', 'position' => 2],

    // Profile (HasOne)
    ['title' => 'Bio', 'value_path' => 'profile.bio', 'position' => 3],
    ['title' => 'Phone', 'value_path' => 'profile.phone', 'default' => 'N/A', 'position' => 4],

    // Company (profile.company - nested)
    ['title' => 'Company', 'value_path' => 'profile.company.name', 'default' => 'Freelance', 'position' => 5],
    ['title' => 'Industry', 'value_path' => 'profile.company.industry', 'position' => 6],

    // Orders (HasMany with aggregation)
    ['title' => 'Order Count', 'value_path' => 'orders', 'aggregator' => 'count', 'position' => 7],
    ['title' => 'Total Spent', 'value_path' => 'orders.total', 'aggregator' => 'sum', 'position' => 8],
];

foreach ($columns as $col) {
    ExportColumn::create(array_merge(
        ['export_layout_id' => $layout->id],
        $col
    ));
}
```

## Notes

- The service automatically eager loads required relationships
- Null values in the chain return null (or default)
- Use aggregators when accessing HasMany/BelongsToMany relations
- See [Nested Relationships Guide](../../guides/nested-relationships.md) for more details
