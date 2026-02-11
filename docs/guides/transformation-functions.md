# Transformation Functions

Transformation functions format and modify column values during export. The package includes 22 built-in functions.

## Setup

Seed the built-in functions:

```bash
php artisan export:seed-functions
```

To update existing functions:

```bash
php artisan export:seed-functions --force
```

## Using Functions

### Basic Usage

```php
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

// Get the function
$formatDate = ExportFunction::where('name', 'Format Date')->first();

// Apply to a column
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Joined',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => json_encode(['F j, Y']),  // Parameters
    'position' => 1,
]);
```

### Function Parameters

Pass parameters as JSON array in `export_function_values`:

```php
// Single parameter
'export_function_values' => json_encode(['Y-m-d'])

// Multiple parameters
'export_function_values' => json_encode([100, '...'])

// Named parameters (some functions)
'export_function_values' => json_encode(['USD', 'en_US'])
```

## Date/Time Functions

### Format Date

Format dates using PHP date format strings.

```php
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'title' => 'Order Date',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => json_encode(['F j, Y']),  // "January 1, 2025"
]);
```

**Common formats:**
- `Y-m-d` - "2025-01-15"
- `d/m/Y` - "15/01/2025"
- `F j, Y` - "January 15, 2025"
- `M j, Y g:i A` - "Jan 15, 2025 3:30 PM"

### Format Date Human

Show relative time (e.g., "2 hours ago").

```php
$formatDateHuman = ExportFunction::where('name', 'Format Date Human')->first();

ExportColumn::create([
    'title' => 'Last Login',
    'value_path' => 'last_login_at',
    'export_function_id' => $formatDateHuman->id,
    // No parameters needed
]);
```

**Output:** "2 hours ago", "3 days ago", "1 week ago"

### Date Difference

Calculate difference between dates.

```php
$dateDiff = ExportFunction::where('name', 'Date Difference')->first();

ExportColumn::create([
    'title' => 'Days Since Registration',
    'value_path' => 'created_at',
    'export_function_id' => $dateDiff->id,
    'export_function_values' => json_encode([null, 'days']),  // Compare to now
]);
```

**Parameters:**
1. Second date (null = now)
2. Unit: `seconds`, `minutes`, `hours`, `days`, `weeks`, `months`, `years`

## String Functions

### Uppercase

Convert to uppercase.

```php
$uppercase = ExportFunction::where('name', 'Uppercase')->first();

// "john doe" -> "JOHN DOE"
```

### Lowercase

Convert to lowercase.

```php
$lowercase = ExportFunction::where('name', 'Lowercase')->first();

// "JOHN DOE" -> "john doe"
```

### Title Case

Convert to title case.

```php
$titleCase = ExportFunction::where('name', 'Title Case')->first();

// "john doe" -> "John Doe"
```

### Truncate

Limit string length with suffix.

```php
$truncate = ExportFunction::where('name', 'Truncate')->first();

ExportColumn::create([
    'title' => 'Description',
    'value_path' => 'description',
    'export_function_id' => $truncate->id,
    'export_function_values' => json_encode([100, '...']),
]);
```

**Parameters:**
1. Maximum length (default: 50)
2. Suffix (default: "...")

**Output:** "This is a long description that..." (truncated at 100 chars)

### Slug

Convert to URL-friendly slug.

```php
$slug = ExportFunction::where('name', 'Slug')->first();

ExportColumn::create([
    'title' => 'URL Slug',
    'value_path' => 'title',
    'export_function_id' => $slug->id,
    'export_function_values' => json_encode(['-']),
]);
```

**Output:** "Hello World" -> "hello-world"

### Replace

Find and replace text.

```php
$replace = ExportFunction::where('name', 'Replace')->first();

ExportColumn::create([
    'title' => 'Clean Phone',
    'value_path' => 'phone',
    'export_function_id' => $replace->id,
    'export_function_values' => json_encode(['-', '']),  // Remove dashes
]);
```

**Parameters:**
1. Search string
2. Replace string

### Extract

Extract text using regex.

```php
$extract = ExportFunction::where('name', 'Extract')->first();

ExportColumn::create([
    'title' => 'Domain',
    'value_path' => 'email',
    'export_function_id' => $extract->id,
    'export_function_values' => json_encode(['/@(.+)$/']),
]);
```

**Output:** "user@example.com" -> "example.com"

## Number Functions

### Format Number

Format with decimals and separators.

```php
$formatNumber = ExportFunction::where('name', 'Format Number')->first();

ExportColumn::create([
    'title' => 'Quantity',
    'value_path' => 'quantity',
    'export_function_id' => $formatNumber->id,
    'export_function_values' => json_encode([0, ',']),  // No decimals, comma separator
]);
```

**Parameters:**
1. Decimals (default: 2)
2. Thousands separator (default: ",")

**Output:** 1234567 -> "1,234,567"

### Format Currency

Format as currency.

```php
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();

ExportColumn::create([
    'title' => 'Total',
    'value_path' => 'total',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => json_encode(['USD', 'en_US']),
]);
```

**Parameters:**
1. Currency code (default: "USD")
2. Locale (default: "en_US")

**Output:** 1234.50 -> "$1,234.50"

### Round

Round to specified decimals.

```php
$round = ExportFunction::where('name', 'Round')->first();

ExportColumn::create([
    'title' => 'Average',
    'value_path' => 'average_score',
    'export_function_id' => $round->id,
    'export_function_values' => json_encode([2]),
]);
```

**Output:** 3.14159 -> 3.14

### Percentage

Format as percentage.

