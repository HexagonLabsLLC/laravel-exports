# Pivot Export Configuration

Pivot exports create crosstab reports with dynamic columns from related data.

## Configuration Structure

```php
'pivot_config' => [
    // Primary grouping (rows). Entries are either a path string or an array
    // with a per-entry date bucket and header:
    'group_by' => [
        'customer.name',
        ['path' => 'created_at', 'format' => 'month', 'header' => 'Month'],
    ],
    'group_by_format' => 'week_year',      // Optional: bucket applied to STRING entries only
    'week_start' => 'sunday',              // Optional: for week buckets (mysql only)
    'group_by_headers' => ['Header Name'],

    // Sub-grouping (nested rows); same entry shapes, but the global
    // group_by_format never applies here - use array entries for buckets
    'sub_group_by' => ['relation.column'],
    'sub_group_by_headers' => ['Sub Header'],

    // Dynamic columns from relation
    'pivot_relation' => 'relation.path',
    'pivot_column' => 'column_name',
    'pivot_filter_param' => 'request_param',  // Filter which pivot columns appear

    // Value aggregation
    'value_relation' => '',                // Empty = base table
    'value_column' => 'amount',
    'aggregation' => 'sum',                // sum, count, avg, min, max

    // Output formatting
    'output_format' => 'grouped',          // 'grouped' or 'flat'
    'total_header' => 'Total',             // Column header for row totals
]
```

## Date Buckets

Available as an array entry's `format` or as the global `group_by_format`
(which applies only to string `group_by` entries):

| Format | Input | Output |
|--------|-------|--------|
| `day` | 2025-01-15 | 2025-01-15 |
| `week` (alias `week_year`) | 2025-01-15 | 2025-W03 (ISO) |
| `month` | 2025-01-15 | 2025-01 |
| `quarter` | 2025-01-15 | 2025-Q1 |
| `year` | 2025-01-15 | 2025 |
| (none) | value | value (unchanged) |

Bucket SQL is driver-aware (mysql, sqlite, pgsql); an unsupported
driver/bucket combination throws a RuntimeException.

### Week Start Options

Per entry (`'week_start'` key) or global:

| Value | Week starts on |
|-------|----------------|
| `monday` | Monday, ISO week numbering (all drivers, default) |
| `sunday` | Sunday, YEARWEEK mode 0 (mysql only; other drivers throw) |

## Output Formats

### Grouped Format

```csv
Work Week,Worker Name,Unloading,Loading,Total
2025-W01,,,
,John Smith,8.00,4.00,12.00
,Jane Doe,6.00,3.00,9.00
2025-W02,,,
,John Smith,10.00,5.00,15.00
```

### Flat Format

```csv
Work Week,Worker Name,Unloading,Loading,Total
2025-W01,John Smith,8.00,4.00,12.00
2025-W01,Jane Doe,6.00,3.00,9.00
2025-W02,John Smith,10.00,5.00,15.00
```

## Complete Example: Weekly Hours by Work Type

```php
$layout = ExportLayout::updateOrCreate(
    ['name' => 'Weekly Hours by Work Type'],
    [
        'export_model_id' => $laborPayModel->id,
        'description' => 'Weekly hours pivoted by work type',
        'is_pivot' => true,
        'pivot_config' => [
            // Group by week from timeclock start
            'group_by' => ['time_start'],
            'group_by_format' => 'week_year',
            'week_start' => 'sunday',
            'group_by_headers' => ['Work Week'],

            // Sub-group by worker
            'sub_group_by' => ['worker.name'],
            'sub_group_by_headers' => ['Worker Name'],

            // Pivot on work type names
            'pivot_relation' => 'workItem.workType',
            'pivot_column' => 'name',
            'pivot_filter_param' => 'work_type_ids',

            // Sum hours
            'value_relation' => '',
            'value_column' => 'hours',
            'aggregation' => 'sum',

            // Output config
            'output_format' => 'grouped',
            'total_header' => 'Total Hours',
        ],
    ]
);

// Pivot column with formatting
$hoursFunction = ExportFunction::where(
    'callable', 'App\\Services\\Export\\ExportFunctions::formatHoursDecimal'
)->first();

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Hours',
    'value_path' => 'hours',
    'is_expanded' => true,
    'expansion_data' => [
        'type' => 'pivot',
        'format_function' => $hoursFunction?->id,
    ],
    'position' => 1,
]);
```

## Simple Pivot (No Dynamic Columns)

For reports that just aggregate without dynamic pivot columns:

```php
'pivot_config' => [
    'group_by' => ['time_start'],
    'group_by_format' => 'week_year',
    'week_start' => 'sunday',
    'group_by_headers' => ['Work Week'],

    'sub_group_by' => ['worker.name'],
    'sub_group_by_headers' => ['Worker Name'],

    // No pivot relation - just aggregate
    'pivot_relation' => '',
    'pivot_column' => '',

    'value_relation' => '',
    'value_column' => 'hours',
    'aggregation' => 'sum',

    'total_header' => 'Total Hours',
    'output_format' => 'grouped',
]
```

Output:
```csv
Work Week,Worker Name,Total Hours
2025-W01,,
,John Smith,12.00
,Jane Doe,9.00
```

## Filters for Pivot Exports

Filters work the same as standard exports:

```php
// Required customer filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $customerRelation->id,
    'operator' => '=',
    'value_type' => 'integer',
    'is_request' => true,
    'is_required' => true,
    'logical_operator' => 'AND',
]);

// Optional date range
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $dateRelation->id,
    'operator' => 'between',
    'value_type' => 'array',
    'is_request' => true,
    'is_required' => false,
    'logical_operator' => 'AND',
]);

// Exclude null values (important for grouping)
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $timeStartRelation->id,
    'operator' => 'not_null',
    'value_type' => 'string',
    'is_request' => false,
    'logical_operator' => 'AND',
]);
```

## API Usage

```php
// Request
POST /api/v1/exports/layouts/{layoutId}/queue
{
    "customer_id": 123,
    "time_start": ["2025-01-01", "2025-12-31 23:59:59"],
    "work_type_ids": "1,2,5"
}

// Programmatic
$service = new DynamicExportService();
$data = $service->executeExport($layout, [
    'customer_id' => 123,
    'time_start' => ['2025-01-01', '2025-12-31 23:59:59'],
]);
```

## Important Notes

1. **Date boundaries**: Use `'2025-12-31 23:59:59'` for end dates to include full day
2. **Null values**: Add `not_null` filter for group_by columns to avoid grouping nulls
3. **Eloquent casts**: Library uses `toBase()->get()` internally to avoid cast interference
4. **Filter the pivot**: Use `pivot_filter_param` to let API consumers choose which pivot columns appear
