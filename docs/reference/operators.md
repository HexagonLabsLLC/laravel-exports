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
    'value_type' => 'integer',
]);
```

**SQL:** `WHERE column > 100`

### Less Than (`<`)

Value less than specified.

```php
ExportFilter::create([
    'operator' => '<',
    'value' => 50,
    'value_type' => 'integer',
]);
```

**SQL:** `WHERE column < 50`

### Greater Than or Equal (`>=`)

Value greater than or equal to specified.

```php
ExportFilter::create([
    'operator' => '>=',
    'value' => 18,
    'value_type' => 'integer',
]);
```

**SQL:** `WHERE column >= 18`

### Less Than or Equal (`<=`)

Value less than or equal to specified.

```php
ExportFilter::create([
    'operator' => '<=',
    'value' => 100,
    'value_type' => 'integer',
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

The comma-separated form is coerced to an array for the `in`, `not_in`, and `between`
operators, for both layout filters and column-attached filters.

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

The canonical `value` is a four-element array, `[relation, path, operator, expected]`,
compared against each item of the column's collection relation. A scalar `value` also
works - the filter's own relation row supplies the comparison path, as below. Only a
malformed array value (fewer than three elements) fails validation.

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
    'export_model_relation_id' => $identifiersRelation->id,  // the collection relation
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
| `>` | Greater than | integer, float, string (dates) | `> value` |
| `<` | Less than | integer, float, string (dates) | `< value` |
| `>=` | Greater or equal | integer, float, string (dates) | `>= value` |
| `<=` | Less or equal | integer, float, string (dates) | `<= value` |
| `in` | In array | array | `IN (...)` |
| `not_in` | Not in array | array | `NOT IN (...)` |
| `between` | Between values | array [2] | `BETWEEN ... AND ...` |
| `like` | Pattern match | string | `LIKE '%...'` |
| `null` | Is null | (ignored) | `IS NULL` |
| `not_null` | Is not null | (ignored) | `IS NOT NULL` |
| `json_contains` | JSON contains | array/object | `JSON_CONTAINS(...)` |
| `relation` | Collection filter | any | (no SQL, filters in PHP) |

## Value Types

`value_type` is stored on the filter and validated by `export:validate`; only these
five values are recognized. At runtime it only changes behavior for `array`, which
tells the service to JSON-decode the stored value. Dates are stored as strings.

| Type | Description | Example |
|------|-------------|---------|
| `string` | Text value (including dates) | `"active"`, `"2024-01-01"` |
| `integer` | Whole number | `100` |
| `float` | Decimal number | `19.99` |
| `boolean` | True/false | `true` |
| `array` | JSON array | `["a", "b"]` |

## Logical Operators

Filters apply in creation order (`export_filters` rows ordered by id, then any layout
`filter_definitions` entries). An `or` filter groups with the filter **before** it, and
any run of consecutive `or` filters extends that same group. Groups are then ANDed
together, so an or-pair can never disjoin an unrelated scoping filter:

```php
// Filter 1: status = 'active'
ExportFilter::create([
    'operator' => '=',
    'value' => 'active',
    'logical_operator' => 'AND',
]);

// Filter 2: OR status = 'pending' - groups with filter 1
ExportFilter::create([
    'operator' => '=',
    'value' => 'pending',
    'logical_operator' => 'OR',
]);

// Filter 3: AND created_at >= '2024-01-01' - starts a new group
ExportFilter::create([
    'operator' => '>=',
    'value' => '2024-01-01',
    'logical_operator' => 'AND',
]);
```

**Result:** `WHERE (status = 'active' OR status = 'pending') AND created_at >= '2024-01-01'`

In short: `A, or B, C` produces `(A OR B) AND C`. An `or` on the first filter has
nothing to attach to, so it starts a group like an `and` would (`export:validate`
reports this as a warning).

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
