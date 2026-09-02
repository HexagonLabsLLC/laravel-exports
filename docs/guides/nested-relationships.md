# Nested Relationships

Export data from deeply nested relationships using dot notation.

## Overview

Laravel Exports supports traversing multiple relationship levels:

```php
// Single level
'value_path' => 'profile.bio',

// Two levels
'value_path' => 'company.address.city',

// Three levels
'value_path' => 'workItem.workOrder.customer.name',

// Four levels
'value_path' => 'order.customer.profile.company.name',
```

## Setup

### Import Models with Deep Discovery (Optional)

Under the default `lazy` schema sync, a nested path is validated and registered the
first time a layout references it, so this step only pre-populates the catalog. It is
required when `laravel-exports.schema_sync` is `manual`.

```bash
# Discover nested relationships up to 2 levels
php artisan export:import-models --deep

# Discover up to 3 levels
php artisan export:import-models --deep --deep-level=3
```

This creates `ExportModelRelation` records for nested paths like:
- `posts`
- `posts.comments`
- `posts.comments.author`

### Automatic Path Validation

When you use a nested path that doesn't exist as a relation, the system:
1. Validates the path is traversable
2. Creates the missing `ExportModelRelation` record
3. Proceeds with the export

Step 2 only happens while `laravel-exports.schema_sync` allows writes (`lazy` or
`verify`). In `manual` mode nothing is written and an unregistered path throws.

## Basic Usage

### BelongsTo Chain

```php
// User -> profile -> company -> name
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Company',
    'value_path' => 'profile.company.name',
    'position' => 1,
]);
```

### HasMany with Attribute

```php
// Order -> items -> product -> name (first item)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'First Product',
    'value_path' => 'items.product.name',
    'aggregator' => 'first',
    'position' => 1,
]);
```

### Complex Path

```php
// WorkItem -> workOrder -> customer -> contact -> org_name
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Organization',
    'value_path' => 'workItem.workOrder.customer.contact.org_name',
    'position' => 1,
]);
```

## How It Works

### Eager Loading

The service analyzes all columns and loads required relationships:

```php
// For these value paths:
// - 'profile.company.name'
// - 'orders.items.product.name'

// The query becomes:
$query->with([
    'profile',
    'profile.company',
    'orders',
    'orders.items',
    'orders.items.product',
]);
```

### Value Extraction

The service traverses the path to extract values:

```php
// value_path: 'profile.company.name'

$value = $user->profile;          // Get profile
$value = $value->company;         // Get company
$value = $value->name;            // Get name
```

If any segment is null, the result is null (or the default value).

## Linking to Relations

For better validation, link columns to model relations:

```php
// Find or create the nested relation
$companyRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'profile.company')
    ->first();

// Or use whereNested scope
$companyRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->whereNested('profile.company')
    ->first();

// Create column with relation link
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $companyRelation->id,
    'title' => 'Company',
    'value_path' => 'profile.company.name',
    'position' => 1,
]);
```

## Filtering Nested Data

### Filter by Nested Column

```php
// Create relation for the nested column
$customerNameRelation = ExportModelRelation::create([
    'export_model_id' => $orderModel->id,
    'relation' => 'customer.company.name',
    'title' => 'Customer Company Name',
    'is_column' => true,
]);

// Create filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerNameRelation->id,
    'operator' => 'like',
    'value' => '%Corp%',
    'value_type' => 'string',
]);
```

### Request-Based Nested Filter

```php
// Create relation marked as column
$invoiceIdRelation = ExportModelRelation::create([
    'export_model_id' => $workItemModel->id,
    'relation' => 'workOrder.invoice.custom_id',
    'title' => 'Invoice Custom ID',
    'is_column' => true,
]);

// Create request filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $invoiceIdRelation->id,
    'operator' => 'in',
    'is_request' => true,
    'value_type' => 'array',
]);

// Usage
$service->executeExport($layout, [
    'workOrder.invoice.custom_id' => ['INV001', 'INV002'],
]);
```

