# Pivot Reports

Build Excel-style crosstab reports where related values become columns and rows
are aggregated by group.

This is a different feature from [Pivot Tables](pivot-tables.md), which exports
attributes from BelongsToMany intermediate tables using `.pivot.` notation. A
pivot report turns rows into columns: one row per group, one column per
distinct value of a chosen relation, and an aggregated number in each cell.

## A Minimal Pivot Report

A pivot layout needs no `export_columns` rows - the whole report is described
by `pivot_config`:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$layout = ExportLayout::create([
    'model' => \App\Models\Order::class,
    'name' => 'revenue_by_customer',
    'title' => 'Revenue by Customer',
    'is_pivot' => true,
    'pivot_config' => [
        'group_by' => ['customer.name'],
        'group_by_headers' => ['Customer'],

        'pivot_relation' => 'items.product.category.name',
        'pivot_column' => 'name',

        'value_relation' => 'items',
        'value_column' => 'amount',
        'aggregation' => 'sum',

        'output_format' => 'flat',
    ],
]);

$results = app(DynamicExportService::class)->executeExport($layout);
```

```
Customer   | Hardware | Software | Total
Acme Ltd.  | 1200.00  | 300.00   | 1500.00
Globex Co. | 800.00   | 0.00     | 800.00
```

One column appears per distinct category name found in the data (sorted
alphabetically), plus a `Total`. Cell values are aggregated with SQL and
rendered through `number_format($value, 2)`.

## Configuration Reference

| Key | Description |
|-----|-------------|
| `group_by` | Primary grouping entries (rows). Path strings or bucket arrays, see below |
| `group_by_headers` | Column headers for `group_by`, matched by index |
| `group_by_format` | Optional date bucket applied to every STRING `group_by` entry |
| `week_start` | `monday` (ISO, default) or `sunday` (mysql only) for week buckets |
| `sub_group_by` | Secondary grouping entries (nested rows); same shapes as `group_by`, but `group_by_format` never applies here |
| `sub_group_by_headers` | Column headers for `sub_group_by`, matched by index |
| `pivot_relation` | Relation path whose values become the dynamic columns; empty for a plain aggregate |
| `pivot_column` | Column on the pivot relation used for the headers (default `id`) |
| `pivot_filter_param` | Request parameter that limits which pivot columns appear |
| `value_relation` | Table the aggregated value comes from; empty means the base table |
| `value_column` | Column to aggregate (default `id`) |
| `aggregation` | `sum`, `count`, `avg`, `min`, or `max` (default `count`) |
| `output_format` | `flat` or `grouped`, see below |
| `total_header` | Header for the row-total column (default `Total`) |

Group entries are either a plain path string or an array with a date bucket:

```php
'group_by' => [
    'customer.name',                          // plain path (column or relation.column)
    [
        'path' => 'created_at',               // required
        'format' => 'month',                  // optional date bucket
        'header' => 'Month',                  // optional, beats group_by_headers
        'week_start' => 'monday',             // optional, week buckets only
    ],
],
```

### Date Buckets

| Format | Input | Output |
|--------|-------|--------|
| `day` | 2026-01-02 10:30:00 | 2026-01-02 |
| `week` (alias `week_year`) | 2026-01-02 10:30:00 | 2026-W01 (ISO) |
| `month` | 2026-01-02 10:30:00 | 2026-01 |
| `quarter` | 2026-01-02 10:30:00 | 2026-Q1 |
| `year` | 2026-01-02 10:30:00 | 2026 |

Bucket SQL is generated per database driver (mysql, sqlite, pgsql), so the
same layout runs identically in tests and production. Bucket output formats
are fixed because they double as the SQL group key. `week_start => 'sunday'`
uses MySQL's YEARWEEK mode 0 and is mysql-only; other drivers throw a
`RuntimeException` instead of silently numbering weeks differently. Unknown
buckets and entries without a `path` also throw.

The global `group_by_format` is the backwards-compatible form: it buckets
every string `group_by` entry and never touches array entries or
`sub_group_by`. Prefer per-entry `format` - it lets relation paths and date
buckets coexist in one grouping.

## Grouped Output

`output_format => 'grouped'` prints each primary group as its own header row,
with the detail rows nested under it:

```php
'pivot_config' => [
    'group_by' => ['customer.name'],
    'group_by_headers' => ['Customer'],

    'sub_group_by' => [
        ['path' => 'created_at', 'format' => 'day', 'header' => 'Date'],
    ],

    'pivot_relation' => 'items.product.category.name',
    'pivot_column' => 'name',
    'value_relation' => 'items',
    'value_column' => 'quantity',
    'aggregation' => 'sum',
    'output_format' => 'grouped',
],
```

```
Customer   | Date       | Hardware | Software | Total
Acme Ltd.  |            |          |          |
           | 2026-01-01 | 1.00     | 2.00     | 3.00
           | 2026-01-02 | 6.00     | 4.00     | 10.00
