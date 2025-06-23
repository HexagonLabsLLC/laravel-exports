# Laravel Exports - API Reference

## Services

### DynamicExportService

The main service for executing exports.

#### Methods

##### executeExport()
Execute an export and return the processed data.

```php
public function executeExport(ExportLayout|string $layout, array $requestData = []): Collection
```

**Parameters:**
- `$layout` - ExportLayout instance or UUID
- `$requestData` - Array of request parameters for dynamic filters

**Returns:** Collection of processed export data

**Example:**
```php
$service = new DynamicExportService();
$data = $service->executeExport($layout, ['status' => 'active']);
```

---

##### executeExportChunked()
Process large datasets in chunks to avoid memory issues.

```php
public function executeExportChunked(
    ExportLayout|string $layout, 
    array $requestData = [], 
    int $chunkSize = 1000, 
    ?callable $callback = null
): void
```

**Parameters:**
- `$layout` - ExportLayout instance or UUID
- `$requestData` - Array of request parameters
- `$chunkSize` - Number of records per chunk
- `$callback` - Function to process each chunk

**Example:**
```php
$service->executeExportChunked($layout, [], 500, function($chunk) {
    // Process chunk
});
```

---

##### executeExportPaginated()
Get paginated export results.

```php
public function executeExportPaginated(
    ExportLayout|string $layout, 
    array $requestData = [], 
    int $perPage = 100, 
    int $page = 1
): array
```

**Returns:** Array with 'data' and 'meta' keys

---

##### exportTo()
Export data to a specific format.

```php
public function exportTo(
    ExportLayout|string $layout, 
    string $format, 
    array $requestData = [], 
    array $options = []
): mixed
```

**Parameters:**
- `$format` - Export format ('csv', 'json')
- `$options` - Format-specific options

---

##### downloadAs()
Generate a download response.

```php
public function downloadAs(
    ExportLayout|string $layout, 
    string $format, 
    string $filename, 
    array $requestData = [], 
    array $options = []
): \Illuminate\Http\Response
```

---

##### streamAs()
Stream large exports.

```php
public function streamAs(
    ExportLayout|string $layout, 
    string $format, 
    string $filename, 
    array $requestData = [], 
    array $options = [], 
    int $chunkSize = 1000
): \Illuminate\Http\Response
```

---

##### getExportCount()
Get total count of records that would be exported.

```php
public function getExportCount(ExportLayout|string $layout, array $requestData = []): int
```

---

### TransformationFunctions

Static methods for data transformation.

#### Available Functions

##### Date/Time Functions

```php
TransformationFunctions::formatDate($date, $format = 'Y-m-d H:i:s')
TransformationFunctions::formatDateHuman($date)
TransformationFunctions::dateDifference($date1, $date2 = null, $unit = 'days')
```

##### String Functions

```php
TransformationFunctions::uppercase($string)
TransformationFunctions::lowercase($string)
TransformationFunctions::titleCase($string)
TransformationFunctions::truncate($string, $length = 50, $suffix = '...')
TransformationFunctions::slug($string, $separator = '-')
TransformationFunctions::replace($string, $search, $replace)
TransformationFunctions::extract($string, $pattern)
```

##### Number Functions

```php
TransformationFunctions::formatNumber($number, $decimals = 2, $thousandsSeparator = ',')
TransformationFunctions::formatCurrency($number, $currency = 'USD', $locale = 'en_US')
TransformationFunctions::round($number, $decimals = 0)
TransformationFunctions::percentage($number, $decimals = 2)
```

##### Boolean Functions

```php
TransformationFunctions::booleanText($value, $trueText = 'Yes', $falseText = 'No')
```

##### Array/JSON Functions

```php
TransformationFunctions::jsonExtract($json, $path)
TransformationFunctions::arrayJoin($array, $separator = ', ')
TransformationFunctions::arrayCount($array)
```

##### Utility Functions

```php
TransformationFunctions::defaultValue($value, $default = '')
TransformationFunctions::concatenate($value1, $value2, $separator = ' ')
TransformationFunctions::hash($value, $algorithm = 'sha256')
TransformationFunctions::mask($string, $visibleChars = 4, $maskChar = '*')
```

---

### ExportInspector

Service for validating and syncing model configurations.

#### Methods

##### validateExportConfiguration()
Validate an export layout configuration.

