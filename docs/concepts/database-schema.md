# Database Schema

Laravel Exports uses 7 interconnected database tables to store export configurations. All tables use UUIDs as primary keys for distributed system compatibility.

## Table Overview

| Table | Purpose |
|-------|---------|
| `export_models` | Registered exportable Eloquent models |
| `export_model_relations` | Model columns and relationships |
| `export_layouts` | Named export configurations |
| `export_columns` | Output columns with transformations |
| `export_filters` | Query constraints and filters |
| `export_sorts` | Ordering configuration |
| `export_functions` | Reusable transformation functions |

## Entity Relationship Diagram

```
export_models
    |
    |--< export_model_relations
    |       |
    |       `--< export_columns
    |       `--< export_filters
    |       `--< export_sorts
    |
    `--< export_layouts
            |
            |--< export_columns --> export_functions
            |                   --> export_filters
            |--< export_filters
            `--< export_sorts
```

## Table Details

### export_models

Stores registered Eloquent models that can be exported.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `title` | VARCHAR | Display name (e.g., "User", "Order") |
| `model` | VARCHAR | Full class name (e.g., "App\Models\User") |
| `schema_hash` | VARCHAR (nullable) | Fingerprint of the reflected model schema; the `verify` schema sync mode re-syncs when it drifts |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Last update time |

**Indexes:**
- `model` - For fast lookups by class name

**Example:**

```php
ExportModel::create([
    'title' => 'User',
    'model' => 'App\Models\User',
]);
```

### export_model_relations

Stores columns and relationships for each model.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `export_model_id` | UUID | Foreign key to export_models |
| `title` | VARCHAR | Display name |
| `relation` | VARCHAR | Column name or relationship method (supports dot notation) |
| `column` | VARCHAR (nullable) | Target column for relationship filters |
| `related_model_id` | UUID (nullable) | Foreign key to related export_model |
| `is_column` | BOOLEAN | True if this is a table column |
| `is_collection` | BOOLEAN | True if relationship returns multiple items |
| `has_pivot` | BOOLEAN | True if BelongsToMany with pivot data |
| `pivot_columns` | JSON (nullable) | Array of available pivot column names |
| `metadata` | JSON (nullable) | Extra configuration, e.g. `{"sort_column": "name"}` for related-column sorting |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Record update time |

**Indexes:**
- Composite: `export_model_id`, `relation`, `related_model_id`
- Unique: `export_model_id`, `relation`, `is_column` (makes lazy-sync upserts race safe)

**Example:**

```php
// A direct column
ExportModelRelation::create([
    'export_model_id' => $userModel->id,
    'title' => 'Email',
    'relation' => 'email',
    'is_column' => true,
    'is_collection' => false,
]);

// A relationship
ExportModelRelation::create([
    'export_model_id' => $userModel->id,
    'title' => 'Posts',
    'relation' => 'posts',
    'related_model_id' => $postModel->id,
    'is_column' => false,
    'is_collection' => true,
]);

// A nested relationship
ExportModelRelation::create([
    'export_model_id' => $userModel->id,
    'title' => 'Post Comments',
    'relation' => 'posts.comments',
    'related_model_id' => $commentModel->id,
    'is_column' => false,
    'is_collection' => true,
]);

