# Functions Reference

23 built-in transformation functions. Seed with: `php artisan export:seed-functions`

## Using Functions

```php
$function = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'export_function_id' => $function->id,
    'export_function_values' => json_encode(['Y-m-d']),
    // ...
]);
```

## Date/Time Functions

| Name | Parameters | Example Output |
|------|------------|----------------|
| Format Date | format | January 15, 2024 |
| Format Date Human | - | 2 hours ago |
| Format Timestamp | format | 2024-01-15 14:30:00 |
| Date Difference | date2, unit | 30 |

### Format Date

```php
'export_function_values' => json_encode(['F j, Y'])
```

| Format | Output |
|--------|--------|
| `Y-m-d` | 2024-01-15 |
| `d/m/Y` | 15/01/2024 |
| `F j, Y` | January 15, 2024 |
| `M j, Y` | Jan 15, 2024 |
| `m/d/Y g:i A` | 01/15/2024 3:30 PM |

### Date Difference

```php
// Days since (compare to now)
'export_function_values' => json_encode([null, 'days'])

// Days between two dates
'export_function_values' => json_encode(['2024-12-31', 'days'])
```

Units: `seconds`, `minutes`, `hours`, `days`, `weeks`, `months`, `years`

## String Functions

| Name | Parameters | Example Output |
|------|------------|----------------|
| Uppercase | - | HELLO |
| Lowercase | - | hello |
| Title Case | - | Hello World |
| Truncate | length, suffix | Hello... |
| Slug | separator | hello-world |
| Replace | search, replace | result |
| Extract | pattern | extracted |

### Truncate

```php
'export_function_values' => json_encode([100, '...'])
```

### Replace

```php
// Remove dashes
'export_function_values' => json_encode(['-', ''])
```

### Extract (Regex)

```php
// Extract numbers
'export_function_values' => json_encode(['/[0-9]+/'])
```

## Number Functions

| Name | Parameters | Example Output |
|------|------------|----------------|
| Format Number | decimals, separator | 1,234.56 |
| Format Currency | currency, locale | $1,234.56 |
| Round | decimals | 3.14 |
| Percentage | decimals | 75.0% |

### Format Number

```php
'export_function_values' => json_encode([2, ','])
```

### Format Currency

```php
'export_function_values' => json_encode(['USD', 'en_US'])
```

| Currency | Locale | Output |
|----------|--------|--------|
| USD | en_US | $1,234.50 |
| EUR | de_DE | 1.234,50 EUR |
| GBP | en_GB | 1,234.50 |

### Percentage

```php
// Input 0.75 -> Output 75.0%
'export_function_values' => json_encode([1])  // 1 decimal
```

## Boolean Function

### Boolean Text

```php
'export_function_values' => json_encode(['Active', 'Inactive'])
```

| Input | True Text | False Text | Output |
|-------|-----------|------------|--------|
| true | Yes | No | Yes |
| false | Active | Inactive | Inactive |

## Array/JSON Functions

| Name | Parameters | Example Output |
|------|------------|----------------|
| JSON Extract | path | value |
| Array Join | separator | a, b, c |
| Array Count | - | 3 |

### JSON Extract

```php
'export_function_values' => json_encode(['user.name'])
```

### Array Join

```php
'export_function_values' => json_encode([', '])
```

## Utility Functions

| Name | Parameters | Example Output |
|------|------------|----------------|
| Default Value | default | N/A |
| Concatenate | value2, separator | a - b |
| Hash | algorithm | abc123... |
| Mask | visible, char | 1234**** |

### Mask

```php
// Show first 4 chars, mask rest with *
'export_function_values' => json_encode([4, '*'])
```

## Custom Functions

Register custom functions in a seeder:

```php
ExportFunction::updateOrCreate(
    ['callable' => 'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'],
    [
        'name' => 'Format Hours Decimal',
        'parameter_count' => 1,
        'value_parameter_index' => 0,
    ]
);
```

Implementation:

```php
// app/Services/Export/ExportFunctions.php
namespace App\Services\Export;

class ExportFunctions
{
    public static function formatHoursDecimal($hours): string
    {
        return number_format((float) ($hours ?? 0), 2);
    }

    public static function formatWeekYear($date): string
    {
        if (!$date) return '';
        $carbon = \Carbon\Carbon::parse($date);
        return $carbon->year . '-W' . str_pad($carbon->weekOfYear, 2, '0', STR_PAD_LEFT);
    }
}
```