## Sorting by Nested Columns

Sort by nested relationship columns:

```php
// Get nested relation
$customerNameRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->whereNested('customer.company.name')
    ->first();

// Sort by customer company name
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerNameRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);
```

## Collections in Nested Paths

### Aggregate Nested Collections

```php
// Count comments across all posts
ExportColumn::create([
    'title' => 'Total Comments',
    'value_path' => 'posts.comments',
    'aggregator' => 'count',
    'position' => 1,
]);

// Sum values from nested collection
ExportColumn::create([
    'title' => 'Total Item Value',
    'value_path' => 'orders.items.price',
    'aggregator' => 'sum',
    'position' => 2,
]);
```

### Extract from Nested Collection

```php
// Get first comment author name
ExportColumn::create([
    'title' => 'First Comment Author',
    'value_path' => 'posts.comments.author.name',
    'aggregator' => 'first',
    'position' => 1,
]);
```

## Common Patterns

### User with Company Info

```php
// User -> profile -> company -> address
$columns = [
    ['title' => 'Name', 'value_path' => 'name'],
    ['title' => 'Company', 'value_path' => 'profile.company.name'],
    ['title' => 'City', 'value_path' => 'profile.company.address.city'],
    ['title' => 'Country', 'value_path' => 'profile.company.address.country'],
];
```

### Order with Customer

```php
// Order -> customer -> contact info
$columns = [
    ['title' => 'Order #', 'value_path' => 'order_number'],
    ['title' => 'Customer', 'value_path' => 'customer.name'],
    ['title' => 'Contact Email', 'value_path' => 'customer.contact.email'],
    ['title' => 'Company', 'value_path' => 'customer.company.name'],
];
```

### Work Item Report

```php
// WorkItem -> workOrder -> customer chain
$columns = [
    ['title' => 'Work Item', 'value_path' => 'title'],
    ['title' => 'Work Order', 'value_path' => 'workOrder.number'],
    ['title' => 'Customer', 'value_path' => 'workOrder.customer.name'],
    ['title' => 'Customer Contact', 'value_path' => 'workOrder.customer.contact.email'],
    ['title' => 'Organization', 'value_path' => 'workOrder.customer.contact.org_name'],
];
```

## Troubleshooting

### Path Returns Null

1. **Check relationship exists**
   ```php
   $user = User::with('profile.company')->find($id);
   dump($user->profile?->company?->name);
   ```

2. **Verify eager loading**
   Enable debug mode to see loaded relations.

3. **Check for null in chain**
   Any null segment stops traversal.

### Performance Issues

1. **Use deep discovery** to pre-register paths
2. **Avoid excessive depth** (4+ levels)
3. **Consider denormalization** for frequently accessed data

### Relation Not Found

If you get "relation not found" errors, first check `laravel-exports.schema_sync`: under
`lazy`/`verify` a valid path registers itself, so the error usually means the path does
not resolve on the model. In `manual` mode, register it with an import:

```bash
php artisan export:import-models --force --deep --deep-level=3
```

## Best Practices

1. **Pre-import Nested Relations**: Use `--deep` flag during import
2. **Link to Relations**: Connect columns to `export_model_relation_id`
3. **Set Defaults**: Nested paths often return null
4. **Test Paths**: Verify paths work before creating exports
5. **Limit Depth**: Keep paths to 3-4 levels when possible

## Debugging

Use `getQuery()` to inspect the built query and its eager loads before executing:

```php
$query = $service->getQuery($layout, $requestData);
dd($query->toSql(), $query->getEagerLoads());
```

Errors and warnings are still written to the log.

## Related Documentation

- [Importing Models](importing-models.md) - Deep discovery options
- [Creating Layouts](creating-layouts.md) - Column configuration
- [Nested Relationships Example](../examples/advanced/nested-relationships.md)