// BelongsToMany with pivot
ExportModelRelation::create([
    'export_model_id' => $userModel->id,
    'title' => 'Roles',
    'relation' => 'roles',
    'related_model_id' => $roleModel->id,
    'is_column' => false,
    'is_collection' => true,
    'has_pivot' => true,
    'pivot_columns' => ['assigned_at', 'expires_at'],
]);
```

### export_layouts

Stores named export configurations.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `export_model_id` | UUID (nullable) | Foreign key to export_models (entry point) |
| `model` | VARCHAR (nullable) | Eloquent FQCN; alternative to `export_model_id`, lazy-syncs the catalog on first reference and wins when both are set |
| `name` | VARCHAR | Layout name (identifier) |
| `title` | VARCHAR (nullable) | Display title |
| `description` | VARCHAR (nullable) | Layout description |
| `is_pivot` | BOOLEAN | True if the layout produces a pivot (crosstab) export |
| `pivot_config` | JSON (nullable) | Pivot export configuration |
| `column_definitions` | JSON (nullable) | Columns carried by the layout row; merged with `export_columns` by position at export time |
| `filter_definitions` | JSON (nullable) | Filters carried by the layout row (`{path, operator, value, ...}`); applied after the `export_filters` rows |
| `sort_definitions` | JSON (nullable) | Sorts carried by the layout row (`{path, direction?, priority?, sort_column?}`) |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Record update time |

**Indexes:**
- Composite: `export_model_id`, `name`

**Example:**

```php
ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'active_users',
    'title' => 'Active Users Report',
    'description' => 'Export active users with profile data',
]);
```

### export_columns

Defines output columns for a layout.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `export_layout_id` | UUID | Foreign key to export_layouts |
| `export_model_relation_id` | UUID (nullable) | Foreign key to export_model_relations |
| `export_function_id` | UUID (nullable) | Foreign key to export_functions |
| `export_function_values` | JSON (nullable) | Positional function parameters; `null` occupies the value slot (index 0 for built-ins) |
| `export_filter_id` | UUID (nullable) | Column-specific filter |
| `export_filter_values` | JSON (nullable) | Filter parameter overrides |
| `title` | VARCHAR (nullable) | Column header |
| `value_path` | VARCHAR | Dot notation path to value |
| `default` | VARCHAR (nullable) | Default value when null/empty |
| `format` | VARCHAR (nullable) | `{value}` output template; wraps each non-empty cell, or templates the generated titles of an expanded column |
| `aggregator` | ENUM (nullable) | sum, count, avg, min, max, first, last |
| `position` | INTEGER | Column display order |
| `is_expanded` | BOOLEAN | Expand a collection relation into one generated column per distinct related value |
| `expansion_data` | JSON (nullable) | Expansion configuration: `header_path` (default `name`) |
| `omit_on_empty` | BOOLEAN | Marker only; empty values already render as the column `default` (an empty string when unset), so rows stay rectangular either way |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Record update time |

**Indexes:**
- Composite: `export_layout_id`, `export_function_id`, `export_model_relation_id`

**Example:**

```php
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Total Orders',
    'value_path' => 'orders',
    'aggregator' => 'count',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Member Since',
    'value_path' => 'created_at',
    'export_function_id' => $formatDateFunction->id,
    'export_function_values' => [null, 'F j, Y'],
    'position' => 2,
]);
```

### export_filters

Defines filtering criteria for layouts and columns.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `export_layout_id` | UUID | Foreign key to export_layouts |
| `export_model_id` | UUID (nullable) | Model being filtered |
| `export_model_relation_id` | UUID (nullable) | Relation/column being filtered |
| `operator` | ENUM | Filter operator (see below) |
| `value` | TEXT (nullable) | Static filter value; JSON-decoded when `value_type` is `array` or the operator needs a list |
| `value_type` | ENUM | array, string, integer, boolean, float |
| `logical_operator` | ENUM | `and`, `or` (stored lowercase; compared case-insensitively). An `or` groups with the preceding filter |
| `is_request` | BOOLEAN | Get value from request |
| `is_required` | BOOLEAN | Required if from request |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Record update time |

**Operators:**
- `=` - Equals
- `!=` - Not equals
- `>` - Greater than
- `<` - Less than
- `>=` - Greater than or equal
- `<=` - Less than or equal
- `in` - In array
- `not_in` - Not in array
- `between` - Between two values
- `like` - SQL LIKE pattern
- `null` - Is null
- `not_null` - Is not null
- `json_contains` - JSON contains
- `relation` - Relation exists with conditions

**Indexes:**
- Composite: `export_layout_id`, `export_model_id`, `export_model_relation_id`

**Example:**

```php
// Static filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $statusRelation->id,
    'operator' => '=',
    'value' => 'active',
    'value_type' => 'string',
    'logical_operator' => 'AND',
]);

