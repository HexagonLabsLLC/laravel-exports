# Functions Reference

Complete reference for all 23 built-in transformation functions.

## Seeding Functions

```bash
# First time
php artisan export:seed-functions

# Update existing
php artisan export:seed-functions --force
```

## Usage in Columns

```php
$function = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_function_id' => $function->id,
    'export_function_values' => [null, 'Y-m-d'],  // Parameters
    // ...
]);
```

`export_function_values` is a plain PHP array of positional parameters. The column value is injected at the function's value position (index 0 for every built-in function), so put `null` in that slot and start configured parameters at index 1.

---

## Date/Time Functions

### Format Date

Format dates using PHP date format strings.

**Name:** `Format Date`
**Callable:** `TransformationFunctions::formatDate`
**Parameters:** `date`, `format`
**Default Format:** `Y-m-d H:i:s`

```php
'export_function_values' => [null, 'F j, Y']
```

| Format | Example Output |
|--------|----------------|
| `Y-m-d` | 2024-01-15 |
| `d/m/Y` | 15/01/2024 |
| `F j, Y` | January 15, 2024 |
| `M j, Y` | Jan 15, 2024 |
| `l, F j, Y` | Monday, January 15, 2024 |
| `m/d/Y g:i A` | 01/15/2024 3:30 PM |

---

### Format Date Human

Display relative time.

**Name:** `Format Date Human`
**Callable:** `TransformationFunctions::formatDateHuman`
**Parameters:** `date`

```php
// No parameters needed
'export_function_values' => null
```

| Input | Output |
|-------|--------|
| Now | just now |
| 1 hour ago | 1 hour ago |
| Yesterday | 1 day ago |
| Last week | 1 week ago |
| Last month | 1 month ago |

---

### Format Timestamp

Format a timestamp with timezone conversion.

**Name:** `Format Timestamp`
**Callable:** `TransformationFunctions::formatTimestamp`
**Parameters:** `date`, `format`, `timezone`
**Default Format:** `Y-m-d H:i:s`
**Default Timezone:** `UTC`

```php
'export_function_values' => [null, 'Y-m-d H:i:s', 'America/New_York']
```

---

### Date Difference

Calculate difference between dates.

**Name:** `Date Difference`
**Callable:** `TransformationFunctions::dateDifference`
**Parameters:** `date1`, `date2`, `unit`
**Default Unit:** `days`

```php
// Days since registration (compare to now)
'export_function_values' => [null, null, 'days']

// Days between two dates
'export_function_values' => [null, '2024-12-31', 'days']
```

**Units:** `seconds`, `minutes`, `hours`, `days`, `weeks`, `months`, `years`

---

## String Functions

### Uppercase

Convert to uppercase.

**Name:** `Uppercase`
**Callable:** `TransformationFunctions::uppercase`
**Parameters:** `string`

```php
// No parameters needed
```

| Input | Output |
|-------|--------|
| hello world | HELLO WORLD |

---

### Lowercase

Convert to lowercase.

**Name:** `Lowercase`
**Callable:** `TransformationFunctions::lowercase`
**Parameters:** `string`

```php
// No parameters needed
```

| Input | Output |
|-------|--------|
| HELLO WORLD | hello world |

---

### Title Case

Convert to title case.

**Name:** `Title Case`
**Callable:** `TransformationFunctions::titleCase`
**Parameters:** `string`

```php
// No parameters needed
```

| Input | Output |
|-------|--------|
| hello world | Hello World |
| HELLO WORLD | Hello World |

---

### Truncate

Limit string length with suffix.

**Name:** `Truncate`
**Callable:** `TransformationFunctions::truncate`
**Parameters:** `string`, `length`, `suffix`
**Defaults:** length=50, suffix='...'

```php
'export_function_values' => [null, 100, '...']
```

| Input | Length | Output |
|-------|--------|--------|
| This is a very long text | 15 | This is a very... |

`Str::limit()` keeps `length` characters and then appends the suffix, so the result can
be longer than `length`.

