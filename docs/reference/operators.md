# Operators Reference

Complete reference for all filter operators.

## Comparison Operators

### Equals (`=`)

Exact match comparison.

```php
ExportFilter::create([
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
]);
```

**SQL:** `WHERE column = 'active'`

### Not Equals (`!=`)

Inverse of equals.

```php
ExportFilter::create([
    'operator' => '!=',
    'value' => 'deleted',
    'value_type' => 'string',
]);
```

**SQL:** `WHERE column != 'deleted'`

### Greater Than (`>`)

Value greater than specified.

```php
ExportFilter::create([
    'operator' => '>',
    'value' => 100,
    'value_type' => 'number',
]);
```

**SQL:** `WHERE column > 100`

### Less Than (`<`)

Value less than specified.

```php
ExportFilter::create([
    'operator' => '<',
    'value' => 50,
    'value_type' => 'number',
]);
```

**SQL:** `WHERE column < 50`

### Greater Than or Equal (`>=`)

Value greater than or equal to specified.

```php
ExportFilter::create([
    'operator' => '>=',
    'value' => 18,
    'value_type' => 'number',
]);
```

**SQL:** `WHERE column >= 18`

### Less Than or Equal (`<=`)

Value less than or equal to specified.

```php
ExportFilter::create([
    'operator' => '<=',
    'value' => 100,
    'value_type' => 'number',
]);
```

**SQL:** `WHERE column <= 100`

## Array Operators

### In (`in`)

Value matches one of the items in array.

```php
ExportFilter::create([
    'operator' => 'in',
    'value' => json_encode(['active', 'pending', 'approved']),
    'value_type' => 'array',
]);
```

**SQL:** `WHERE column IN ('active', 'pending', 'approved')`

**Request format:**
```php
// As array
$requestData = ['status' => ['active', 'pending']];

// As comma-separated string (auto-converted)
$requestData = ['status' => 'active,pending'];
```

### Not In (`not_in`)

Value does not match any item in array.

```php
ExportFilter::create([
    'operator' => 'not_in',
    'value' => json_encode(['deleted', 'banned']),
    'value_type' => 'array',
]);
```

**SQL:** `WHERE column NOT IN ('deleted', 'banned')`

### Between (`between`)

Value falls between two values (inclusive).

```php
ExportFilter::create([
    'operator' => 'between',
    'value' => json_encode(['2024-01-01', '2024-12-31']),
    'value_type' => 'array',
]);
```

**SQL:** `WHERE column BETWEEN '2024-01-01' AND '2024-12-31'`

**Common uses:**
- Date ranges
- Numeric ranges
- Price ranges

## Pattern Operators

### Like (`like`)

SQL pattern matching with wildcards.

```php
ExportFilter::create([
    'operator' => 'like',
    'value' => '%@company.com',
    'value_type' => 'string',
]);
```

**SQL:** `WHERE column LIKE '%@company.com'`

**Wildcards:**
- `%` - Any number of characters
- `_` - Single character

**Examples:**
- `'%@company.com'` - Ends with @company.com
- `'John%'` - Starts with John
- `'%admin%'` - Contains admin
- `'J_hn'` - Matches John, Jahn, etc.

## Null Operators

### Is Null (`null`)

Value is null.

```php
ExportFilter::create([
    'operator' => 'null',
    // value is ignored
]);
```

**SQL:** `WHERE column IS NULL`

### Is Not Null (`not_null`)

Value is not null.

```php
ExportFilter::create([
    'operator' => 'not_null',
    // value is ignored
]);
```

**SQL:** `WHERE column IS NOT NULL`

**Common use:** Check for verified users

```php
// email_verified_at is not null
ExportFilter::create([
    'export_model_relation_id' => $emailVerifiedAtRelation->id,
    'operator' => 'not_null',
]);
```

## Special Operators

### JSON Contains (`json_contains`)

Check if JSON column contains value.

```php
ExportFilter::create([
    'operator' => 'json_contains',
    'value' => json_encode(['role' => 'admin']),
    'value_type' => 'array',
]);
```

**SQL:** `WHERE JSON_CONTAINS(column, '{"role":"admin"}')`

**Use cases:**
- Filtering by JSON object properties
- Checking array membership in JSON arrays
- Settings or metadata columns

### Relation (`relation`)

Filter eager-loaded collections. Does NOT affect the main query.

```php
ExportFilter::create([
    'operator' => 'relation',
    'value' => 'Container',
    'value_type' => 'string',
]);
```

**Use:** Extracts specific items from related collections for column values.

**Example:** Get only "Container" type identifiers from a collection of identifiers.

```php
// Filter to get Container identifiers
$containerFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $identifierTypeRelation->id,  // points to type.title
    'operator' => 'relation',
    'value' => 'Container',
]);

// Column uses this filter to extract
ExportColumn::create([
    'export_filter_id' => $containerFilter->id,
    'title' => 'Container ID',
    'value_path' => 'identifiers.value',
    'aggregator' => 'first',
]);
```

## Operator Summary Table

| Operator | Description | Value Type | SQL Equivalent |
|----------|-------------|------------|----------------|
| `=` | Equals | Any | `= value` |
| `!=` | Not equals | Any | `!= value` |
| `>` | Greater than | number, date | `> value` |
| `<` | Less than | number, date | `< value` |
| `>=` | Greater or equal | number, date | `>= value` |
| `<=` | Less or equal | number, date | `<= value` |
| `in` | In array | array | `IN (...)` |
| `not_in` | Not in array | array | `NOT IN (...)` |
| `between` | Between values | array [2] | `BETWEEN ... AND ...` |
| `like` | Pattern match | string | `LIKE '%...'` |
| `null` | Is null | (ignored) | `IS NULL` |
| `not_null` | Is not null | (ignored) | `IS NOT NULL` |
| `json_contains` | JSON contains | array/object | `JSON_CONTAINS(...)` |
| `relation` | Collection filter | any | (no SQL, filters in PHP) |

## Value Types

| Type | Description | Example |
|------|-------------|---------|
| `string` | Text value | `"active"` |
| `number` | Numeric value | `100` |
| `boolean` | True/false | `true` |
| `array` | JSON array | `["a", "b"]` |
| `date` | Date string | `"2024-01-01"` |

## Logical Operators

Combine filters with AND/OR:

```php
// Filter 1: status = 'active' AND
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

**Result:** `WHERE status = 'active' OR status = 'pending'`

## Operator Methods

The `OperatorType` enum provides helper methods:

```php
use HexagonLabsLLC\LaravelExports\Enums\OperatorType;

// Get operator enum from string
$operator = OperatorType::getOperator('=');  // OperatorType::EQUALS

// Get query builder method name
$method = OperatorType::getCallable('=');     // 'where'
$method = OperatorType::getCallable('in');    // 'whereIn'
$method = OperatorType::getCallable('=', true); // 'orWhere' (for OR)
```
