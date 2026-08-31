# API Reference

Complete reference for all classes and methods.

## DynamicExportService

The main service for executing exports.

```php
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;

$service = new DynamicExportService();
```

### executeExport()

Execute an export and return processed data.

```php
public function executeExport(
    ExportLayout|string $layout,
    array $requestData = []
): Collection
```

**Parameters:**
- `$layout` - ExportLayout instance or UUID string
- `$requestData` - Array of request parameters for dynamic filters

**Returns:** `Illuminate\Support\Collection` of processed export data

**Example:**
```php
$data = $service->executeExport($layout, ['status' => 'active']);
```

### executeExportChunked()

Process large datasets in chunks.

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
$service->executeExportChunked($layout, [], 500, function ($chunk) {
    foreach ($chunk as $row) {
        // Process row
    }
});
```

### executeExportPaginated()

Get paginated export results.

```php
public function executeExportPaginated(
    ExportLayout|string $layout,
    array $requestData = [],
    int $perPage = 100,
    int $page = 1
): array
```

**Returns:** Array with `data` and `meta` keys

```php
[
    'data' => [...],
    'meta' => [
        'current_page' => 1,
        'last_page' => 10,
        'per_page' => 100,
        'total' => 1000,
        'from' => 1,
        'to' => 100,
    ]
]
```

### exportTo()

Export data to a specific format as string.

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

**Example:**
```php
$csv = $service->exportTo($layout, 'csv', [], ['delimiter' => ';']);
```

### downloadAs()

Generate a download response.

```php
public function downloadAs(
    ExportLayout|string $layout,
    string $format,
    string $filename,
    array $requestData = [],
    array $options = []
): Response
```

**Returns:** `Illuminate\Http\Response`

**Example:**
```php
return $service->downloadAs($layout, 'csv', 'users.csv');
```

### streamAs()

Stream large exports.

```php
public function streamAs(
    ExportLayout|string $layout,
    string $format,
    string $filename,
    array $requestData = [],
    array $options = [],
    int $chunkSize = 1000
): Response|StreamedResponse
```

**Returns:** `Illuminate\Http\Response` or `Symfony\Component\HttpFoundation\StreamedResponse`

**Example:**
```php
return $service->streamAs($layout, 'csv', 'large.csv', [], [], 500);
```

### getExportCount()

Get total count of records that would be exported.

```php
public function getExportCount(
    ExportLayout|string $layout,
    array $requestData = []
): int
```

**Example:**
```php
$count = $service->getExportCount($layout, $requestData);
```

### getQuery()

Get the fully built query (filters, sorts, and eager loads applied) without executing it. Useful for debugging.

```php
public function getQuery(
    ExportLayout|string $layout,
    array $requestData = []
): Builder
```

**Example:**
```php
$query = $service->getQuery($layout, $requestData);
dd($query->toSql(), $query->getBindings());
```

### queueExport()

Queue an export for background processing.

```php
public function queueExport(
    ExportLayout|string $layout,
    string $format = 'csv',
    array $requestData = [],
    array $options = []
): string
```

**Returns:** Export ID (UUID)

**Options:** `disk` and `path` override the configured storage disk and path for the generated file.

**Note:** Queued exports support only the `csv` and `json` formats. Other formats throw an `InvalidArgumentException`.

**Example:**
```php
$exportId = $service->queueExport($layout, 'csv', $requestData);
```

### getExportStatus()

Get status of a queued export.

```php
public function getExportStatus(string $exportId): ?array
```

**Returns:** Status array or null if not found

---

## ProcessExportJob

Background job for exports.

### Static Methods

#### getStatus()

```php
public static function getStatus(string $exportId): ?array
```

**Returns:**
```php
[
    'status' => 'processing',  // processing, completed, failed
    'progress' => 45,
    'row_count' => 4500,
    'error' => null,
    'path' => null,
    'url' => null,
]
```

#### isComplete()

```php
public static function isComplete(string $exportId): bool
```

#### isSuccessful()

```php
public static function isSuccessful(string $exportId): bool
```

#### getDownloadUrl()

```php
public static function getDownloadUrl(string $exportId): ?string
```

#### getFilePath()

```php
public static function getFilePath(string $exportId): ?string
```

---

## TransformationFunctions

Static methods for data transformation.

### Date/Time Functions

```php
TransformationFunctions::formatDate($date, $format = 'Y-m-d H:i:s')
TransformationFunctions::formatDateHuman($date)
TransformationFunctions::formatTimestamp($date, $format = 'Y-m-d H:i:s', $timezone = 'UTC')
TransformationFunctions::dateDifference($date1, $date2 = null, $unit = 'days')
```

### String Functions

```php
TransformationFunctions::uppercase($string)
TransformationFunctions::lowercase($string)
TransformationFunctions::titleCase($string)
TransformationFunctions::truncate($string, $length = 50, $suffix = '...')
TransformationFunctions::slug($string, $separator = '-')
TransformationFunctions::replace($string, $search, $replace)
TransformationFunctions::extract($string, $pattern)
```

### Number Functions

```php
TransformationFunctions::formatNumber($number, $decimals = 2, $thousandsSeparator = ',')
TransformationFunctions::formatCurrency($number, $currency = 'USD', $locale = 'en_US')
TransformationFunctions::round($number, $decimals = 0)
TransformationFunctions::percentage($number, $decimals = 2)
```

### Boolean Functions

```php
TransformationFunctions::booleanText($value, $trueText = 'Yes', $falseText = 'No')
```

### Array/JSON Functions

```php
TransformationFunctions::jsonExtract($json, $path)
TransformationFunctions::arrayJoin($array, $separator = ', ')
TransformationFunctions::arrayCount($array)
```

### Utility Functions

```php
TransformationFunctions::defaultValue($value, $default = '')
TransformationFunctions::concatenate($value1, $value2, $separator = ' ')
TransformationFunctions::hash($value, $algorithm = 'sha256')
TransformationFunctions::mask($string, $visibleChars = 4, $maskChar = '*')
```

---

## Models

### ExportModel

Represents an exportable Eloquent model.

**Properties:**
- `id` (uuid) - Primary key
- `title` (string) - Display name
- `model` (string) - Full class name

**Attributes:**
- `instance` - Returns the actual Eloquent model instance

**Relationships:**
- `layouts()` - HasMany ExportLayout
- `relations()` - HasMany ExportModelRelation

### ExportLayout

Defines an export configuration.

**Properties:**
- `id` (uuid)
- `export_model_id` (uuid)
- `name` (string)
- `title` (string, nullable)
- `description` (string, nullable)
- `is_pivot` (boolean)
- `pivot_config` (json, nullable)

**Relationships:**
- `exportModel()` - BelongsTo ExportModel
- `columns()` - HasMany ExportColumn
- `filters()` - HasMany ExportFilter
- `sorts()` - HasMany ExportSort

### ExportColumn

Defines a column in the export.

**Properties:**
- `id` (uuid)
- `export_layout_id` (uuid)
- `export_model_relation_id` (uuid, nullable)
- `export_function_id` (uuid, nullable)
- `export_filter_id` (uuid, nullable)
- `title` (string, nullable)
- `value_path` (string)
- `default` (string, nullable)
- `omit_on_empty` (boolean)
- `aggregator` (enum, nullable) - sum, count, avg, min, max, first, last
- `position` (integer)
- `export_function_values` (json, nullable)
- `export_filter_values` (json, nullable)

**Relationships:**
- `layout()` - BelongsTo ExportLayout
- `modelRelation()` - BelongsTo ExportModelRelation
- `exportFunction()` - BelongsTo ExportFunction
- `filter()` - BelongsTo ExportFilter

### ExportFilter

Defines filtering criteria.

**Properties:**
- `id` (uuid)
- `export_layout_id` (uuid)
- `export_model_id` (uuid, nullable)
- `export_model_relation_id` (uuid, nullable)
- `operator` (enum) - See [Operators Reference](operators.md)
- `value` (json, nullable)
- `value_type` (enum) - string, number, boolean, array, date
- `logical_operator` (enum) - AND, OR
- `is_request` (boolean)
- `is_required` (boolean)

### ExportSort

Defines sorting order.

**Properties:**
- `id` (uuid)
- `export_layout_id` (uuid)
- `export_model_id` (uuid, nullable)
- `export_model_relation_id` (uuid, nullable)
- `direction` (enum) - asc, desc
- `priority` (integer)

### ExportFunction

Defines transformation functions.

**Properties:**
- `id` (uuid)
- `name` (string)
- `callable` (string)
- `parameter_count` (integer)
- `value_parameter_index` (integer, nullable)
- `metadata` (json, nullable)

### ExportModelRelation

Represents model columns and relationships.

**Properties:**
- `id` (uuid)
- `export_model_id` (uuid)
- `title` (string)
- `relation` (string)
- `column` (string, nullable) - Target column for relationship filters
- `related_model_id` (uuid, nullable)
- `is_column` (boolean)
- `is_collection` (boolean)
- `has_pivot` (boolean)
- `pivot_columns` (json, nullable)
- `metadata` (json, nullable) - Extra configuration, e.g. `{"sort_column": "name"}` for related-column sorting

**Scopes:**
- `whereNested(string $path)` - Validates a dot-notation path by traversing the relationship chain, then matches the FIRST segment's relation row. The query returns the record for the first segment (e.g. `workItem`), not a record holding the full dot path.

```php
// Returns the 'workItem' relation row if the full chain is valid
ExportModelRelation::whereNested('workItem.workOrder.customer')->first();
```

---

## Export Handlers

### CsvExportHandler

**Options:**
- `delimiter` (string) - Default: ','
- `enclosure` (string) - Default: '"'
- `escape` (string) - Default: '\\'
- `include_headers` (boolean) - Default: true
- `bom` (boolean) - Default: false. Prepend a UTF-8 byte order mark for Excel compatibility.
- `escape_formulas` (boolean) - Default: true. Guards against spreadsheet formula injection by prefixing cell values starting with `=`, `+`, `-`, `@`, tab, or carriage return with a single quote. Set to false to disable.

### JsonExportHandler

**Options:**
- `pretty` (boolean) - Default: false
- `unescaped_slashes` (boolean) - Default: true
- `unescaped_unicode` (boolean) - Default: true
- `wrap_data` (boolean) - Default: true. Wrap rows in a `data` key.
- `include_meta` (boolean) - Default: true. Include metadata; when enabled, rows are always wrapped in a `data` key.

---

## ModelRelationInspector

Discovers model attributes and relationships. Methods are instance methods - resolve the inspector from the container:

```php
$inspector = app(ModelRelationInspector::class);
```

### getModelColumns()

```php
public function getModelColumns(string $modelClass): array
```

### getModelRelations()

```php
public function getModelRelations(string $modelClass): array
```

---

## Enums

### OperatorType

```php
enum OperatorType: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
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