---

### Slug

Convert to URL-friendly slug.

**Name:** `Slug`
**Callable:** `TransformationFunctions::slug`
**Parameters:** `string`, `separator`
**Default Separator:** `-`

```php
'export_function_values' => [null, '-']
```

| Input | Output |
|-------|--------|
| Hello World | hello-world |
| Product Name 2024 | product-name-2024 |

---

### Replace

Find and replace text.

**Name:** `Replace`
**Callable:** `TransformationFunctions::replace`
**Parameters:** `string`, `search`, `replace`

```php
'export_function_values' => [null, '-', '']  // Remove dashes
```

| Input | Search | Replace | Output |
|-------|--------|---------|--------|
| 123-456-7890 | - | (empty) | 1234567890 |
| foo bar | bar | baz | foo baz |

---

### Extract

Extract text using regex pattern.

**Name:** `Extract`
**Callable:** `TransformationFunctions::extract`
**Parameters:** `string`, `pattern`

```php
'export_function_values' => [null, '/[0-9]+/']
```

| Input | Pattern | Output |
|-------|---------|--------|
| Order #12345 | `/[0-9]+/` | 12345 |
| user@example.com | `/@(.+)$/` | @example.com |

The whole match is returned; capture groups are ignored.

---

## Number Functions

### Format Number

Format with decimals and separators.

**Name:** `Format Number`
**Callable:** `TransformationFunctions::formatNumber`
**Parameters:** `number`, `decimals`, `thousands_separator`
**Defaults:** decimals=2, separator=','

```php
'export_function_values' => [null, 2, ',']
```

| Input | Decimals | Separator | Output |
|-------|----------|-----------|--------|
| 1234567 | 0 | , | 1,234,567 |
| 1234.5678 | 2 | , | 1,234.57 |

---

### Format Currency

Format as currency.

**Name:** `Format Currency`
**Callable:** `TransformationFunctions::formatCurrency`
**Parameters:** `number`, `currency`, `locale`
**Defaults:** currency='USD', locale='en_US'

```php
'export_function_values' => [null, 'USD', 'en_US']
```

| Input | Currency | Locale | Output |
|-------|----------|--------|--------|
| 1234.50 | USD | en_US | $1,234.50 |
| 1234.50 | EUR | de_DE | 1.234,50 with a euro sign suffix |
| 1234.50 | GBP | en_GB | 1,234.50 with a pound sign prefix |

---

### Round

Round to specified decimals.

**Name:** `Round`
**Callable:** `TransformationFunctions::round`
**Parameters:** `number`, `decimals`
**Default Decimals:** 0

```php
'export_function_values' => [null, 2]
```

| Input | Decimals | Output |
|-------|----------|--------|
| 3.14159 | 2 | 3.14 |
| 3.5 | 0 | 4 |

---

### Percentage

Format as percentage.

**Name:** `Percentage`
**Callable:** `TransformationFunctions::percentage`
**Parameters:** `number`, `decimals`
**Default Decimals:** 2

```php
'export_function_values' => [null, 1]
```

| Input | Decimals | Output |
|-------|----------|--------|
| 0.75 | 1 | 75.0% |
| 0.333 | 2 | 33.30% |

---

## Boolean Function

### Boolean Text

Convert boolean to custom text.

**Name:** `Boolean Text`
**Callable:** `TransformationFunctions::booleanText`
**Parameters:** `value`, `true_text`, `false_text`
**Defaults:** true='Yes', false='No'

```php
'export_function_values' => [null, 'Active', 'Inactive']
```

| Input | True Text | False Text | Output |
|-------|-----------|------------|--------|
| true | Yes | No | Yes |
| false | Active | Inactive | Inactive |
| 1 | Enabled | Disabled | Enabled |

---

## Array/JSON Functions

### JSON Extract

Extract value from JSON using dot notation.

**Name:** `JSON Extract`
**Callable:** `TransformationFunctions::jsonExtract`
**Parameters:** `json`, `path`

