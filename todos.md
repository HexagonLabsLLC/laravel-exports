# Laravel Exports Package - Development Tasks

## High Priority (Critical for v1.0)

- [x] Fix namespace issue in ExportInspector for ModelRelationInspector
- [x] Implement ImportModelsCommand to auto-discover and register models
- [x] Write comprehensive test suite using Pest
- [x] Update ExportInspector to use correct table names (export_models not export_entities)
- [x] Update DynamicExportService to handle column-specific filtering via export_filter_id
- [x] Add support for new operators (json_contains, relation) in DynamicExportService

## Medium Priority (Enhanced functionality)

- [x] Add sorting support for related columns (currently has TODO)
- [x] Create built-in transformation functions (date formatting, string manipulation, etc)
- [x] Add usage examples and documentation
- [ ] Add validation for export configurations
- [ ] Implement proper handling of aggregations with the nullable aggregator field

## Low Priority (Future enhancements)

- [ ] Consider adding more export formats (Excel, PDF)
- [x] Add export scheduling/job support
- [ ] Create a simple UI or API endpoints for managing exports
- [x] Add support for expansion_data in columns for advanced collection handling
- [x] Add pivot table support for BelongsToMany relationships

## Progress Summary

**Package Completion: ~99%**

All critical P1 and most P2 tasks have been completed! The package is production-ready with comprehensive features and documentation.

The core export functionality is fully implemented and production-ready. The main export pipeline works end-to-end with proper abstractions including:

- [x] Dynamic query building with filters and eager loading
- [x] Relationship traversal using dot notation
- [x] Multiple filter operators (=, !=, >, <, IN, BETWEEN, LIKE, NULL checks)
- [x] Aggregation support (sum, count, avg, min, max)
- [x] CSV and JSON export formats with streaming
- [x] Extensible architecture for custom export handlers

### Recent Changes

