# Nested Relationships Export

Export deeply nested relationship data using dot notation.

## Scenario

Export work items with data from a 4-level relationship chain:
WorkItem -> WorkOrder -> Customer -> Contact

## Models

```php
// WorkItem.php
class WorkItem extends Model
{
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}

// WorkOrder.php
class WorkOrder extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

// Customer.php
class Customer extends Model
{
    public function contact(): HasOne
    {
        return $this->hasOne(Contact::class);
    }
}

// Contact.php
class Contact extends Model
{
    // org_name, email, phone columns
}
```

## Sample Data

```php
// work_items
[
    ['id' => 1, 'title' => 'Install Equipment', 'work_order_id' => 1],
    ['id' => 2, 'title' => 'Maintenance Check', 'work_order_id' => 2],
]

// work_orders
[
    ['id' => 1, 'number' => 'WO-001', 'customer_id' => 1],
    ['id' => 2, 'number' => 'WO-002', 'customer_id' => 2],
]

// customers
[
    ['id' => 1, 'name' => 'Acme Corp'],
    ['id' => 2, 'name' => 'Tech Inc'],
]

// contacts
[
    ['customer_id' => 1, 'org_name' => 'Acme Corporation', 'email' => 'contact@acme.com'],
    ['customer_id' => 2, 'org_name' => 'Tech Industries', 'email' => 'info@tech.com'],
]
```

## Setup

### 1. Import with Deep Discovery

```bash
php artisan export:import-models --deep --deep-level=4
```

### 2. Create Layout

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn};

$workItemModel = ExportModel::where('title', 'WorkItem')->first();

$layout = ExportLayout::create([
    'export_model_id' => $workItemModel->id,
    'title' => 'Work Items with Full Customer Details',
]);
```

### 3. Create Columns

```php
// Direct attribute
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Work Item',
    'value_path' => 'title',
    'position' => 1,
]);

// Level 1: workOrder
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Work Order',
    'value_path' => 'workOrder.number',
    'position' => 2,
]);

// Level 2: workOrder.customer
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer',
    'value_path' => 'workOrder.customer.name',
    'position' => 3,
]);

// Level 3: workOrder.customer.contact
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Organization',
    'value_path' => 'workOrder.customer.contact.org_name',
    'default' => 'N/A',
    'position' => 4,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Contact Email',
    'value_path' => 'workOrder.customer.contact.email',
    'default' => 'No email',
    'position' => 5,
]);
```

## Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'work-items-detailed.csv');
```

## Expected Output

```csv
Work Item,Work Order,Customer,Organization,Contact Email
Install Equipment,WO-001,Acme Corp,Acme Corporation,contact@acme.com
Maintenance Check,WO-002,Tech Inc,Tech Industries,info@tech.com
```

## Filtering by Nested Columns

Filter work items by customer organization name:

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportFilter, ExportModelRelation};

// Create relation for nested column
$orgNameRelation = ExportModelRelation::create([
    'export_model_id' => $workItemModel->id,
    'relation' => 'workOrder.customer.contact.org_name',
    'title' => 'Organization Name',
    'is_column' => true,
]);

// Filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $orgNameRelation->id,
    'operator' => 'like',
    'value' => '%Corp%',
    'value_type' => 'string',
]);
```

### Request-Based Nested Filter

```php
// Create request filter for customer ID
$customerIdRelation = ExportModelRelation::create([
    'export_model_id' => $workItemModel->id,
    'relation' => 'workOrder.customer.id',
    'is_column' => true,
]);

ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerIdRelation->id,
    'operator' => 'in',
    'is_request' => true,
    'value_type' => 'array',
]);

// Usage
$service->executeExport($layout, [
    'workOrder.customer.id' => [1, 2, 3],
]);
```

## Sorting by Nested Columns

```php
use HexagonLabsLLC\LaravelExports\Models\ExportSort;

$customerNameRelation = ExportModelRelation::where('export_model_id', $workItemModel->id)
    ->whereNested('workOrder.customer.name')
    ->first();

ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerNameRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);
```

## Multiple Nested Paths

Access different branches of relationships:

```php
// WorkItem -> workOrder -> customer -> name
'value_path' => 'workOrder.customer.name'

// WorkItem -> workOrder -> technician -> name
'value_path' => 'workOrder.technician.name'

// WorkItem -> workOrder -> location -> address -> city
'value_path' => 'workOrder.location.address.city'
```

## Handling Null Values

Set defaults for missing data in the chain:

```php
ExportColumn::create([
    'title' => 'Contact Phone',
    'value_path' => 'workOrder.customer.contact.phone',
    'default' => 'Not provided',  // Used if contact or phone is null
    'position' => 6,
]);
```

## Performance Note

Deep nesting triggers multiple eager loads:

```php
// For value_path: workOrder.customer.contact.email
// The service loads:
$query->with([
    'workOrder',
    'workOrder.customer',
    'workOrder.customer.contact',
]);
```

This is optimized to prevent N+1 queries but can be slow with very deep nesting or large datasets.

## Debug Mode

Enable to see path traversal:

```env
APP_DEBUG=true
```

**Log output:**
```
[INFO] Extracting value for: workOrder.customer.contact.org_name
[INFO] Traversing: workOrder -> WorkOrder #1
[INFO] Traversing: customer -> Customer #1
[INFO] Traversing: contact -> Contact #1
[INFO] Extracting attribute: org_name -> "Acme Corporation"
```

## Notes

- Maximum recommended depth: 4-5 levels
- Always set defaults for nullable paths
- Use `--deep-level` in import to pre-register paths
- Consider denormalization for frequently accessed deep data
