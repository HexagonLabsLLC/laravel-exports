# Formatted Export

Apply transformation functions to format column values.

## Scenario

Export users with formatted dates, currency, and status text.

## Sample Data

```php
// users table
[
    ['name' => 'John', 'balance' => 1234.567, 'is_active' => true, 'created_at' => '2024-01-15 14:30:00'],
    ['name' => 'Jane', 'balance' => 5678.90, 'is_active' => false, 'created_at' => '2024-02-20 09:15:00'],
]
```

## Setup

### 1. Seed Functions

```bash
php artisan export:seed-functions
```

### 2. Create Layout

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFunction};

$userModel = ExportModel::where('title', 'User')->first();

$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'formatted_user_report',
    'title' => 'Formatted User Report',
]);
```

### 3. Create Columns with Functions

```php
// Get transformation functions
$formatDate = ExportFunction::where('name', 'Format Date')->first();
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();
$booleanText = ExportFunction::where('name', 'Boolean Text')->first();
$titleCase = ExportFunction::where('name', 'Title Case')->first();

// Name with title case
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'export_function_id' => $titleCase->id,
    'position' => 1,
]);

// Balance formatted as currency
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Account Balance',
    'value_path' => 'balance',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'position' => 2,
]);

// Status as Yes/No
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Active',
    'value_path' => 'is_active',
    'export_function_id' => $booleanText->id,
    'export_function_values' => [null, 'Yes', 'No'],
    'position' => 3,
]);

// Date formatted nicely
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Member Since',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'F j, Y'],  // "January 15, 2024"
    'position' => 4,
]);
```

### 4. Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'formatted-users.csv');
```

## Expected Output

**Before (without functions):**
```csv
Name,Account Balance,Active,Member Since
john,1234.567,1,2024-01-15 14:30:00
jane,5678.9,0,2024-02-20 09:15:00
```

**After (with functions):**
```csv
Name,Account Balance,Active,Member Since
John,"$1,234.57",Yes,"January 15, 2024"
Jane,"$5,678.90",No,"February 20, 2024"
```

(Values containing the delimiter are enclosed by `fputcsv`.)

## Common Formatting Patterns

### Date Formats

```php
// ISO format
'export_function_values' => [null, 'Y-m-d']
// Output: "2024-01-15"

// US format
'export_function_values' => [null, 'm/d/Y']
// Output: "01/15/2024"

// European format
'export_function_values' => [null, 'd/m/Y']
// Output: "15/01/2024"

// Full date
'export_function_values' => [null, 'l, F j, Y']
// Output: "Monday, January 15, 2024"

// With time
'export_function_values' => [null, 'M j, Y g:i A']
// Output: "Jan 15, 2024 2:30 PM"
```

### Number Formats

```php
// Format Number: decimals, thousands separator
$formatNumber = ExportFunction::where('name', 'Format Number')->first();
'export_function_values' => [null, 2, ',']
// Output: 1234567 -> "1,234,567.00"

// Round
$round = ExportFunction::where('name', 'Round')->first();
'export_function_values' => [null, 2]
// Output: 3.14159 -> 3.14

// Percentage
$percentage = ExportFunction::where('name', 'Percentage')->first();
'export_function_values' => [null, 1]
// Output: 0.756 -> "75.6%"
```

### Currency Formats

```php
// USD
'export_function_values' => [null, 'USD', 'en_US']
// Output: "$1,234.56"

// EUR
'export_function_values' => [null, 'EUR', 'de_DE']
// Output: "1.234,56" followed by the euro sign

// GBP
'export_function_values' => [null, 'GBP', 'en_GB']
// Output: "1,234.56" prefixed with the pound sign
```

### Boolean Formats

```php
// Yes/No (default)
'export_function_values' => [null, 'Yes', 'No']

// Active/Inactive
'export_function_values' => [null, 'Active', 'Inactive']

// Enabled/Disabled
'export_function_values' => [null, 'Enabled', 'Disabled']

// Custom symbols
'export_function_values' => [null, 'V', 'X']
```

### String Manipulation

```php
// Truncate long text
$truncate = ExportFunction::where('name', 'Truncate')->first();
'export_function_values' => [null, 50, '...']
// Output: the first 50 characters followed by "..."

// Mask sensitive data
$mask = ExportFunction::where('name', 'Mask')->first();
'export_function_values' => [null, 4, '*']
// Output: "1234567890" -> "1234******"
```

## Combining Functions with Aggregations

```php
// Sum orders and format as currency
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Revenue',
    'value_path' => 'orders.total',
    'aggregator' => 'sum',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'position' => 1,
]);
```

**Processing order:**
1. Get orders collection
2. Sum the totals -> 5432.10
3. Format as currency -> "$5,432.10"

## Notes

- Functions are applied after aggregations
- Parameters are passed as a plain PHP array, positional, with `null` at the value position (index 0)
- Use `export:seed-functions` to get all 23 built-in functions
- See [Transformation Functions Guide](../../guides/transformation-functions.md) for full list