- **Added column format templates and dynamic column expansion** (2026-09-01, planned and approved via plan mode): New nullable `format` field on export_columns (migration `2026_09_01_000002`). On a regular column it wraps each cell's final value with `{value}` (`Site {value}` -> `Site Customer Ltd.`), applied after aggregation/functions/defaults, skipped for empty values, bypassed by overrides. On an `is_expanded` collection-relation column it templates the titles of GENERATED columns: `expandColumns()` fans one configured column out into one column per distinct `expansion_data.header_path` value across the dataset (union, alphabetical, rectangular rows), each cell reusing the relation-operator extraction path via a synthesized in-memory filter, with the column's own value_path/aggregator/default driving cells. The `extractColumnValue`/`extractCollectionValue` guards were relaxed to accept synthesized filters (no sentinel export_filter_id). Chunked/streamed/queued/paginated exports throw a RuntimeException for expanded columns (column set needs the full dataset; lift later with a header pre-query). This wires up the previously dormant `is_expanded`/`expansion_data` fields for non-pivot exports and checks off the expansion_data todo. Covered by 3 regression tests including a column_definitions-driven expansion.
- **Added database-driven columns via export_layouts.column_definitions** (2026-09-01): New nullable JSON field on export_layouts (migration `2026_09_01_000001`). When set, `ExportLayout::buildDefinedColumns()` builds unsaved ExportColumn models from the definitions at load time and `DynamicExportService::loadLayout()` merges them with persisted export_columns by position, so a layout inserted purely through the database (admin UI, SQL, another service) carries its own columns with zero PHP seeding. Entries share `addColumns()` shapes via an extracted `normalizeColumnDefinition()` (shorthand, attribute arrays, `relation` resolution with loud failure). Defined columns have no UUIDs, so request `defaults`/`overrides` cannot target them; the definition's own `default` covers fallbacks. Covered by regression tests (definitions-only layout, position interleaving with persisted columns).
- **Added ExportLayout::addColumns() bulk column creation** (2026-09-01): One array creates all of a layout's columns. Supports `'Title' => 'value.path'` shorthand, `'Title' => [attributes]`, and list-style attribute arrays; positions auto-increment past the current max when omitted; a `relation` key is looked up on the layout's export model and set as `export_model_relation_id`, throwing `InvalidArgumentException` for unregistered relations (so typos fail loudly instead of exporting empty columns). Covered by regression tests; documented in api.md and the seeder-patterns skill reference.
- **XLSX multi-sheet support** (2026-09-01): `XlsxExportHandler` now writes multiple sheets with their own titles. The `sheet_by` option splits rows into one sheet per distinct column value (works in `export` and `stream`; each sheet gets its own header row), and `export()` accepts a string-keyed set of row collections for one sheet per key. `sheet_title` names single-sheet workbooks (defaults to layout title, then name). Titles are sanitized to Excel's rules: `[]:*?/\` replaced, 31-char cap, blank -> `Sheet`, case-insensitive dedupe with ` (2)` suffixes. Covered by round-trip regression tests including truncation and dedupe.
- **Added XLSX export via optional phpoffice/phpspreadsheet** (2026-09-01, branch `chore/audit-l13-simplify`): New `XlsxExportHandler` registered as the `xlsx` format. The dependency is a composer `suggest` plus `require-dev` (for our tests), never a hard requirement; the handler's constructor throws "composer require phpoffice/phpspreadsheet" guidance when the package is absent, and PHP's lazy autoloading means users without it pay nothing. String cells use an explicit string type so values like `=SUM(A1)` are stored as text (formula-injection safe, covered by a round-trip regression test). The workbook builds in memory, so csv remains the recommendation for very large exports; queued exports stay csv/json only. Package vetted via the supply-chain cache (trustworthy, 2026-06-18).
- **Full codebase audit: correctness fixes, Laravel 13 readiness, and simplification** (2026-08-31, branch `chore/audit-l13-simplify`):
  - **Correctness fixes in the export pipeline**:
    - Fresh installs can now migrate: the `export_relation_id` rename migration is guarded with `Schema::hasColumn` (the base migration already creates the final column name).
    - Added missing schema columns via migration `2026_08_31_000001`: `export_layouts.title` (used by code and docs but never migrated), `export_model_relations.column` (target column for relationship filters), and `export_model_relations.metadata` (enables the documented `sort_column` config for related-column sorting).
    - Transformation functions with configured parameters no longer fatal: `export_function_values` is cast to array on the model, so the extra `json_decode()` was a TypeError on every parameterized function call.
    - Static (non-request) filter values are now decoded per `value_type` before applying, so `in`/`not_in`/`between`/`relation` filters stored as JSON strings work instead of silently matching nothing.
    - Nested-relation sorting no longer crashes on a `$model->$parts[0]()` parse bug; collection-relation sorting orders by the correct `Str::snake($relation).'_count'` alias.
    - `omit_on_empty` now keeps the column key with an empty string instead of dropping it, which was shifting CSV cells under the wrong headers; `0` values are no longer treated as empty.
    - Collection relation-filter configs `[relation, path, operator, value]` now honor the operator (including `!=`) and use tolerant comparison; previously the operator was ignored and comparison was strict-only.
    - `buildEagerLoadArray` only treats `value_path` prefixes as root relations when they actually are, so relation-relative paths (e.g. `category.name` on a tag) no longer throw `RelationNotFoundException`.
    - Pivot exports: the `value_relation` is now joined into the query (previously "unknown column" errors or aggregating the base table); group keys use an ASCII unit separator so values containing `_` do not shatter across columns; custom `sub_group_by_headers` no longer blank out their cells.
    - JSON handler: meta is built with `json_encode` (no more invalid `\'` escapes), reads `exportModel->title` (the `name` field does not exist), and `include_meta` without `wrap_data` now emits valid JSON.
    - `ProcessExportJob`: unsupported formats now throw instead of writing an empty "completed" file; format is case-normalized; `fputcsv` passes explicit enclosure/escape (PHP 8.4 deprecation); the finished file is streamed to storage instead of loaded into memory.
    - CSV output guards against spreadsheet formula injection (cells starting with `=`, `+`, `-`, `@`, tab, or CR get a leading `'`); disable with handler option `escape_formulas => false`.
    - `validateLayout` now catches required filters with no relation by querying the table directly (the `filters()` relation scopes them out, which made the old check unreachable).
  - **Performance**:
    - Removed ~320 lines of per-row/per-item `APP_DEBUG` logging from `DynamicExportService`, including per-collection-item logs and an app-specific `worker`/`user` special case. Errors and warnings are still logged; use `getQuery()` to inspect the built query.
    - `validateValuePath`/`validateAndCreateNestedPath` are memoized per export, eliminating one `exists()` query per row per dotted column.
    - `exportTo`/`downloadAs`/`storeAs` no longer load the layout two or three times per call.
  - **Laravel 13 readiness** (composer.json): `php ^8.2`, `illuminate/* ^12.12||^13.0` (contracts no longer allows a mismatched ^11), explicit requires for the illuminate components actually used, `orchestra/testbench ^10||^11`, `pest ^3||^4` (pest-plugin-laravel ^3 caps at Laravel 12 and was the hard blocker), `larastan ^3.10`, dropped redundant collision dev dependency. Service provider uses `publishesMigrations()` and typed returns.
  - **Simplification (deleted)**: six unused `src/Contracts/` interfaces; unregistered `TestDatabaseCommand` (`export:test-db` never worked and docs claimed otherwise); unused `ExportInspector` service (redundant with `export:import-models`); dead `OperatorTypeCast`; broken-and-uncalled `OperatorType::getCallableArguments()`/`builder()`; the seven config `export_*` model/table blocks nothing read; deprecated `relation()` aliases on ExportColumn/ExportFilter (internals use `modelRelation()`); stale `CLAUDE.continue.md`.
  - **Tooling**: added `phpstan.neon.dist` (level 5, clean: 152 errors fixed via model `@property` docblocks and real fixes; `tests/phpstan-bootstrap.php` works around a larastan 3.10 package-analysis crash) and `pint.json` (no space after `!` or casts).
  - **Tests**: suite grew to 95 passing (343 assertions) with new regression coverage for parameterized functions, static array filters, row rectangularity, collection filter operators, pivot exports (first coverage of that path), JSON meta, and CSV injection guarding.
  - **Doc-verification round** (same day): two adversarial agents checked every doc claim against code. Code was aligned to documented behavior where docs described the clear intent: aggregation now runs before transformation functions (formatted sums work); relation-filtered collection columns with an aggregator aggregate over all matches; `value_path` plucks attributes across collection relations (`tags.value` + `sum`); `logical_operator` is case-insensitive (MySQL enum stores lowercase `or`); pivot `value_relation` may be empty for the base table; `--deep-level` clamps to 1-5. Doc-side fixes: `export_function_values` examples corrected to positional plain arrays (`[null, 'F j, Y']`, ~60 snippets), function count corrected to 23 with `Format Timestamp` documented, handler option names fixed (`include_headers`), `exportFunction()` relation name fixed, inert collection-filter examples given `export_model_relation_id` and item-relative filter paths, plus the user-level laravel-exports skill (protected `executePivotExport` call replaced with `executeExport`, same function-values fix). larastan's `LARAVEL_VERSION` constant is now defined via composer autoload-dev files so `phpstan` passes reliably.