// Request-based filter
ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $dateRelation->id,
    'operator' => 'between',
    'is_request' => true,
    'is_required' => true,
    'value_type' => 'array',
    'logical_operator' => 'AND',
]);
```

### export_sorts

Defines sorting order for a layout.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `export_layout_id` | UUID | Foreign key to export_layouts |
| `export_model_id` | UUID (nullable) | Model being sorted |
| `export_model_relation_id` | UUID (nullable) | Relation/column to sort by |
| `direction` | ENUM | asc, desc |
| `priority` | INTEGER | Sort order (1 = first) |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Record update time |

**Indexes:**
- Composite: `export_layout_id`, `export_model_id`, `export_model_relation_id`, `priority`

**Example:**

```php
// Primary sort
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $createdAtRelation->id,
    'direction' => 'desc',
    'priority' => 1,
]);

// Secondary sort
ExportSort::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $nameRelation->id,
    'direction' => 'asc',
    'priority' => 2,
]);
```

### export_functions

Stores reusable transformation functions.

| Column | Type | Description |
|--------|------|-------------|
| `id` | UUID | Primary key |
| `name` | VARCHAR | Display name |
| `callable` | VARCHAR | PHP callable string |
| `parameter_count` | INTEGER | Number of parameters |
| `value_parameter_index` | INTEGER (nullable) | Index of the value parameter |
| `metadata` | JSON (nullable) | Documentation and examples |
| `created_at` | TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | Record update time |

**Constraints:**
- Unique: `callable`

**Indexes:**
- `name`

**Example:**

```php
ExportFunction::create([
    'name' => 'Format Date',
    'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::formatDate',
    'parameter_count' => 2,
    'value_parameter_index' => 0,
    'metadata' => [
        'description' => 'Format a date using a specified format',
        'parameters' => ['date', 'format'],
        'example' => 'formatDate($date, "Y-m-d")',
    ],
]);
```

## UUID Usage

All tables use Laravel's UUID trait (`HasUuids`) for primary keys. This provides:

1. **Distributed Safety** - UUIDs can be generated anywhere without collision
2. **No Sequence Issues** - Works well with database replication
3. **Security** - IDs are not sequential/guessable
4. **Portability** - Easy to export/import between systems

UUIDs are stored as 36-character strings (with hyphens) using Laravel's default UUID format.

## Relationships Summary

```php
// ExportModel
$exportModel->layouts();        // HasMany ExportLayout
$exportModel->relations();      // HasMany ExportModelRelation

// ExportLayout
$layout->exportModel();         // BelongsTo ExportModel
$layout->columns();             // HasMany ExportColumn
$layout->filters();             // HasMany ExportFilter
$layout->sorts();               // HasMany ExportSort

// ExportColumn
$column->layout();              // BelongsTo ExportLayout
$column->modelRelation();       // BelongsTo ExportModelRelation
$column->exportFunction();      // BelongsTo ExportFunction
$column->filter();              // BelongsTo ExportFilter

// ExportFilter
$filter->layout();              // BelongsTo ExportLayout
$filter->modelRelation();       // BelongsTo ExportModelRelation

// ExportSort
$sort->layout();                // BelongsTo ExportLayout
$sort->modelRelation();         // BelongsTo ExportModelRelation

// ExportModelRelation
$relation->model();             // BelongsTo ExportModel (the owning model)
$relation->relatedModel();      // BelongsTo ExportModel (nullable)
```

## Migration Order

When the migrations run, tables are created in this order to satisfy foreign key constraints:

1. `export_models`
2. `export_model_relations`
3. `export_layouts`
4. `export_functions`
5. `export_filters`
6. `export_columns`
7. `export_sorts`

## Related Documentation

- [Export Lifecycle](export-lifecycle.md) - How these tables work together
- [Filter Architecture](filter-architecture.md) - Deep dive into filters
- [API Reference](../reference/api.md) - Model class documentation