```php
'export_function_values' => [null, 'user.name']
```

| Input | Path | Output |
|-------|------|--------|
| `{"user":{"name":"John"}}` | user.name | John |
| `{"items":[1,2,3]}` | items.0 | 1 |

---

### Array Join

Join array elements with separator.

**Name:** `Array Join`
**Callable:** `TransformationFunctions::arrayJoin`
**Parameters:** `array`, `separator`
**Default Separator:** ', '

```php
'export_function_values' => [null, ', ']
```

| Input | Separator | Output |
|-------|-----------|--------|
| ['a', 'b', 'c'] | `', '` | a, b, c |
| ['a', 'b', 'c'] | `','` | a,b,c |
| ['a', 'b', 'c'] | `'-'` | a-b-c |

---

### Array Count

Count array elements.

**Name:** `Array Count`
**Callable:** `TransformationFunctions::arrayCount`
**Parameters:** `array`

```php
// No parameters needed
```

| Input | Output |
|-------|--------|
| ['a', 'b', 'c'] | 3 |
| [] | 0 |

---

## Utility Functions

### Default Value

Provide fallback for empty values.

**Name:** `Default Value`
**Callable:** `TransformationFunctions::defaultValue`
**Parameters:** `value`, `default`
**Default:** '' (empty string)

```php
'export_function_values' => [null, 'N/A']
```

| Input | Default | Output |
|-------|---------|--------|
| null | N/A | N/A |
| '' | N/A | N/A |
| 'John' | N/A | John |

**Note:** Consider using the column's `default` property instead.

---

### Concatenate

Join two values with separator.

**Name:** `Concatenate`
**Callable:** `TransformationFunctions::concatenate`
**Parameters:** `value1`, `value2`, `separator`
**Default Separator:** ' '

```php
'export_function_values' => [null, 'suffix', ' - ']
```

**Note:** Limited functionality. The second value is a literal, not another column value.

---

### Hash

Hash value using specified algorithm.

**Name:** `Hash`
**Callable:** `TransformationFunctions::hash`
**Parameters:** `value`, `algorithm`
**Default Algorithm:** sha256

```php
'export_function_values' => [null, 'sha256']
```

**Algorithms:** md5, sha1, sha256, sha512

---

### Mask

Mask sensitive data.

**Name:** `Mask`
**Callable:** `TransformationFunctions::mask`
**Parameters:** `string`, `visible_chars`, `mask_char`
**Defaults:** visible=4, mask='*'

```php
'export_function_values' => [null, 4, '*']
```

| Input | Visible | Mask | Output |
|-------|---------|------|--------|
| 1234567890 | 4 | * | 1234****** |
| secret | 2 | # | se#### |
| ab | 4 | * | ab |

---

## Function Summary Table

| Name | Category | Parameters | Example Output |
|------|----------|------------|----------------|
| Format Date | Date | format | January 15, 2024 |
| Format Date Human | Date | - | 2 hours ago |
| Format Timestamp | Date | format, timezone | 2024-01-15 14:30:00 |
| Date Difference | Date | date2, unit | 30 |
| Uppercase | String | - | HELLO |
| Lowercase | String | - | hello |
| Title Case | String | - | Hello World |
| Truncate | String | length, suffix | Hello... |
| Slug | String | separator | hello-world |
| Replace | String | search, replace | result |
| Extract | String | pattern | extracted |
| Format Number | Number | decimals, separator | 1,234.56 |
| Format Currency | Number | currency, locale | $1,234.56 |
| Round | Number | decimals | 3.14 |
| Percentage | Number | decimals | 75.0% |
| Boolean Text | Boolean | true, false | Yes |
| JSON Extract | Array | path | value |
| Array Join | Array | separator | a, b, c |
| Array Count | Array | - | 3 |
| Default Value | Utility | default | N/A |
| Concatenate | Utility | value2, separator | a - b |
| Hash | Utility | algorithm | abc123... |
| Mask | Utility | visible, char | 1234****** |