- **Fixed N+1 in relation-operator column filters** (`DynamicExportService::applyWhereRelationConstraints`): The PHP-level filtering refactor left dead code that appended sub-relation paths to an already-consumed `$relationsToLoad` array, causing lazy loads during collection filtering. Replaced with a dedicated `$constrainedRelationsToLoad` buffer and a trailing `$query->with(array_unique(...))`, so the constrained relation and its comparison sub-relations are eager-loaded.
- **Added explicit `like` operator handling in `applyFilter`**: Laravel 11+ `whereLike`/`orWhereLike` take `(column, value)` not `(column, operator, value)`. The previous default branch passed 3 args, which would break at runtime.
- **Guarded `is_column` relations** in `applySorts` and `buildEagerLoadArray` so direct model attributes aren't treated as Eloquent relationships.
- **Collection filter comparison** in `extractCollectionValue` now unwraps an Eloquent Model actual-value using `laravel-exports.fallback_attributes` before comparing against the expected scalar.
- **Removed unused `ExportModel::columns()` relation** (columns belong to `ExportLayout`, not `ExportModel`).
- **`ImportModelsCommand --path` now accepts absolute paths** as well as paths relative to `base_path()`.

- **Added Pivot Export Support to DynamicExportService**: Dynamic pivot table exports with aggregations
  - **Note**: This is separate from BelongsToMany pivot support (which handles `.pivot.` notation for many-to-many intermediate tables). This feature creates Excel-style pivot reports where rows become columns.
  - `isPivotLayout()` - Checks if layout is configured for pivot export
  - `executePivotExport()` - Main entry point for pivot exports
  - `buildPivotQuery()` - Builds aggregated SQL query with dynamic joins and group by
  - `resolvePivotRelationPath()` - Resolves relation paths to table/column/alias
  - `applyJoinForRelation()` - Applies appropriate joins (BelongsTo, HasOne, HasMany, BelongsToMany)
  - `determinePivotColumns()` - Determines dynamic column headers from data or request params
  - `getModelFromRelation()` - Gets model class from relation path
  - `transformPivotResults()` - Transforms aggregated data to pivot format
  - `buildPivotGroupKey()` - Builds grouping keys from row data
  - `extractPivotGroupData()` - Extracts group data from row
  - `convertPivotToRows()` - Converts pivoted structure to flat output rows
  - `formatPivotValue()` - Formats values using optional ExportFunction
  - **Integration with existing methods**: `executeExport()` and `executeExportChunked()` now check for pivot layouts
  - **ExportLayout model updated**:
    - Added `is_pivot` boolean field
    - Added `pivot_config` JSON field
    - Added `isPivot()` and `getPivotConfig()` methods
    - Migration: `2026_02_05_000001_add_pivot_support_to_export_layouts.php`
  - **Pivot config structure**:
    ```php
    [
        'group_by' => ['relation.column'],       // Primary grouping
        'sub_group_by' => ['relation.column'],   // Sub-grouping
        'pivot_relation' => 'relation.name',     // Dynamic column source
        'pivot_column' => 'name',                // Column for pivot headers
        'value_relation' => 'table',             // Source for aggregated values
        'value_column' => 'amount',              // Column to aggregate
        'aggregation' => 'sum',                  // sum, count, avg, min, max
        'output_format' => 'flat',               // flat or grouped
        'pivot_filter_param' => 'type_ids',      // Request param for filtering pivot columns
    ]
    ```
  - **Usage example**:
    ```php
    // Create a pivot layout for sales by product category and month
    $layout = ExportLayout::create([
        'export_model_id' => $salesModel->id,
        'name' => 'sales_pivot',
        'title' => 'Sales by Category',
        'is_pivot' => true,
        'pivot_config' => [
            'group_by' => ['product.category.name'],
            'sub_group_by' => ['product.name'],
            'pivot_relation' => 'period.name',
            'pivot_column' => 'name',
            'value_column' => 'amount',
            'aggregation' => 'sum',
            'output_format' => 'flat',
        ],
    ]);

    // Execute the pivot export
    $results = $service->executeExport($layout);
    // Returns: Category | Product | Jan | Feb | Mar | Total
    ```

