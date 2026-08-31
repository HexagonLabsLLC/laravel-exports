# Sorting Data

Configure how exported records are ordered using the sorting system.

## Basic Sorting

Sort by a single column:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;

// Get the column to sort by
$createdAtRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'created_at')
    ->first();

// Sort by created_at descending (newest first)
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'direction' => 'desc',
    'priority' => 1,
]);
```

## Sort Properties

| Property | Type | Description |
|----------|------|-------------|
| `export_layout_id` | uuid | The layout this sort belongs to |
| `export_model_relation_id` | uuid | Column or relation to sort by |
| `direction` | enum | `asc` or `desc` |
| `priority` | integer | Sort order (1 = first, 2 = second) |

## Multi-Column Sorting

Sort by multiple columns using priority:

```php
// Primary sort: status ascending
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);

// Secondary sort: name ascending
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $nameRelation->id,
    'direction' => 'asc',
    'priority' => 2,
]);

// Tertiary sort: created_at descending
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'direction' => 'desc',
    'priority' => 3,
]);
```

**Result:** Records sorted by status, then name, then created_at.

## Sorting by Related Columns

### BelongsTo / HasOne Relations

Sort by a related model's column using LEFT JOIN:

```php
// Get the profile.company.name relation
$companyNameRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->whereNested('profile.company.name')
    ->first();

// Sort by company name
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $companyNameRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);
```

**Generated SQL:**

```sql
SELECT users.*
FROM users
LEFT JOIN profiles ON users.id = profiles.user_id
LEFT JOIN companies ON profiles.company_id = companies.id
ORDER BY companies.name ASC
```

### HasMany / BelongsToMany Relations

Sort by count or aggregate of related items:

```php
// Sort by post count
$postsRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'posts')
    ->first();

ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $postsRelation->id,
    'direction' => 'desc',  // Most posts first
    'priority' => 1,
]);
```

**Generated SQL:**

```sql
SELECT users.*, (
    SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id
) as posts_count
FROM users
ORDER BY posts_count DESC
```

### Custom Sort Column via Metadata

When sorting through a relation, the related column to sort by defaults to `id`. Set `metadata` on the `ExportModelRelation` to choose a different column:

```php
$relation->update(['metadata' => ['sort_column' => 'name']]);
```

## Common Sort Patterns

### Newest First

```php
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'direction' => 'desc',
    'priority' => 1,
]);
```

### Alphabetical

```php
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $nameRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);
```

### By Status Then Name

```php
// Status first (so all "active" are together)
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);

// Then by name
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $nameRelation->id,
    'direction' => 'asc',
    'priority' => 2,
]);
```

### Top Customers (By Order Total)

```php
// Assuming orders.total aggregation
$ordersRelation = ExportModelRelation::where('export_model_id', $customerModel->id)
    ->where('relation', 'orders')
    ->first();

ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $ordersRelation->id,
    'direction' => 'desc',  // Highest total first
    'priority' => 1,
]);
```

### Recently Updated

```php
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $updatedAtRelation->id,
    'direction' => 'desc',
    'priority' => 1,
]);
```

## Managing Sorts

### Get Sorts for a Layout

```php
$sorts = ExportSort::where('export_layout_id', $layout->id)
    ->orderBy('priority')
    ->get();
```

### Update Sort Order

```php
// Change from desc to asc
$sort->update(['direction' => 'asc']);

// Change priority
$sort->update(['priority' => 2]);
```

### Remove a Sort

```php
$sort->delete();
```

### Clear All Sorts

```php
ExportSort::where('export_layout_id', $layout->id)->delete();
```

## Sort vs. Database Indexes

For optimal performance, ensure database indexes exist on columns you sort by:

```php
// In a migration
Schema::table('users', function (Blueprint $table) {
    $table->index('created_at');
    $table->index('status');
    $table->index(['status', 'name']);  // Compound index for multi-column sort
});
```

## Nested Relation Sorting

Sort by deeply nested relations:

```php
// Sort orders by customer's company name
$customerCompanyRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->whereNested('customer.company.name')
    ->first();

ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerCompanyRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);
```

## Null Value Handling

By default, SQL sorts null values last in ascending order and first in descending order. To control this, you may need custom database-specific handling.

**MySQL:**

```sql
ORDER BY ISNULL(column), column ASC
```

**PostgreSQL:**

```sql
ORDER BY column ASC NULLS LAST
```

## Performance Considerations

1. **Index Sorted Columns**: Always index columns used for sorting
2. **Limit Result Sets**: Use pagination or limits with large datasets
3. **Avoid Sorting by Calculated Values**: Pre-compute if possible
4. **Use Related Sorts Carefully**: JOINs can be expensive

## Example: Complete Sorted Export

```php
// Create layout
$layout = ExportLayout::create([
    'export_model_id' => $orderModel->id,
    'name' => 'orders_report',
    'title' => 'Orders Report',
]);

// Add columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Order Number',
    'value_path' => 'order_number',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer',
    'value_path' => 'customer.name',
    'position' => 2,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total',
    'value_path' => 'total',
    'position' => 3,
]);

// Add multi-column sort
// 1. By customer name
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerNameRelation->id,
    'direction' => 'asc',
    'priority' => 1,
]);

// 2. By order date within each customer
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'direction' => 'desc',
    'priority' => 2,
]);

// Execute
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'orders.csv');
```

## Related Documentation

- [Creating Layouts](creating-layouts.md) - Complete layout configuration
- [Filtering Data](filtering-data.md) - Filter before sorting
- [Large Datasets](large-datasets.md) - Performance with large exports