```php
public function validateExportConfiguration(ExportLayout $layout): array
```

**Returns:** Array of validation errors (empty if valid)

---

##### syncModelRelations()
Sync model columns and relationships.

```php
public function syncModelRelations(ExportModel $model): array
```

**Returns:** Array with 'columns' and 'relations' counts

---

### ModelRelationInspector

Helper for discovering model attributes and relationships.

#### Methods

##### getModelColumns()
Get all columns from a model's table.

```php
public static function getModelColumns(string $modelClass): array
```

---

##### getModelRelations()
Discover all relationships on a model.

```php
public static function getModelRelations(string $modelClass): array
```

## Models

### ExportModel

Represents an exportable Eloquent model. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `title` (string) - Display name
- `model` (string) - Full class name
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Attributes
- `instance` - Returns the actual Eloquent model instance

#### Methods
- `model()` - Get the Eloquent model instance (deprecated, use `instance` attribute)

#### Relationships
- `layouts()` - HasMany ExportLayout
- `relations()` - HasMany ExportModelRelation
- `filters()` - HasMany ExportFilter
- `sorts()` - HasMany ExportSort
- `columns()` - HasMany ExportColumn

#### Traits
- `HasUuids` - Laravel's UUID trait for automatic UUID generation

#### Example
```php
$exportModel = ExportModel::find($id);
$userModel = $exportModel->instance; // Returns App\Models\User instance
```

---

### ExportLayout

Defines an export configuration. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `export_model_id` (uuid) - Parent model
- `name` (string) - Layout name
- `description` (string, nullable) - Layout description
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Relationships
- `exportModel()` - BelongsTo ExportModel
- `columns()` - HasMany ExportColumn
- `filters()` - HasMany ExportFilter
- `sorts()` - HasMany ExportSort

#### Traits
- `HasUuids` - Laravel's UUID trait

---

### ExportColumn

Defines a column in the export. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `export_layout_id` (uuid) - Parent layout
- `export_model_relation_id` (uuid, nullable) - Related model relation
- `export_function_id` (uuid, nullable) - Transformation function
- `export_filter_id` (uuid, nullable) - Column-specific filter
- `title` (string, nullable) - Column header
- `value_path` (string, nullable) - Dot notation path
- `default` (string, nullable) - Default value
- `omit_on_empty` (boolean) - Skip if empty
- `aggregator` (enum, nullable) - sum, count, avg, min, max
- `position` (integer) - Column order
- `export_function_values` (json, nullable) - Function parameters
- `export_filter_values` (json, nullable) - Filter overrides
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Relationships
- `layout()` - BelongsTo ExportLayout
- `modelRelation()` - BelongsTo ExportModelRelation
- `function()` - BelongsTo ExportFunction
- `filter()` - BelongsTo ExportFilter

#### Traits
- `HasUuids` - Laravel's UUID trait

---

### ExportFilter

Defines filtering criteria. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `export_layout_id` (uuid) - Parent layout
- `export_model_id` (uuid, nullable) - Model to filter
- `export_model_relation_id` (uuid, nullable) - Relation to filter
- `operator` (enum) - Filter operator
- `value` (json, nullable) - Filter value
- `value_type` (enum) - string, number, boolean, array, date
- `logical_operator` (enum) - AND, OR
- `is_request` (boolean) - Get value from request
- `is_required` (boolean) - Required if from request
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Operators
- `=` - Equals
- `!=` - Not equals
- `>` - Greater than
- `<` - Less than
- `>=` - Greater than or equal
- `<=` - Less than or equal
- `in` - In array
- `not_in` - Not in array
- `between` - Between two values
- `like` - SQL LIKE
- `null` - Is null
- `not_null` - Is not null
- `json_contains` - JSON contains
- `relation` - Relation exists with conditions

#### Traits
- `HasUuids` - Laravel's UUID trait

---

### ExportSort

Defines sorting order. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `export_layout_id` (uuid) - Parent layout
- `export_model_id` (uuid, nullable) - Model to sort
- `export_model_relation_id` (uuid, nullable) - Relation to sort by
- `direction` (enum) - asc, desc
- `priority` (integer) - Sort order
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Traits
- `HasUuids` - Laravel's UUID trait

---

### ExportFunction

