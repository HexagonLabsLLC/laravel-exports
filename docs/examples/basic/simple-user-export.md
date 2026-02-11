# Simple User Export

A basic export of user data to CSV.

## Scenario

Export all users with their basic information: name, email, and registration date.

## Sample Data

```php
// users table
[
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => '2024-01-15'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => '2024-02-20'],
    ['id' => 3, 'name' => 'Bob Wilson', 'email' => 'bob@example.com', 'created_at' => '2024-03-10'],
]
```

## Setup

### 1. Import Models

```bash
php artisan export:import-models
```

### 2. Create the Export

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

// Get the User model
$userModel = ExportModel::where('title', 'User')->first();

// Create layout
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'title' => 'User List',
    'description' => 'Basic user information export',
]);

// Create columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 2,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Registered',
    'value_path' => 'created_at',
    'position' => 3,
]);
```

### 3. Execute the Export

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();

// Get data as collection
$data = $service->executeExport($layout);

// Or download as CSV
return $service->downloadAs($layout, 'csv', 'users.csv');
```

## Expected Output

**CSV:**
```csv
Name,Email,Registered
John Doe,john@example.com,2024-01-15 00:00:00
Jane Smith,jane@example.com,2024-02-20 00:00:00
Bob Wilson,bob@example.com,2024-03-10 00:00:00
```

**JSON:**
```json
[
    {"name": "John Doe", "email": "john@example.com", "registered": "2024-01-15 00:00:00"},
    {"name": "Jane Smith", "email": "jane@example.com", "registered": "2024-02-20 00:00:00"},
    {"name": "Bob Wilson", "email": "bob@example.com", "registered": "2024-03-10 00:00:00"}
]
```

## Controller Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

class UserExportController extends Controller
{
    public function download()
    {
        $layout = ExportLayout::where('title', 'User List')->firstOrFail();
        $service = new DynamicExportService();

        return $service->downloadAs($layout, 'csv', 'users.csv');
    }
}
```

## Variations

### Export as JSON

```php
return $service->downloadAs($layout, 'json', 'users.json');
```

### Get Data for Processing

```php
$data = $service->executeExport($layout);

foreach ($data as $row) {
    // Process each row
    echo $row['name'] . "\n";
}
```

### Count Before Export

```php
$count = $service->getExportCount($layout);
echo "Will export {$count} users";
```

## Notes

- Column order is determined by `position` field
- Date fields output in their raw format
- Use transformation functions for date formatting (see [Formatted Export](formatted-export.md))
