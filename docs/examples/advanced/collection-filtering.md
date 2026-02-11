# Collection Filtering Export

Extract specific items from collections based on criteria.

## Scenario

Export work items with different identifier types in separate columns. Each work item has multiple identifiers (Container, Tracking, Reference).

## Models

```php
// WorkItem.php
class WorkItem extends Model
{
    public function identifiers(): HasMany
    {
        return $this->hasMany(Identifier::class);
    }
}

// Identifier.php
class Identifier extends Model
{
    public function type(): BelongsTo
    {
        return $this->belongsTo(IdentifierType::class, 'identifier_type_id');
    }
}

// IdentifierType.php
class IdentifierType extends Model
{
    // title column: 'Container', 'Tracking', 'Reference'
}
```

## Sample Data

```php
// work_items
[
    ['id' => 1, 'title' => 'Shipment A'],
    ['id' => 2, 'title' => 'Shipment B'],
]

// identifier_types
[
    ['id' => 1, 'title' => 'Container'],
    ['id' => 2, 'title' => 'Tracking'],
    ['id' => 3, 'title' => 'Reference'],
]

// identifiers
[
    ['work_item_id' => 1, 'identifier_type_id' => 1, 'value' => 'CNT12345'],
    ['work_item_id' => 1, 'identifier_type_id' => 2, 'value' => 'TRK98765'],
    ['work_item_id' => 1, 'identifier_type_id' => 3, 'value' => 'REF-001'],
    ['work_item_id' => 2, 'identifier_type_id' => 1, 'value' => 'CNT67890'],
    ['work_item_id' => 2, 'identifier_type_id' => 2, 'value' => 'TRK11111'],
    // Shipment B has no Reference identifier
]
```

## Setup

### 1. Create Layout

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFilter, ExportModelRelation};

$workItemModel = ExportModel::where('title', 'WorkItem')->first();

$layout = ExportLayout::create([
    'export_model_id' => $workItemModel->id,
    'title' => 'Work Items with Identifiers',
]);
```

### 2. Get Required Relations

```php
// Relation to identifiers collection
$identifiersRelation = ExportModelRelation::where('export_model_id', $workItemModel->id)
    ->where('relation', 'identifiers')
    ->first();

// Relation to identifier type title (for filtering)
$typeRelation = ExportModelRelation::where('export_model_id', $workItemModel->id)
    ->whereNested('identifiers.type.title')
    ->first();

// If not found, create it
if (!$typeRelation) {
    $typeRelation = ExportModelRelation::create([
        'export_model_id' => $workItemModel->id,
        'relation' => 'identifiers.type.title',
        'title' => 'Identifier Type Title',
        'is_column' => false,
    ]);
}
```

### 3. Create Filters for Each Type

```php
// Filter for Container type
$containerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeRelation->id,
    'operator' => 'relation',
    'value' => 'Container',
    'value_type' => 'string',
]);

// Filter for Tracking type
$trackingFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeRelation->id,
    'operator' => 'relation',
    'value' => 'Tracking',
    'value_type' => 'string',
]);

// Filter for Reference type
$referenceFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeRelation->id,
    'operator' => 'relation',
    'value' => 'Reference',
    'value_type' => 'string',
]);
```

### 4. Create Columns with Filters

```php
// Work Item title
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Work Item',
    'value_path' => 'title',
    'position' => 1,
]);

// Container ID (filtered)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $containerFilter->id,
    'title' => 'Container ID',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'default' => 'N/A',
    'position' => 2,
]);

// Tracking Number (filtered)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $trackingFilter->id,
    'title' => 'Tracking Number',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'default' => 'N/A',
    'position' => 3,
]);

// Reference (filtered)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifiersRelation->id,
    'export_filter_id' => $referenceFilter->id,
    'title' => 'Reference',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'default' => 'N/A',
    'position' => 4,
]);
```

## Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'work-items-identifiers.csv');
```

## Expected Output

```csv
Work Item,Container ID,Tracking Number,Reference
Shipment A,CNT12345,TRK98765,REF-001
Shipment B,CNT67890,TRK11111,N/A
```

## How It Works

1. **Load all identifiers** for each work item via eager loading
2. **Apply collection filter** to get only identifiers matching the type
3. **Use aggregator** (`first`) to get single value from filtered collection
4. **Apply default** if no matching identifiers found

## Filtering by Attribute Directly

If your identifier has a `type` string column instead of a relationship:

```php
// identifiers table: work_item_id, type, value
// type values: 'container', 'tracking', 'reference'

$typeColumnRelation = ExportModelRelation::create([
    'export_model_id' => $workItemModel->id,
    'relation' => 'identifiers.type',
    'is_column' => true,
]);

$containerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeColumnRelation->id,
    'operator' => 'relation',
    'value' => 'container',
]);
```

## Multiple Values from Same Collection

Get all tracking numbers joined:

```php
$arrayJoin = ExportFunction::where('name', 'Array Join')->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $trackingFilter->id,
    'title' => 'All Tracking Numbers',
    'value_path' => 'identifiers.value',
    // No aggregator - get all values
    'export_function_id' => $arrayJoin->id,
    'export_function_values' => json_encode([', ']),
    'position' => 5,
]);
```

## Count Filtered Items

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $trackingFilter->id,
    'title' => 'Tracking Count',
    'value_path' => 'identifiers',
    'aggregator' => 'count',
    'default' => '0',
    'position' => 6,
]);
```

## Real-World Example: Contact Types

```php
// User has many contacts: primary, billing, shipping

// Primary email
ExportColumn::create([
    'title' => 'Primary Email',
    'value_path' => 'contacts.email',
    'export_filter_id' => $primaryFilter->id,  // type = 'primary'
    'aggregator' => 'first',
]);

// Billing email
ExportColumn::create([
    'title' => 'Billing Email',
    'value_path' => 'contacts.email',
    'export_filter_id' => $billingFilter->id,  // type = 'billing'
    'aggregator' => 'first',
]);
```

## Notes

- The `relation` operator is key for collection filtering
- Use `first` or `last` aggregator for single values
- Always set defaults for potentially empty filtered collections
- Collection filters do NOT affect the main query, only the extracted values