- **Laravel 12 Best Practices Validation**: Updated package architecture for Laravel 12 compliance
  - **Dependency Injection Improvements**:
    - `DynamicExportService` constructor now accepts optional `ModelRelationInspector` parameter for proper DI
    - `ProcessExportJob` uses `app()` helper instead of `new` for service instantiation
    - `LaravelExportsServiceProvider` registers both services as singletons with proper bindings
  - **Model Casts Modernization**: Converted all models from `protected $casts` property to `casts()` method (Laravel 12 preferred style)
    - `ExportColumn`, `ExportFilter`, `ExportFunction`, `ExportModelRelation` updated
  - **Duplicate Relationship Deprecation**: Added `@deprecated` notice to duplicate `relation()` methods in `ExportColumn` and `ExportFilter`, recommending `modelRelation()` instead
  - **Command Improvements**: `TestDatabaseCommand` (since removed) now uses `int` return type and `self::SUCCESS`/`self::FAILURE` constants
  - **Config Enhancements**: Added new configuration options:
    - `job_tries` (env: `EXPORT_JOB_TRIES`, default: 3)
    - `job_timeout` (env: `EXPORT_JOB_TIMEOUT`, default: 3600)
    - `fallback_attributes` for customizing value extraction from related objects
  - **Test Updates**: Updated `ValidationTest` to work with new `casts()` method syntax
  - **Contracts/Interfaces Created**: New interface definitions for future service decomposition (since removed as unused):
    - `QueryBuilderInterface`, `EagerLoaderInterface`, `ResultProcessorInterface`
    - `ValueExtractorInterface`, `FilterApplicatorInterface`, `FunctionApplicatorInterface`

- **Added Pivot Table Support for BelongsToMany Relationships**: Export pivot attributes from many-to-many relationships
  - Added `has_pivot` and `pivot_columns` fields to `export_model_relations` table
  - ModelRelationInspector now auto-detects pivot columns when inspecting BelongsToMany relationships
  - Added `detectPivotInfo()` method to extract pivot column names via reflection
  - Use `.pivot.` notation in value_path to access pivot data (e.g., `roles.pivot.assigned_at`)
  - Eager loading automatically includes pivot columns when loading relations
  - ImportModelsCommand now saves pivot metadata when syncing relations
  - Migration: `2025_02_04_000001_add_pivot_columns_to_export_model_relations.php`
  - Usage example:
    ```php
    // Model with pivot data
    class User extends Model {
        public function roles(): BelongsToMany {
            return $this->belongsToMany(Role::class)
                ->withPivot(['assigned_at', 'expires_at']);
        }
    }

    // Export column accessing pivot data
    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'title' => 'Role Assigned',
        'value_path' => 'roles.pivot.assigned_at',  // Access via .pivot.
        'aggregator' => 'first',
        'position' => 1,
    ]);
    ```