Defines transformation functions. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `name` (string) - Display name
- `callable` (string) - PHP callable
- `parameter_count` (integer) - Number of parameters
- `value_parameter_index` (integer, nullable) - Which parameter receives the value
- `metadata` (json, nullable) - Function documentation
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Traits
- `HasUuids` - Laravel's UUID trait

---

### ExportModelRelation

Represents model columns and relationships. Uses UUIDs as primary keys.

#### Properties
- `id` (uuid) - Primary key
- `export_model_id` (uuid) - Parent model
- `title` (string) - Display name
- `relation` (string) - Column/relation name
- `related_model_id` (uuid, nullable) - Related model
- `is_column` (boolean) - Is table column
- `is_collection` (boolean) - Is collection relation
- `created_at` (timestamp) - Creation timestamp
- `updated_at` (timestamp) - Last update timestamp

#### Relationships
- `model()` - BelongsTo ExportModel
- `relatedModel()` - BelongsTo ExportModel

#### Scopes
- `whereNested(string $nestedPath)` - Filter by nested relationship path using dot notation

#### Traits
- `HasUuids` - Laravel's UUID trait

#### Example
```php
// Find a nested relationship
$workOrderRelation = ExportModelRelation::where('export_model_id', $laborPayModel->id)
    ->whereNested('workItem.workOrder')
    ->first();

// The whereNested scope traverses the relationship chain:
// 1. Finds relations where relation = 'workItem'
// 2. Then ensures the related model has a relation = 'workOrder'
```

## Console Commands

### export:import-models

Import and register Eloquent models.

```bash
php artisan export:import-models [options]
```

**Options:**
- `--path[=PATH]` - Directory to scan (default: "app/Models")
- `--namespace[=NAMESPACE]` - Base namespace (default: "App\Models")
- `--filter[=FILTER]` - File pattern filter (default: "*")
- `--omit[=OMIT]` - Comma-separated list of models to omit from relation inspection
- `--force` - Force re-import existing models
- `--skip-relations` - Skip syncing model columns and relationships (synced by default)
- `--deep` - Discover nested relationships with dot notation (e.g., user.posts.comments)
- `--deep-level[=DEEP-LEVEL]` - Maximum depth for nested relationship discovery (default: 2)

---

### export:seed-functions

Seed built-in transformation functions.

```bash
php artisan export:seed-functions [options]
```

**Options:**
- `--force` - Force update existing functions

## Export Handlers

### CsvExportHandler

Handles CSV format exports.

#### Options
- `delimiter` (string) - Field delimiter (default: ',')
- `enclosure` (string) - Field enclosure (default: '"')
- `escape` (string) - Escape character (default: '\\')
- `headers` (boolean) - Include headers (default: true)

---

### JsonExportHandler

Handles JSON format exports.

#### Options
- `pretty` (boolean) - Pretty print JSON (default: false)
- `options` (integer) - JSON encoding options
- `depth` (integer) - Maximum depth (default: 512)

## Enums

### OperatorType

Available filter operators.

```php
enum OperatorType: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_EQUAL = '>=';
    case LESS_EQUAL = '<=';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case BETWEEN = 'between';
    case LIKE = 'like';
    case NULL = 'null';
    case NOT_NULL = 'not_null';
    case JSON_CONTAINS = 'json_contains';
    case RELATION = 'relation';
}
```

## Configuration

### config/laravel-exports.php

```php
return [
    // Model class mappings
    'models' => [
        'export_model' => \HexagonLabsLLC\LaravelExports\Models\ExportModel::class,
        'export_layout' => \HexagonLabsLLC\LaravelExports\Models\ExportLayout::class,
        'export_column' => \HexagonLabsLLC\LaravelExports\Models\ExportColumn::class,
        'export_filter' => \HexagonLabsLLC\LaravelExports\Models\ExportFilter::class,
        'export_sort' => \HexagonLabsLLC\LaravelExports\Models\ExportSort::class,
        'export_function' => \HexagonLabsLLC\LaravelExports\Models\ExportFunction::class,
        'export_model_relation' => \HexagonLabsLLC\LaravelExports\Models\ExportModelRelation::class,
    ],
    
    // Export handler mappings
    'handlers' => [
        'csv' => \HexagonLabsLLC\LaravelExports\Exports\Handlers\CsvExportHandler::class,
        'json' => \HexagonLabsLLC\LaravelExports\Exports\Handlers\JsonExportHandler::class,
    ],
];
```