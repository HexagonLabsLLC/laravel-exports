# Multi-Format Export

Export data in different formats (CSV, JSON, and XLSX) from the same layout.

## Scenario

Provide an API endpoint that exports orders in the user's preferred format.

## Setup

### 1. Create Layout

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFunction};

$orderModel = ExportModel::where('title', 'Order')->first();
$formatCurrency = ExportFunction::where('name', 'Format Currency')->first();
$formatDate = ExportFunction::where('name', 'Format Date')->first();

$layout = ExportLayout::create([
    'export_model_id' => $orderModel->id,
    'name' => 'order_export',
    'title' => 'Order Export',
]);

// Columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Order Number',
    'value_path' => 'order_number',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Customer',
    'value_path' => 'customer.name',
    'position' => 2,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total',
    'value_path' => 'total',
    'export_function_id' => $formatCurrency->id,
    'export_function_values' => [null, 'USD', 'en_US'],
    'position' => 3,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Date',
    'value_path' => 'created_at',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'Y-m-d'],
    'position' => 4,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Status',
    'value_path' => 'status',
    'position' => 5,
]);
```

### 2. Controller

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

class OrderExportController extends Controller
{
    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => 'required|in:csv,json,xlsx',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'status' => 'nullable|array',
        ]);

        $layout = ExportLayout::where('title', 'Order Export')->firstOrFail();
        $service = new DynamicExportService();

        $format = $validated['format'];
        $filename = 'orders-' . now()->format('Y-m-d') . '.' . $format;

        return $service->downloadAs(
            $layout,
            $format,
            $filename,
            $request->except('format')
        );
    }
}
```

## Format Options

### CSV Options

```php
return $service->downloadAs($layout, 'csv', 'orders.csv', $requestData, [
    'delimiter' => ',',      // Field separator (default: ,)
    'enclosure' => '"',      // Field enclosure (default: ")
    'escape' => '\\',        // Escape character (default: \)
    'include_headers' => true, // Include header row (default: true)
    'bom' => false,          // Prepend UTF-8 BOM for Excel (default: false)
    'escape_formulas' => true, // Prefix =, +, -, @, tab, CR cells with ' (default: true)
]);
```

By default, cell values starting with `=`, `+`, `-`, `@`, a tab, or a carriage return are prefixed with a single quote to guard against spreadsheet formula injection. Set `escape_formulas => false` to disable.

### JSON Options

```php
return $service->downloadAs($layout, 'json', 'orders.json', $requestData, [
    'pretty' => true,             // Pretty print (default: false)
    'unescaped_slashes' => true,  // Do not escape slashes (default: true)
    'unescaped_unicode' => true,  // Keep unicode characters (default: true)
    'wrap_data' => true,          // Wrap rows in a "data" key (default: true)
    'include_meta' => true,       // Include metadata (default: true)
]);
```

By default, rows are wrapped in a `data` key alongside metadata. With `include_meta` enabled, rows are always wrapped in a `data` key. Set both `wrap_data` and `include_meta` to false for a bare array of rows.

### XLSX Options

The `xlsx` format needs the optional `phpoffice/phpspreadsheet` package
(`composer require phpoffice/phpspreadsheet`); the handler throws with install
instructions when it is missing.

```php
return $service->downloadAs($layout, 'xlsx', 'orders.xlsx', $requestData, [
    'include_headers' => true,  // Header row per sheet (default: true)
    'sheet_title' => 'Orders',  // Single-sheet title (default: layout title, then name)
    'sheet_by' => 'Status',     // One sheet per distinct value of this column title
]);
```

### Custom Formats

Register your own handler to add a format:

```php
use HexagonLabsLLC\LaravelExports\Exports\ExportFactory;

ExportFactory::register('pdf', PdfExportHandler::class); // must extend ExportHandler
```

## Sample Outputs

The JSON samples below use `wrap_data => false` and `include_meta => false`.

### CSV Output

```csv
Order Number,Customer,Total,Date,Status
ORD-001,John Doe,$150.00,2024-01-15,completed
ORD-002,Jane Smith,$275.50,2024-02-20,pending
ORD-003,Bob Wilson,$320.00,2024-03-10,completed
```

### JSON Output (Pretty)

```json
[
    {
        "Order Number": "ORD-001",
        "Customer": "John Doe",
        "Total": "$150.00",
        "Date": "2024-01-15",
        "Status": "completed"
    },
    {
        "Order Number": "ORD-002",
        "Customer": "Jane Smith",
        "Total": "$275.50",
        "Date": "2024-02-20",
        "Status": "pending"
    }
]
```

### JSON Output (Compact)

```json
[{"Order Number":"ORD-001","Customer":"John Doe","Total":"$150.00","Date":"2024-01-15","Status":"completed"},{"Order Number":"ORD-002","Customer":"Jane Smith","Total":"$275.50","Date":"2024-02-20","Status":"pending"}]
```

## API Routes

```php
// routes/api.php
Route::post('/exports/orders', [OrderExportController::class, 'export']);
```

## Request Examples

### CSV Export

```bash
curl -X POST "https://api.example.com/exports/orders" \
  -H "Content-Type: application/json" \
  -d '{"format": "csv", "status": ["completed"]}' \
  -o orders.csv
```

### JSON Export

```bash
curl -X POST "https://api.example.com/exports/orders" \
  -H "Content-Type: application/json" \
  -d '{"format": "json", "start_date": "2024-01-01", "end_date": "2024-03-31"}' \
  -o orders.json
```

## Get Data Without Download

Return data for processing instead of downloading:

```php
public function getData(Request $request)
{
    $layout = ExportLayout::where('title', 'Order Export')->firstOrFail();
    $service = new DynamicExportService();

    // Get as collection
    $data = $service->executeExport($layout, $request->all());

    return response()->json([
        'count' => $data->count(),
        'data' => $data,
    ]);
}
```

## Export to String

Get formatted content as string:

```php
// CSV string
$csvContent = $service->exportTo($layout, 'csv', $requestData);

// JSON string
$jsonContent = $service->exportTo($layout, 'json', $requestData, ['pretty' => true]);
```

## Content Negotiation

Auto-detect format from Accept header:

```php
public function export(Request $request)
{
    $layout = ExportLayout::where('title', 'Order Export')->firstOrFail();
    $service = new DynamicExportService();

    // Determine format from Accept header
    $format = 'csv';  // Default
    if ($request->wantsJson() || $request->header('Accept') === 'application/json') {
        $format = 'json';
    }

    $filename = 'orders-' . now()->format('Y-m-d') . '.' . $format;

    return $service->downloadAs($layout, $format, $filename, $request->all());
}
```

## Custom MIME Types

The handlers set appropriate content types:

- **CSV**: `text/csv`
- **JSON**: `application/json`
- **XLSX**: `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`

## Streaming Large Exports

For large datasets, use streaming:

```php
public function exportLarge(Request $request)
{
    $format = $request->input('format', 'csv');
    $layout = ExportLayout::where('title', 'Order Export')->firstOrFail();
    $service = new DynamicExportService();

    return $service->streamAs(
        $layout,
        $format,
        "large-orders.{$format}",
        $request->except('format'),
        [],     // Format options
        1000    // Chunk size
    );
}
```

## Notes

- Same layout works for all formats
- Column titles become CSV headers and JSON keys
- Transformation functions apply to all formats
- Use streaming for large exports
- JSON format preserves data types (when not using functions)