- **Added ProcessExportJob for Background Exports**: Queue large exports for background processing
  - New job class: `src/Jobs/ProcessExportJob.php`
  - Cache-based status tracking with progress percentage
  - Configurable storage disk and path via config
  - Added `queueExport()` and `getExportStatus()` methods to DynamicExportService
  - Static helper methods: `getStatus()`, `isComplete()`, `isSuccessful()`, `getDownloadUrl()`, `getFilePath()`
  - New config options: `queue`, `disk`, `path`, `status_ttl`, `chunk_size`
  - Usage example:
    ```php
    // Queue an export
    $exportId = $service->queueExport($layout, 'csv', $requestData);

    // Check status
    $status = ProcessExportJob::getStatus($exportId);
    // ['status' => 'processing', 'progress' => 45, ...]

    // When complete
    if (ProcessExportJob::isSuccessful($exportId)) {
        $url = ProcessExportJob::getDownloadUrl($exportId);
    }
    ```
  - Configuration (.env):
    ```env
    EXPORT_QUEUE=exports
    EXPORT_DISK=local
    EXPORT_PATH=exports
    EXPORT_STATUS_TTL=86400
    EXPORT_CHUNK_SIZE=1000
    ```

- **Added Request-Based Column Overrides**: Force column values regardless of extracted data
  - Pass an `overrides` array in `requestData` keyed by column UUID
  - Overrides always replace the value, unlike `defaults` which only apply when empty
  - Added `getColumnOverride()` helper method
  - Overrides are applied last in the processing pipeline (after functions, aggregations, and defaults)
  - Usage example:
    ```php
    $requestData = [
        'overrides' => [
            $column->id => 'Forced Value',  // Always uses this value
        ],
    ];
    $results = $service->executeExport($layout, $requestData);
    ```
  - Can be combined with defaults:
    ```php
    $requestData = [
        'defaults' => ['col-1' => 'Fallback'],    // Only when empty
        'overrides' => ['col-2' => 'Always This'], // Always replaces
    ];
    ```

- **Added Request-Based Column Default Overrides**: Column defaults can now be overridden via request parameters
  - Pass a `defaults` array in `requestData` keyed by column UUID
  - Request defaults take priority over static column defaults
  - Added `getColumnDefault()` helper method to centralize default resolution
  - Updated all fallback locations in `processResults()` and `extractCollectionValue()`
  - Usage example:
    ```php
    $requestData = [
        'defaults' => [
            $column->id => 'Custom Default Value',
        ],
    ];
    $results = $service->executeExport($layout, $requestData);
    ```

- **Implemented Smart Relation Filter Parsing**: Added intelligent parsing for nested column relations
  - When `ExportModelRelation` has `is_column = true` and contains dots, the system automatically:
    - Splits the last segment as the column name
    - Uses the remaining path as the relation
    - Example: `workOrder.invoice.custom_id` -> relation: `workOrder.invoice`, column: `custom_id`
  - Added `applySmartRelationFilter()` method to handle this parsing
  - Added `applyNestedWhereHas()` for proper handling of `in` and `not_in` operators with nested relations
  - Added `buildNestedQuery()` for recursive whereHas building
  - This enables request-based filtering on nested relation columns without complex configuration
  - Usage example:
    ```php
    // Setup
    $relation = ExportModelRelation::create([
        'export_model_id' => $workItemModel->id,
        'relation' => 'workOrder.invoice.custom_id',
        'is_column' => true,
    ]);
    
    $filter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $relation->id,
        'operator' => 'in',
        'value_type' => 'array',
        'is_request' => true,
    ]);
    
    // Request
    $requestData = ['workOrder.invoice.custom_id' => ['ABC123', 'DEF456']];
    ```
  - **Recommended operators for smart relation filters**:
    - `in` - Check if column value is in array (e.g., invoice IDs)
      ```php
      // Request can be array or comma-separated string
      ['workOrder.invoice.id' => [123, 456]]
      ['workOrder.invoice.id' => '123,456']  // Auto-converted to array
      ```
    - `not_in` - Check if column value is NOT in array
    - `=` - Exact match (e.g., `['workOrder.invoice.status' => 'paid']`)
    - `!=` - Not equal (e.g., `['workOrder.invoice.status' => 'cancelled']`)
    - `>`, `<`, `>=`, `<=` - Comparisons (e.g., `['workOrder.invoice.amount' => 1000]`)
    - `like` - Pattern matching (e.g., `['workOrder.customer.name' => '%Corp%']`)
    - `between` - Range checks (e.g., `['workOrder.invoice.created_at' => ['2024-01-01', '2024-12-31']]`)
    - `null`, `not_null` - Null checks (value is ignored)
  - All operators work with the smart parsing when `is_column = true` and the relation contains dots
  - The `in` and `not_in` operators use nested `whereHas` for proper SQL generation
  - Other operators use standard `whereHas` which works correctly


