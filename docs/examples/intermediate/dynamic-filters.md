# Dynamic Filters

Create exports with user-controllable filter parameters.

## Scenario

Export orders where users can filter by date range, status, and minimum total.

## Sample Data

```php
// orders table
[
    ['id' => 1, 'order_number' => 'ORD-001', 'status' => 'completed', 'total' => 150.00, 'created_at' => '2024-01-15'],
    ['id' => 2, 'order_number' => 'ORD-002', 'status' => 'pending', 'total' => 75.50, 'created_at' => '2024-02-20'],
    ['id' => 3, 'order_number' => 'ORD-003', 'status' => 'completed', 'total' => 320.00, 'created_at' => '2024-03-10'],
    ['id' => 4, 'order_number' => 'ORD-004', 'status' => 'cancelled', 'total' => 50.00, 'created_at' => '2024-03-15'],
]
```

## Setup

### 1. Create Layout and Columns

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFilter, ExportModelRelation};

$orderModel = ExportModel::where('title', 'Order')->first();

$layout = ExportLayout::create([
    'export_model_id' => $orderModel->id,
    'title' => 'Filtered Orders Report',
]);

// Columns
ExportColumn::create(['export_layout_id' => $layout->id, 'title' => 'Order #', 'value_path' => 'order_number', 'position' => 1]);
ExportColumn::create(['export_layout_id' => $layout->id, 'title' => 'Status', 'value_path' => 'status', 'position' => 2]);
ExportColumn::create(['export_layout_id' => $layout->id, 'title' => 'Total', 'value_path' => 'total', 'position' => 3]);
ExportColumn::create(['export_layout_id' => $layout->id, 'title' => 'Date', 'value_path' => 'created_at', 'position' => 4]);
```

### 2. Create Request-Based Filters

```php
// Get relations
$statusRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->where('relation', 'status')->first();
$totalRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->where('relation', 'total')->first();
$createdAtRelation = ExportModelRelation::where('export_model_id', $orderModel->id)
    ->where('relation', 'created_at')->first();

// Date range filter (required)
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => true,      // Must provide dates
    'value_type' => 'date',
    'logical_operator' => 'AND',
]);

// Status filter (optional, multiple values)
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => 'in',
    'is_request' => true,
    'is_required' => false,     // Optional
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);

// Minimum total filter (optional)
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $totalRelation->id,
    'operator' => '>=',
    'is_request' => true,
    'is_required' => false,
    'value_type' => 'number',
    'logical_operator' => 'AND',
]);
```

### 3. Controller

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
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|array',
            'status.*' => 'in:pending,completed,cancelled',
            'min_total' => 'nullable|numeric|min:0',
        ]);

        $layout = ExportLayout::where('title', 'Filtered Orders Report')->first();
        $service = new DynamicExportService();

        // Build request data
        $requestData = [
            'created_at' => [$validated['start_date'], $validated['end_date']],
        ];

        if (!empty($validated['status'])) {
            $requestData['status'] = $validated['status'];
        }

        if (!empty($validated['min_total'])) {
            $requestData['total'] = $validated['min_total'];
        }

        return $service->downloadAs(
            $layout,
            'csv',
            'orders-' . now()->format('Y-m-d') . '.csv',
            $requestData
        );
    }
}
```

## Usage Examples

### All Orders in Date Range

```php
$requestData = [
    'created_at' => ['2024-01-01', '2024-03-31'],
];
```

**Result:** All 4 orders

### Only Completed Orders

```php
$requestData = [
    'created_at' => ['2024-01-01', '2024-03-31'],
    'status' => ['completed'],
];
```

**Result:** Orders 1 and 3

### Completed and Pending, Over $100

```php
$requestData = [
    'created_at' => ['2024-01-01', '2024-03-31'],
    'status' => ['completed', 'pending'],
    'total' => 100,
];
```

**Result:** Orders 1 and 3 (pending order is under $100)

## API Endpoint

```php
// routes/api.php
Route::post('/exports/orders', [OrderExportController::class, 'export']);
```

**Request:**

```json
POST /api/exports/orders
{
    "start_date": "2024-01-01",
    "end_date": "2024-03-31",
    "status": ["completed", "pending"],
    "min_total": 100
}
```

## Parameter Name Matching

The system matches parameters flexibly:

```php
// Filter for 'created_at' matches any of:
'created_at'     // Exact match
'createdat'      // Lowercase no underscore
'CREATED_AT'     // Uppercase

// For nested filters like 'customer.name':
'customer.name'
'customer_name'
'customerName'
```

## Combining Static and Dynamic Filters

```php
// Static: Always exclude cancelled
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '!=',
    'value' => 'cancelled',
    'value_type' => 'string',
    'is_request' => false,      // Static
    'logical_operator' => 'AND',
]);

// Dynamic: User chooses specific status from remaining
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => 'in',
    'is_request' => true,       // Dynamic
    'is_required' => false,
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);
```

## Frontend Form Example

```html
<form action="/api/exports/orders" method="POST">
    <label>Date Range</label>
    <input type="date" name="start_date" required>
    <input type="date" name="end_date" required>

    <label>Status</label>
    <select name="status[]" multiple>
        <option value="pending">Pending</option>
        <option value="completed">Completed</option>
        <option value="cancelled">Cancelled</option>
    </select>

    <label>Minimum Total</label>
    <input type="number" name="min_total" min="0" step="0.01">

    <button type="submit">Export</button>
</form>
```

## Notes

- Required filters cause the export to fail if not provided
- Optional filters are skipped when not provided
- Arrays can be passed as actual arrays or comma-separated strings
- See [Filtering Data Guide](../../guides/filtering-data.md) for all operators