```php
$percentage = ExportFunction::where('name', 'Percentage')->first();

ExportColumn::create([
    'title' => 'Completion',
    'value_path' => 'progress',  // Stored as 0.75
    'export_function_id' => $percentage->id,
    'export_function_values' => json_encode([1]),
]);
```

**Output:** 0.75 -> "75.0%"

## Boolean Function

### Boolean Text

Convert boolean to custom text.

```php
$booleanText = ExportFunction::where('name', 'Boolean Text')->first();

ExportColumn::create([
    'title' => 'Active',
    'value_path' => 'is_active',
    'export_function_id' => $booleanText->id,
    'export_function_values' => json_encode(['Yes', 'No']),
]);
```

**Parameters:**
1. True text (default: "Yes")
2. False text (default: "No")

**Other examples:**
- `['Active', 'Inactive']`
- `['Enabled', 'Disabled']`
- `['Verified', 'Unverified']`

## Array/JSON Functions

### JSON Extract

Extract value from JSON using dot notation.

```php
$jsonExtract = ExportFunction::where('name', 'JSON Extract')->first();

ExportColumn::create([
    'title' => 'Settings Theme',
    'value_path' => 'settings',  // JSON column
    'export_function_id' => $jsonExtract->id,
    'export_function_values' => json_encode(['appearance.theme']),
]);
```

**Input:** `{"appearance": {"theme": "dark"}}`
**Output:** "dark"

### Array Join

Join array elements with separator.

```php
$arrayJoin = ExportFunction::where('name', 'Array Join')->first();

ExportColumn::create([
    'title' => 'Tags',
    'value_path' => 'tags',  // Array column
    'export_function_id' => $arrayJoin->id,
    'export_function_values' => json_encode([', ']),
]);
```

**Input:** `['php', 'laravel', 'vue']`
**Output:** "php, laravel, vue"

### Array Count

Count array elements.

```php
$arrayCount = ExportFunction::where('name', 'Array Count')->first();

ExportColumn::create([
    'title' => 'Tag Count',
    'value_path' => 'tags',
    'export_function_id' => $arrayCount->id,
]);
```

**Input:** `['php', 'laravel', 'vue']`
**Output:** 3

## Utility Functions

### Default Value

Provide fallback for empty values.

```php
$defaultValue = ExportFunction::where('name', 'Default Value')->first();

ExportColumn::create([
    'title' => 'Company',
    'value_path' => 'company_name',
    'export_function_id' => $defaultValue->id,
    'export_function_values' => json_encode(['N/A']),
]);
```

**Note:** Consider using the column's `default` property instead.

### Concatenate

Join two values with separator.

```php
$concatenate = ExportFunction::where('name', 'Concatenate')->first();

ExportColumn::create([
    'title' => 'Full Name',
    'value_path' => 'first_name',
    'export_function_id' => $concatenate->id,
    'export_function_values' => json_encode(['last_name', ' ']),  // {first_name} {last_name}
]);
```

**Note:** This function is limited. For complex concatenation, consider a custom function.

### Hash

Hash values using specified algorithm.

```php
$hash = ExportFunction::where('name', 'Hash')->first();

ExportColumn::create([
    'title' => 'ID Hash',
    'value_path' => 'id',
    'export_function_id' => $hash->id,
    'export_function_values' => json_encode(['sha256']),
]);
```

**Algorithms:** md5, sha1, sha256, sha512

### Mask

Mask sensitive data.

```php
$mask = ExportFunction::where('name', 'Mask')->first();

ExportColumn::create([
    'title' => 'Phone',
    'value_path' => 'phone',
    'export_function_id' => $mask->id,
    'export_function_values' => json_encode([4, '*']),
]);
```

**Parameters:**
1. Visible characters from start (default: 4)
2. Mask character (default: "*")

**Output:** "1234567890" -> "1234******"

## Creating Custom Functions

### Register a Custom Function

```php
ExportFunction::create([
    'name' => 'Custom Format',
    'callable' => 'App\Services\ExportFunctions::customFormat',
    'parameter_count' => 2,
    'value_parameter_index' => 0,
    'metadata' => [
        'description' => 'Apply custom formatting',
        'parameters' => ['value', 'option'],
        'example' => 'customFormat($value, "option")',
    ],
]);
```

### Implement the Function

```php
// App/Services/ExportFunctions.php
namespace App\Services;

class ExportFunctions
{
    public static function customFormat($value, $option = null)
    {
        // Your formatting logic
        return "Formatted: {$value}";
    }
}
```

## Function Reference Table

| Name | Parameters | Example Output |
|------|------------|----------------|
| Format Date | format | "January 1, 2025" |
| Format Date Human | - | "2 hours ago" |
| Date Difference | date2, unit | 30 |
| Uppercase | - | "HELLO" |
| Lowercase | - | "hello" |
| Title Case | - | "Hello World" |
| Truncate | length, suffix | "Hello..." |
| Slug | separator | "hello-world" |
| Replace | search, replace | "replaced" |
| Extract | pattern | "extracted" |
| Format Number | decimals, separator | "1,234.56" |
| Format Currency | currency, locale | "$1,234.56" |
| Round | decimals | 3.14 |
| Percentage | decimals | "75.0%" |
| Boolean Text | true, false | "Yes" |
| JSON Extract | path | "value" |
| Array Join | separator | "a, b, c" |
| Array Count | - | 3 |
| Default Value | default | "N/A" |
| Concatenate | value2, separator | "a b" |
| Hash | algorithm | "abc123..." |
| Mask | visible, char | "1234****" |

## Related Documentation

- [Creating Layouts](creating-layouts.md) - Using functions in columns
- [Functions Reference](../reference/functions.md) - Complete function details