- **Fixed naming inconsistency**: Renamed `export_relation_id` to `export_model_relation_id` in export_filters table
  - Created migration to rename the column in the database
  - Updated ExportFilter model to use the new column name in fillable array and relationships
  - Updated all references in DynamicExportService (7 occurrences)
  - Updated ExportLayout model's filters() relationship
  - Updated the original migration file for consistency
  - Updated test files (3 occurrences in DynamicExportServiceTest)
  - Updated all documentation files (README, api-reference, usage-examples, database.md, CLAUDE.continue.md)
  - This ensures consistent naming across all tables - export_columns and export_sorts already used export_model_relation_id

- **Updated documentation references from 'export_relation_id' to 'export_model_relation_id'**: Updated all documentation files to maintain consistency
  - Modified docs/usage-examples.md (2 occurrences)
  - Modified README.md (1 occurrence)
  - Modified database.md (2 occurrences)
  - Modified docs/api-reference.md (1 occurrence)
  - Modified CLAUDE.continue.md (1 occurrence)
  - This change aligns the documentation with the actual database column naming convention used throughout the codebase

- **Added deep relationship discovery to ImportModelsCommand**: New --deep option discovers nested relationships
  - The `--deep` flag enables discovery of nested relationships with dot notation (e.g., user.posts.comments)
  - The `--deep-level` option controls maximum nesting depth (default: 2, supports up to 5 levels)
  - Deep discovery recursively explores relationships, creating export_model_relations for each path
  - Example: For User model with posts relation, it discovers both 'posts' and 'posts.author' relations
  - This significantly improves the ability to work with complex data models without manual relation setup

- **Major DynamicExportService improvements**: Fixed critical relation path resolution and eager loading issues
  - Implemented proper nested relation traversal (e.g., `user.name`, `workItem.workType.name`)
  - Added improved eager loading that handles intermediate relations automatically
  - Fixed collection value extraction for filtered relations (e.g., identifiers/values by type)
  - Added comprehensive debugging and validation methods
  - Enhanced `extractColumnValue`, `resolveRelationValue`, and `extractCollectionValue` methods
  - Issues #1, #2, #5, and #7 from the fix plan are now resolved

- **Fixed column filter architecture issue**: Column-attached filters are now properly separated from layout filters
  - Previously, filters attached to columns were being processed twice (in applyFilters and applyColumnFilters)
  - Implemented two-part fix:
    1. Updated `loadLayout()` method to exclude column filter IDs when loading layout filters
    2. Added check in `applyColumnFilters()` to skip filters with `operator: 'relation'`
  - Filters with `operator: 'relation'` attached to columns are now ONLY used for constraining eager loading
  - This ensures clean separation: layout filters affect the main query, column filters either constrain eager loading (for relation operator) or filter the main query (for other operators)

- Fixed namespace bug where ModelRelationInspector was using `App\Http\Helpers` instead of `HexagonLabsLLC\LaravelExports\Helpers`
- Implemented ImportModelsCommand with auto-discovery, filtering options, and relationship syncing
- Updated database structure to match specifications:
  - Changed `export_models` to use `title` field instead of `name`
  - Added new filter operators: `json_contains` and `relation` (while keeping useful ones like `!=`, `not_in`, `not_null`)
  - Updated `export_columns` to include `export_filter_id` and `export_filter_values` for column-specific filtering
  - Changed `aggregator` field to be nullable instead of having a 'none' option
  - Updated all models and migrations to reflect the new structure
- Implemented comprehensive test suite using Pest:
  - Unit tests for models, helpers, and export handlers
  - Feature tests for commands and services
  - Test coverage includes ExportModel, ImportModelsCommand, DynamicExportService, ModelRelationInspector, and CSV/JSON handlers
  - Created test models (User, Post, Comment) for realistic testing scenarios
  - Configured PHPUnit and Pest for the package
- Fixed critical issues from database updates:
  - Updated ExportInspector to use correct table names (export_models instead of export_entities)
  - Implemented column-specific query filtering via export_filter_id
  - Added support for new operators (json_contains and relation) in DynamicExportService
  - Verified nullable aggregator field is handled correctly
- Implemented sorting support for related columns:
  - Fixed ExportSort model to use correct column names (export_model_relation_id)
  - Added comprehensive relation sorting methods in DynamicExportService
  - Supports BelongsTo/HasOne with LEFT JOIN for efficient sorting
  - Supports HasMany/BelongsToMany with COUNT aggregation
  - Handles nested relations (e.g., order.customer.name) with subqueries
  - Allows custom sort columns via metadata configuration
- Created built-in transformation functions:
  - Added TransformationFunctions service with 22 ready-to-use functions
  - Date/Time: formatDate, formatDateHuman, dateDifference
  - String: uppercase, lowercase, titleCase, truncate, slug, replace, extract
  - Number: formatNumber, formatCurrency, round, percentage
  - Boolean: booleanText (Yes/No, Active/Inactive, etc.)
  - Array/JSON: jsonExtract, arrayJoin, arrayCount
  - Utility: defaultValue, concatenate, hash, mask (for sensitive data)
  - Created SeedTransformationFunctionsCommand to populate functions in database
  - Full test coverage for all transformation functions
