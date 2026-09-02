# Operators Reference

## Comparison Operators

| Operator | SQL | Value Type | Example |
|----------|-----|------------|---------|
| `=` | `= value` | any | `'operator' => '=', 'value' => 'active'` |
| `!=` | `!= value` | any | `'operator' => '!=', 'value' => 'deleted'` |
| `>` | `> value` | number, date | `'operator' => '>', 'value' => 100` |
| `<` | `< value` | number, date | `'operator' => '<', 'value' => 50` |
| `>=` | `>= value` | number, date | `'operator' => '>=', 'value' => 18` |
| `<=` | `<= value` | number, date | `'operator' => '<=', 'value' => 100` |

## Array Operators

| Operator | SQL | Value Type | Example |
|----------|-----|------------|---------|
| `in` | `IN (...)` | array | `'operator' => 'in', 'value' => json_encode(['a', 'b'])` |
| `not_in` | `NOT IN (...)` | array | `'operator' => 'not_in', 'value' => json_encode(['x', 'y'])` |
| `between` | `BETWEEN ... AND ...` | array [2] | `'operator' => 'between', 'value_type' => 'array'` |

## Pattern Operators

| Operator | SQL | Wildcards | Example |
|----------|-----|-----------|---------|
| `like` | `LIKE pattern` | `%` (any chars), `_` (single char) | `'value' => '%@company.com'` |

## Null Operators

| Operator | SQL | Notes |
|----------|-----|-------|
| `null` | `IS NULL` | Value is ignored, but `value_type` still required |
| `not_null` | `IS NOT NULL` | Value is ignored, but `value_type` still required |

## Special Operators

| Operator | Description | Use Case |
|----------|-------------|----------|
| `json_contains` | Check JSON column contains value | Filtering JSON/settings columns |
| `relation` | Filter eager-loaded collections | Extract specific items from related collections |

## Value Types

| Type | Description | Example |
|------|-------------|---------|
| `string` | Text value (dates are strings) | `"active"`, `"2024-01-01"` |
| `integer` | Integer value | `123` |
| `float` | Decimal value | `19.99` |
| `boolean` | True/false | `true` |
| `array` | JSON array | `["a", "b"]` |

## Logical Operators

Combine multiple filters:

```php
// Filter 1: status = 'active'
ExportFilter::create([
    'operator' => '=',
    'value' => 'active',
    'logical_operator' => 'AND',
]);

// Filter 2: OR status = 'pending'
ExportFilter::create([
    'operator' => '=',
    'value' => 'pending',
    'logical_operator' => 'OR',
]);
```

Result: `WHERE status = 'active' OR status = 'pending'`

## Request Filters

Dynamic filters from API request:

```php
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $relation->id,
    'operator' => '=',
    'value_type' => 'integer',
    'is_request' => true,      // Get value from request
    'is_required' => true,     // Fail if not provided
    'logical_operator' => 'AND',
]);
```

Request format for `in` operator:
```php
// As array
['status' => ['active', 'pending']]

// As comma-separated string (auto-converted)
['status' => 'active,pending']
```

## Relation Filter (Collection Filtering)

Filter related items without affecting the main query:

```php
// Filter to get only "Container" type identifiers
$containerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $typeRelation->id,
    'operator' => 'relation',
    'value' => 'Container',
]);

// Column uses this filter
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_filter_id' => $containerFilter->id,
    'title' => 'Container ID',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
    'position' => 1,
]);
```
