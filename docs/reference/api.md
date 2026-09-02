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
- `export_model_id` (uuid, nullable) - either this or `model` must be set
- `model` (string, nullable) - an Eloquent FQCN; wins over `export_model_id` and lazy-syncs the catalog on first reference
- `name` (string)
- `title` (string, nullable)
- `description` (string, nullable)
- `is_pivot` (boolean)
- `pivot_config` (json, nullable)
- `column_definitions` (json, nullable) - column definitions carried by the layout row itself; see below
- `filter_definitions` (json, nullable) - filter definitions carried by the layout row; see below
- `sort_definitions` (json, nullable) - sort definitions carried by the layout row; see below

**Lazy catalog sync:**

The export catalog (`export_models` / `export_model_relations`) syncs itself as models are referenced, controlled by `config('laravel-exports.schema_sync')`:
- `lazy` (default) - a referenced model or relation path missing from the catalog is reflected and upserted on first use; existing rows are trusted with zero writes
- `verify` - additionally re-syncs a model when its reflected schema fingerprint (`export_models.schema_hash`) has drifted
- `manual` - never writes at runtime; missing catalog entries throw with a hint to run `export:import-models`

`app(SchemaSync::class)->describe(App\Models\Post::class)` returns the synced columns and relations for a model - the schema endpoint for UI picklists.

**Filter and sort definitions:**

Like `column_definitions`, a layout row can carry its filters and sorts, so one INSERT is a complete runnable export:

```json
{
  "filter_definitions": [
    {"path": "published", "operator": "=", "value": true},
    {"path": "user.name", "operator": "=", "value": "John Doe"},
    {"path": "tags", "operator": "=", "value": "120", "column": "value"},
    {"path": "status", "operator": "in", "is_request": true, "is_required": true}
  ],
  "sort_definitions": [
    {"path": "user", "sort_column": "name"},
    {"path": "created_at", "direction": "desc", "priority": 2}
  ]
}
```

Filter entries take `path`, `operator`, `value`, and optionally `value_type`, `logical_operator`, `is_request`, `is_required`, and `column` (the target column for whereHas relation filters). Paths resolve against the catalog with lazy sync: a base column filters directly, a relation name becomes a whereHas, a dotted attribute path (like `user.name`) rides the smart relation filter parsing. Request filters match request keys derived from the path. Sort entries take `path`, `direction`, optional `priority` (definitions without one slot in after persisted sorts), and optional `sort_column` for relation sorts. Defined entries have no UUIDs, so request `defaults`/`overrides` cannot target them, and an `or` group should live within one storage mechanism (definitions concat after persisted rows).

**Relationships:**
- `exportModel()` - BelongsTo ExportModel
- `columns()` - HasMany ExportColumn
- `filters()` - HasMany ExportFilter
- `sorts()` - HasMany ExportSort

**Database-driven columns via `column_definitions`:**

A layout row can carry its own columns in the `column_definitions` JSON field, so a layout inserted purely through the database (admin UI, raw SQL, another service) needs no `export_columns` rows and no PHP seeding step. At export time the definitions are built into in-memory columns and merged with any persisted `export_columns` by position (definitions without a position slot in after them). Entries use the same shapes as `addColumns()`, including `relation` resolution:

```json
{
    "Title": "title",
    "Tag Total": {"value_path": "tags.value", "relation": "tags", "aggregator": "sum"},
    "Source": {"value_path": "source_system", "default": "CRM", "position": 1}
}
```

Because these columns have no database ids, request `defaults`/`overrides` (which are keyed by column UUID) cannot target them; use the definition's own `default` instead.

**Methods:**

`addColumns(array $columns): static` - bulk-create columns from one array. Entries may be `'Title' => 'value.path'` shorthand, `'Title' => [attributes]`, or list-style attribute arrays. Positions auto-increment past the current max when omitted, and a `relation` key is resolved to `export_model_relation_id` against the layout's export model (throws `InvalidArgumentException` for unregistered relations).

```php
$layout->addColumns([
    'ID' => 'id',
    'Name' => ['value_path' => 'name', 'relation' => 'name'],
    'Email' => ['value_path' => 'email', 'default' => 'N/A'],
    'Post Count' => ['value_path' => 'posts', 'relation' => 'posts', 'aggregator' => 'count'],
]);
```

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
- `format` (string, nullable) - `{value}` output template; see below
- `omit_on_empty` (boolean)
- `aggregator` (enum, nullable) - sum, count, avg, min, max, first, last
- `position` (integer)
- `export_function_values` (json, nullable)
- `export_filter_values` (json, nullable)
- `is_expanded` (boolean) + `expansion_data` (json, nullable) - dynamic column expansion; see below

**Format templates (`format`):**

On a regular column, `format` wraps each cell's final value: `'value_path' => 'customer.name', 'format' => 'Site {value}'` renders `Site Customer Ltd.`. It applies after aggregation, transformation functions, and default resolution (so a default of `0` renders `0 Items` with `{value} Items`), is skipped when the value is empty (no ` Items` artifacts), and request overrides bypass it entirely.

**Dynamic column expansion (`is_expanded`):**