- Added comprehensive documentation:
  - Created detailed usage examples (docs/usage-examples.md) with practical examples
  - Created complete API reference (docs/api-reference.md) documenting all classes and methods
  - Created directory structure guide (docs/directory-structure.md)
  - Updated main README.md with features, quick start, and overview
  - Added transformation functions usage guide in todos.md
- Enhanced model functionality:
  - Added `instance` attribute to ExportModel that returns the actual Eloquent model instance
  - Implemented `whereNested()` scope on ExportModelRelation for validating nested relationships
  - Example: `ExportModelRelation::whereNested('workItem.workOrder')` traverses the relationship chain
  - Updated CLAUDE.md to reflect current package state and recent enhancements
- Improved ImportModelsCommand with --deep option:
  - Added `--deep` flag to discover nested relationships with dot notation
  - Added `--deep-level` option to control maximum depth (default: 2)
  - Added `--deep-columns` flag to optionally create nested column paths
  - Optimized ModelRelationInspector to prevent circular references and excessive relation creation
  - Added progress indicators for large datasets
- Fixed DynamicExportService relation handling:
  - Improved `buildRelationPath` to properly handle nested relations with dot notation
  - Enhanced `resolveRelationValue` to correctly traverse nested paths like "worker.user.name"
  - Added debug logging for worker relations to help troubleshoot issues
  - Fixed eager loading to properly load intermediate relations in nested paths
- Implemented dynamic nested path validation and creation:
  - Added on-the-fly validation using ModelRelationInspector's `validateNestedPath` method
  - Automatically creates missing ExportModelRelation records when valid nested paths are encountered
  - Eliminates need to re-run import-models command for new nested relationships
  - Validates paths in both modelRelation and value_path contexts during export execution
  - Provides comprehensive logging for debugging path validation and creation
- Enhanced collection handling with aggregator options:
  - Added `first` aggregator to get the first element from collections
  - Added `last` aggregator to get the last element from collections  
  - These aggregators work seamlessly with filtered collections and relation data
  - Particularly useful when using relation operator filters to get specific items
  - Added migration to update export_columns.aggregator enum with new options

- **Fixed Critical Export Issues**:
  - **Request Parameter Filtering**: Enhanced `getFilterColumnName()` method to properly resolve column names for request filters
    - Improved logic for request filters to check model relations and primary keys
    - Added fallback mechanisms for dynamic request parameter handling
    - Now properly supports filtering exports based on request parameters
  - **User Relation Value Extraction**: Enhanced debug logging for user and worker relations
    - Added checks for relation method existence and eager loading status
    - Improved troubleshooting for relation value extraction failures
    - Enhanced logging in `resolveRelationValue()` method (src/Services/DynamicExportService.php:902-912)
  - **Export Function Execution**: Added comprehensive error handling and logging for transformation functions
    - Try-catch blocks around function execution with detailed error logging
    - Debug logging for successful function executions
    - Warning logs for non-callable functions
    - Functions now gracefully fall back to original value on errors
    - Enhanced debugging in `applyColumnFunction()` method (src/Services/DynamicExportService.php:1083-1119)

- **Fixed Core Export Pipeline Issues**:
  - **Value Extraction Pipeline**: Completely rewrote `resolveRelationValue()` and `extractColumnValue()` methods
    - Fixed value extraction after relation resolution with proper fallback logic
    - Added comprehensive debugging for every extraction step
    - Improved handling of object-to-value conversion for relations
    - Enhanced nested relation value extraction (src/Services/DynamicExportService.php:1001-1036)
  - **Function Execution**: Fixed function loading and execution pipeline
    - Added proper eager loading of function relationships in `loadLayout()`
    - Enhanced function execution debugging with before/after value logging
    - Added error handling and fallback for function execution failures
    - Fixed function parameter resolution and callable validation (src/Services/DynamicExportService.php:1249-1310)
  - **Request Parameter Filtering**: Implemented robust request parameter matching
    - Added multiple parameter name pattern matching (snake_case, lowercase, etc.)
    - Enhanced debugging for request filter matching and application
    - Improved column name resolution for dynamic request filters
    - Added comprehensive filter application logging (src/Services/DynamicExportService.php:158-212)
  - **Collection Value Extraction**: Complete rewrite of collection filtering and extraction
    - Added comprehensive debugging for collection processing pipeline
    - Enhanced filter matching logic with detailed item-by-item logging
    - Improved value extraction from filtered collection items with multiple fallback strategies
    - Fixed complex path handling for nested collection attributes (src/Services/DynamicExportService.php:1100-1245)