Globex Co. |            |          |          |
           | 2026-01-01 | 1.00     | 2.00     | 3.00
```

The header row carries only the group value; sub-rows blank the group column.
With `output_format => 'flat'` the same config repeats the customer name on
every row instead:

```
Customer   | Date       | Hardware | Software | Total
Acme Ltd.  | 2026-01-01 | 1.00     | 2.00     | 3.00
Acme Ltd.  | 2026-01-02 | 6.00     | 4.00     | 10.00
Globex Co. | 2026-01-01 | 1.00     | 2.00     | 3.00
```

### One Row Per Record

A pivot aggregates by its group keys, so two orders on the same day collapse
into one row. To keep them apart, add a distinguishing entry to the sub-group:

```php
'sub_group_by' => [
    ['path' => 'created_at', 'format' => 'day', 'header' => 'Date'],
    'order_number',
],
```

```
Customer   | Date       | Order number | Hardware | Software | Total
Acme Ltd.  |            |              |          |          |
           | 2026-01-01 | ORD-1001     | 1.00     | 2.00     | 3.00
           | 2026-01-02 | ORD-1002     | 1.00     | 2.00     | 3.00
           | 2026-01-02 | ORD-1003     | 5.00     | 2.00     | 7.00
```

## Plain Aggregates (No Dynamic Columns)

Leave `pivot_relation` empty to aggregate without pivoting - the report is
just the group columns and the total:

```php
'pivot_config' => [
    'group_by' => [['path' => 'created_at', 'format' => 'week', 'header' => 'Week']],
    'sub_group_by' => ['customer.name'],
    'sub_group_by_headers' => ['Customer'],
    'value_relation' => 'items',
    'value_column' => 'amount',
    'aggregation' => 'sum',
    'output_format' => 'grouped',
    'total_header' => 'Revenue',
],
```

```
Week     | Customer   | Revenue
2026-W01 |            |
         | Acme Ltd.  | 1500.00
         | Globex Co. | 800.00
```

## Limiting Pivot Columns Per Request

`pivot_filter_param` names a request parameter (array or comma-separated ids
of the pivot relation's rows) that limits which pivot columns appear. Hidden
columns are excluded from the row totals:

```php
'pivot_config' => [
    // ...
    'pivot_filter_param' => 'category_ids',
],

$service->executeExport($layout, ['category_ids' => '1,3']);
```

## Filters and Formatting

Layout filters (see [Filtering Data](filtering-data.md)) constrain the base
query before aggregation, including request-based filters. Two common ones:

- a `not_null` filter on the group column, so null values do not become a
  blank group
- a `between` request filter on the date column to bound the report period

To format cell values beyond the default two-decimal rendering, attach a
transformation function through a placeholder column:

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Amount',
    'value_path' => 'amount',
    'is_expanded' => true,
    'expansion_data' => ['type' => 'pivot', 'format_function' => $currencyFunction->id],
    'position' => 1,
]);
```

## Validating Pivot Layouts

`php artisan export:validate` checks pivot configs along with everything else:
unresolvable paths (`unknown_pivot_path`), bad aggregations and output formats,
unknown date buckets (`unknown_group_format`), entries without a path
(`missing_group_path`), and unsupported week starts (`unknown_week_start`).

## Notes

- The pivot query runs through `toBase()`, so Eloquent datetime casts cannot
  interfere with bucketed group keys.
- Dynamic columns come from the data unless `pivot_filter_param` narrows them;
  an unexpected value in the pivot relation becomes an unexpected column.
- Use end-of-day timestamps (`2026-12-31 23:59:59`) in date-range request
  filters so the last day is included.

## Related Documentation

- [Pivot Tables](pivot-tables.md) - BelongsToMany `.pivot.` attribute access
- [Filtering Data](filtering-data.md) - Layout and request filters
- [API Reference](../reference/api.md) - `pivot_config` field reference