A column on a collection relation with `is_expanded => true` fans out at export time into one generated column per distinct related value across the filtered dataset (union, sorted alphabetically, so every row stays rectangular):

```php
[
    'relation' => 'laborEntries',
    'is_expanded' => true,
    'format' => 'Site {value}',                      // templates the generated column TITLES
    'expansion_data' => ['header_path' => 'site.name'], // names each column (default 'name')
    'value_path' => 'hours',                         // cell extraction per matching item
    'aggregator' => 'sum',                           // collapses a row's matches into the cell
    'default' => '0',
]
```

Each cell aggregates the row's related items whose `header_path` value matches that column. Without `format`, the raw header value is the title. Works identically inside a layout's `column_definitions` JSON. Limitation: expanded columns need the full dataset to determine the column set, so chunked, streamed, queued, and paginated exports throw a RuntimeException for now.

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

### XlsxExportHandler

Requires the optional `phpoffice/phpspreadsheet` package (`composer require phpoffice/phpspreadsheet`); the handler throws with install instructions when it is missing. String cells are written with an explicit string type, so values like `=SUM(A1)` are stored as text and never execute as formulas. The workbook is built in memory; prefer csv for very large exports. Not supported by queued exports.

**Options:**
- `include_headers` (boolean) - Default: true
- `sheet_title` (string) - Title for the single sheet. Defaults to the layout title, then the layout name.
- `sheet_by` (string) - Split rows into one sheet per distinct value of this column (matched by column title). Each sheet gets its own header row. Works with `exportTo`, `downloadAs`, `storeAs`, and `streamAs`.

**Multiple sheets:**

```php
// One sheet per author, titled by the author's name
$xlsx = $service->exportTo($layout, 'xlsx', [], ['sheet_by' => 'Author']);

// Or pass a string-keyed set of row collections directly to the handler
$handler = ExportFactory::create('xlsx', $layout);
$xlsx = $handler->export(collect([
    'Users' => $userRows,
    'Orders' => $orderRows,
]));
```

Sheet titles are sanitized to Excel's rules automatically: the characters `[ ] : * ? / \` are replaced with spaces, titles are capped at 31 characters, blanks become `Sheet`, and duplicates get a ` (2)` style suffix.

---

## ExportLayoutBuilder

Fluent construction of catalog-backed layouts. `for()` accepts a model FQCN (lazy-syncing its catalog rows) or an ExportModel; `column()` takes the `addColumns()` definition shapes; paths resolve inside `save()`'s transaction, so an invalid path rolls the whole layout back.

```php
use HexagonLabsLLC\LaravelExports\Builders\ExportLayoutBuilder;

$layout = ExportLayoutBuilder::for(App\Models\Post::class)
    ->name('posts_report')
    ->title('Posts Report')
    ->column('Title', 'title')
    ->column('Author', 'user.name')
    ->column('Tag Total', ['relation' => 'tags', 'value_path' => 'tags.value', 'aggregator' => 'sum', 'default' => '0'])
    ->filter('published', '=', true)
    ->requestFilter('user.name', 'in', required: true)
    ->sort('created_at', 'desc')
    ->save();
```

A `filter()` with a `column` option writes that target column onto the catalog relation row (the same field catalog-based whereHas filters read). Array filter values are stored JSON-encoded with `value_type` defaulting to `array`.

`validate(): array` spot-checks the staged layout without saving anything, returning the LayoutValidator's problem list. `save()` validates first and throws `InvalidArgumentException` listing every error at once; warnings never block a save.

---

## LayoutValidator

Read-only spot checker for layout configurations. Works on persisted AND unsaved layouts, never writes, and never lazy-syncs - safe for CI, replicas, and manual sync mode.

```php
use HexagonLabsLLC\LaravelExports\Services\LayoutValidator;

$problems = app(LayoutValidator::class)->validate($layout);

// Pre-save spot check of a raw form payload (e.g. from a UI) - zero DB writes
$problems = app(LayoutValidator::class)->validateDraft([
    'model' => \App\Models\Post::class,
    'name' => 'draft',
    'column_definitions' => ['Title' => 'title'],
]);
```

Each problem: `['severity' => 'error'|'warning', 'code' => 'unknown_operator', 'source' => 'column:Title', 'params' => [...], 'message' => '...']`. An empty array means valid. Errors are things that break or corrupt an export (unknown operators, aggregators, unresolvable paths, orphaned references, invalid pivot config); warnings are suspicious but functional (skipped static filters, formats without `{value}`, collection sorts without `sort_column`, a leading `or` filter).

**Customizing validation messages:**

Messages render through Laravel's translator (`laravel-exports::validation.<code>`), so consuming projects can replace engineer nomenclature with client-friendly wording, and multilingual projects can add locales:

```bash
php artisan vendor:publish --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider" --tag=lang
```

Then edit `lang/vendor/laravel-exports/en/validation.php` (or add `es/validation.php` etc.):

```php
'unknown_aggregator' => 'Pick one of the listed aggregations (you chose :aggregator)',
```

Frontends can also ignore `message` entirely and map each problem's stable `code` + `params` to their own strings.

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