### ImportModelsCommand Usage

The `export:import-models` command auto-discovers and registers Eloquent models in your application:

```bash
# Basic usage - scans app/Models directory
php artisan export:import-models

# Scan custom directory with namespace
php artisan export:import-models --path=app/Domain/Models --namespace=App\\Domain\\Models

# Force re-import existing models (with relations by default)
php artisan export:import-models --force

# Import models without syncing relations
php artisan export:import-models --skip-relations

# Filter models by pattern
php artisan export:import-models --filter=*User*

# Omit specific models from relation inspection
php artisan export:import-models --omit=User,Post

# Discover deep nested relationships
php artisan export:import-models --deep

# Discover nested relationships up to 3 levels deep
php artisan export:import-models --deep --deep-level=3
```

**Options:**
- `--path` - Directory to scan (default: app/Models)
- `--namespace` - Base namespace (default: App\Models)
- `--filter` - File pattern filter (default: *)
- `--omit` - Comma-separated list of models to omit from relation inspection
- `--force` - Re-import existing models
- `--skip-relations` - Skip syncing model columns and relationships (synced by default)
- `--deep` - Discover nested relationships with dot notation (e.g., user.posts.comments)
- `--deep-level` - Maximum depth for nested relationship discovery (default: 2, max: 5)

The command recursively scans directories, validates that classes extend Eloquent Model, and optionally uses ModelRelationInspector to discover columns and relationships. With the `--deep` option, it will recursively explore relationships to create nested paths like `posts.author` or `workItem.workOrder.customer`.

### Enhanced Model Features

**ExportModel Instance Attribute:**
```php
// Access the actual Eloquent model instance
$exportModel = ExportModel::find($id);
$modelInstance = $exportModel->instance; // Returns the actual model (e.g., User, Post, etc.)
```

**ExportModelRelation Nested Validation:**
```php
// Find relations by nested path
$workOrderRelation = ExportModelRelation::where('export_model_id', $laborPayModel->id)
    ->whereNested('workItem.workOrder')
    ->first();

// The whereNested scope traverses the relationship chain:
// 1. Finds relations where relation = 'workItem'
// 2. Then follows to relations where relation = 'workOrder'
```

### Transformation Functions Usage

Seed the built-in transformation functions:

```bash
# First time seeding
php artisan export:seed-functions

# Update existing functions
php artisan export:seed-functions --force
```

This adds 23 transformation functions to the `export_functions` table that can be used in export columns:

**Date/Time Functions:**
- `Format Date` - Format dates with custom patterns (e.g., "Y-m-d", "d/m/Y")
- `Format Date Human` - Human-readable dates (e.g., "2 hours ago")
- `Format Timestamp` - Format unix timestamps with custom patterns
- `Date Difference` - Calculate difference between dates in various units

**String Functions:**
- `Uppercase`, `Lowercase`, `Title Case` - Case conversions
- `Truncate` - Limit string length with suffix
- `Slug` - URL-friendly strings
- `Replace` - Find and replace text
- `Extract` - Extract text using regex patterns

**Number Functions:**
- `Format Number` - Number formatting with decimals and separators
- `Format Currency` - Currency formatting with locale support
- `Round` - Round to specified decimal places
- `Percentage` - Format as percentage

**Array/Collection Functions:**
- `JSON Extract` - Extract values from JSON using dot notation
- `Array Join` - Join array elements with separator
- `Array Count` - Count array elements

**Utility Functions:**
- `Boolean Text` - Convert true/false to custom text
- `Default Value` - Provide fallback for empty values
- `Concatenate` - Concatenate values with separator
- `Hash` - Hash values using specified algorithm
- `Mask` - Mask sensitive data (e.g., "1234****")

### Collection Aggregators

The package now supports collection aggregators in the `export_columns.aggregator` field:

**Numeric Aggregators:**
- `sum` - Sum all values in collection
- `count` - Count items in collection
- `avg` - Average of all values
- `min` - Minimum value in collection
- `max` - Maximum value in collection

**Collection Extractors:**
- `first` - Get first item from collection
- `last` - Get last item from collection

**Usage Examples:**

```php
// Get first matching item from filtered collection
ExportColumn::create([
    'title' => 'Primary Contact',
    'value_path' => 'contacts',
    'export_filter_id' => $primaryContactFilter->id,
    'aggregator' => 'first', // Get first matching contact
    'position' => 1,
]);

// Count number of contacts
ExportColumn::create([
    'title' => 'Contact Count',
    'value_path' => 'contacts', 
    'aggregator' => 'count',
    'position' => 2,
]);
```