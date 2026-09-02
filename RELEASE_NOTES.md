# v1.0.0-rc.6 (feat/lazy-catalog-builders)

The export catalog becomes a lazily self-syncing cache, and layouts gain
fluent builders plus row-carried filter/sort definitions.

## Features

- **Layout validation** via a new read-only `LayoutValidator` (never writes,
  never lazy-syncs): `validate($layout)` works on persisted and unsaved
  layouts, `validateDraft($payload)` spot-checks raw UI form data before
  anything is saved, `ExportLayoutBuilder::validate()` checks staged layouts,
  and `save()` now reports every error at once instead of dying on the first.
  `php artisan export:validate` audits all layouts with a CI-friendly
  non-zero exit on errors. Every problem carries a stable `code` + `params`,
  and messages render through the `laravel-exports::validation` lang
  namespace - publish the `lang` tag to override wording with client-friendly
  text or add locales for multilingual apps.
- **Lazy catalog sync** (`laravel-exports.schema_sync`, default `lazy`): models
  and relation paths sync into export_models/export_model_relations the first
  time a layout references them - `export:import-models` is now optional.
  `verify` mode also re-syncs when a model's reflected schema fingerprint
  (new `export_models.schema_hash`) drifts; `manual` restores the old
  never-write behavior and throws helpful errors for missing entries.
- **Model-class layouts**: `export_layouts.model` (nullable FQCN) can replace
  `export_model_id` (now nullable; exactly one required, `model` wins). A
  layout inserted with just a class name runs with zero catalog setup.
- **`filter_definitions` / `sort_definitions`** JSON on export_layouts: with
  `column_definitions`, one INSERT is a complete runnable export. Paths
  resolve against the (self-syncing) catalog; dotted attribute paths ride the
  smart relation filter parsing; request filters match path-derived keys.
- **`ExportLayoutBuilder`**: fluent, transactional construction of
  catalog-backed layouts (`for(Post::class)->column(...)->filter(...)
  ->sort(...)->save()`), with `SchemaSync::describe()` as the UI schema
  endpoint.
- **`SchemaSync` service** extracted from ImportModelsCommand: one shared,
  race-safe write path for the whole catalog (a new unique index on
  export_model_relations backs the upserts; the migration de-duplicates
  existing rows first).

## Behavior changes

- **`or` filters now group with the preceding filter**: `A, or B, C` builds
  `(A OR B) AND C` instead of the flat `A OR B AND C` SQL precedence, so an
  or-pair can no longer disjoin an unrelated scoping filter. Put a scoping
  filter after the or-group to keep it ANDed.
- **Filter application order is now deterministic** (creation order via
  ordered-uuid `orderBy('id')`); previously it followed database index order,
  which could silently move an `or` filter's placement.
- **Column filters coerce comma-separated request strings** into arrays for
  `in`/`not_in`/`between`, matching layout filters; `'120,30'` and
  `['120','30']` now behave identically.
- Referenced nested paths are no longer ad-hoc INSERTed during exports in all
  cases: they sync through SchemaSync under `lazy`/`verify` and are left
  alone (in-memory validation only) under `manual`.
- The lazy-sync migration alters export_layouts (`export_model_id` nullable)
  and adds a unique index; on dirty catalogs the de-duplication step removes
  duplicate relation rows (oldest kept). SQLite rebuilds the table on the
  nullable change.
- Apps routing reads to database replicas should set `schema_sync=manual`
  (runtime writes otherwise occur on catalog misses).

# v1.0.0-rc.5

Correctness, performance, and Laravel 13 readiness release over rc.4, driven by
a full codebase audit. Includes one new migration and several removals of dead
or broken public surface.

## Breaking / behavior changes

- **PHP 8.2+ required; Laravel 12.12+ or 13 supported.** Dev tooling moves to
  testbench ^10||^11 and Pest ^3||^4.
- **Removed:** `ExportInspector` service (use `export:import-models`), the six
  unimplemented `Contracts` interfaces, `OperatorTypeCast`, the never-registered
  `export:test-db` command, `OperatorType::builder()` and
  `OperatorType::getCallableArguments()` (both broken), and the deprecated
  `relation()` aliases on `ExportColumn`/`ExportFilter` (use `modelRelation()`).
- **Removed unread config:** the seven `export_*` model/table blocks in
  `config/laravel-exports.php` were never consulted by any code.
- **`omit_on_empty` no longer drops the column key**; it emits an empty string
  so CSV rows stay aligned with their headers, and `0` is no longer treated as
  empty.
- **CSV cells that spreadsheets would run as formulas are now prefixed with a
  single quote** by default; disable with the `escape_formulas => false`
  handler option.
- **JSON exports with `include_meta` always wrap rows in a `data` key** (the
  old meta-without-wrapper combination produced invalid JSON).
- **Queued exports throw for formats other than csv/json** instead of writing
  an empty file marked completed.
- **Per-row `APP_DEBUG` logging was removed** from the export pipeline; use
  `getQuery()` to inspect the built query. Errors and warnings are still
  logged.

## Features

- **Column `format` templates**: a nullable `format` field on export_columns
  wraps each cell's final value with a `{value}` template (`Site {value}` ->
  `Site Customer Ltd.`). Applied after aggregation, functions, and defaults;
  skipped for empty values; bypassed by request overrides.
- **Dynamic column expansion**: `is_expanded` + `expansion_data.header_path`
  on a collection-relation column now generate one column per distinct related
  value across the dataset (alphabetical, rectangular rows), with `format`
  templating the generated titles and the column's own value_path/aggregator/
  default driving each cell. Chunked, streamed, queued, and paginated exports
  throw for expanded columns (full dataset required).
- **Database-driven columns via `export_layouts.column_definitions`** (json,
  nullable): a layout row can carry its own column definitions, so layouts
  inserted purely through the database need no `export_columns` rows and no
  seeding code. Definitions use the same shapes as `addColumns()` (including
  `relation` resolution) and are merged with persisted columns by position at
  export time. Request `defaults`/`overrides` cannot target them (no UUIDs);
  use the definition's `default`.
- **`ExportLayout::addColumns()`** bulk-creates columns from one array:
  `'Title' => 'value.path'` shorthand, `'Title' => [attributes]`, or list-style
  attribute arrays. Positions auto-increment past the current max, and a
  `relation` key resolves to `export_model_relation_id` against the layout's
  export model (throwing for unregistered relations).
- **XLSX export format** via a new `XlsxExportHandler`. The
  `phpoffice/phpspreadsheet` dependency is optional (composer `suggest`, not
  `require`); the handler throws with install instructions when the package is
  absent, so nothing changes for users who never export xlsx. String cells are
  written with an explicit string type so data cannot execute as formulas.
  Multi-sheet workbooks are supported two ways: the `sheet_by` option splits
  rows into one sheet per distinct column value (also when streaming), and the
  handler accepts a string-keyed set of row collections for one sheet per key.
  Sheet titles are sanitized to Excel's rules (31 chars, no `[]:*?/\`, unique).
  Queued exports remain csv/json only.

## Fixes

- Fresh installs no longer fail on the `export_relation_id` rename migration;
  it is now guarded with `Schema::hasColumn`.
- New migration `2026_08_31_000001` adds columns the code already read:
  `export_layouts.title`, `export_model_relations.column` (relationship filter
  target), and `export_model_relations.metadata` (`sort_column` support).
- Transformation functions with configured parameters no longer throw a
  `TypeError` (`export_function_values` was double-decoded).
- Static filter values stored as JSON strings are decoded before applying, so
  `in`/`not_in`/`between`/`relation` filters match instead of returning empty
  exports.
- Nested-relation sorting no longer crashes; collection-relation sorting uses
  the correct snake_case `withCount` alias.
- Collection relation-filter configs honor their operator (including `!=`) and
  compare tolerantly across numeric string types.
- Relation-relative `value_path`s no longer crash eager loading.
- Pivot exports: `value_relation` is joined, group keys survive values
  containing underscores, and custom sub-group headers render their values.
- JSON meta is valid JSON and reads the export model `title`.
- `ProcessExportJob` passes explicit `fputcsv` enclosure/escape (PHP 8.4
  deprecation) and streams the finished file to storage instead of loading it
  into memory.
- Aggregation now runs before transformation functions, so a summed column can
  be formatted (sum then Format Currency) as the docs always described.
- Relation-filtered collection columns with an aggregator now aggregate over
  all matching items (count/sum/avg of a filtered subset); without an
  aggregator they keep first-match extraction.
- `value_path` like `orders.total` on a collection relation now plucks the
  attribute across the collection, making sum/avg/min/max over related columns
  work as documented.
- `logical_operator` comparison is case-insensitive; the lowercase `or` that
  MySQL's enum column stores now triggers OR grouping.
- Pivot `value_relation` set to an empty string now cleanly means the base
  table; `--deep-level` is clamped to the documented 1-5 range.

## Performance

- Removed roughly 320 lines of per-row debug logging from the hot path.
- Path validation is memoized per export, removing one `exists()` query per
  row per dotted column.
- `exportTo`/`downloadAs`/`storeAs` load the layout once instead of two or
  three times.

## Tooling

- `phpstan.neon.dist` at level 5, passing clean; models carry full `@property`
  docblocks for IDE support.
- `pint.json` enforces `!$var` and `(bool)$var` spacing.
- Test suite: 95 tests, 343 assertions, including first coverage of the pivot
  export path.

## Upgrade notes

Run migrations (`php artisan migrate`) for the new columns. If downstream code
called `ExportInspector`, `relation()` on columns/filters, or read the removed
config blocks, update as noted above. If any consumer depended on
`omit_on_empty` removing keys from JSON output, that behavior is gone.

# v1.0.0-rc.4

Bug-fix and polish release over rc.3. No breaking changes for normal usage; the
only removal is an unused public relation method that was never referenced
outside the package.

## Fixes

- **DynamicExportService: relation-operator column filters no longer N+1.**
  `applyWhereRelationConstraints` previously appended sub-relation paths to a
  variable that had already been consumed earlier in the method, so the
  sub-relations used for PHP-level collection filtering were lazy-loaded at
  extract time. Constrained relations and their comparison sub-relations are
  now collected into a dedicated buffer and eager-loaded via `with()`.
- **DynamicExportService: `like` operator no longer errors at runtime.** The
  default filter branch passed `(column, operator, value)`, but Laravel 11+
  `whereLike`/`orWhereLike` take `(column, value)`. `like` now has an explicit
  case that calls the builder with the correct arity.
- **DynamicExportService: `is_column` relations are no longer treated as
  Eloquent relationships.** `applySorts` and `buildEagerLoadArray` now skip
  `ExportModelRelation` rows marked `is_column`, preventing attempts to
  eager-load or join against what is really a model attribute.
- **DynamicExportService: collection filter comparisons unwrap related
  Models.** When the filtered attribute on a collection item resolves to an
  Eloquent Model, `extractCollectionValue` now extracts a comparable scalar
  via the configured `laravel-exports.fallback_attributes` list before
  comparing against the expected value.

## Features

- **`ImportModelsCommand --path` accepts absolute paths** in addition to paths
  relative to `base_path()`. This makes it easier to run the command against
  models that live outside the Laravel app root (shared packages, symlinked
  directories, etc.).

## Cleanup

- **Removed unused `ExportModel::columns()` relation.** Export columns belong
  to `ExportLayout`, not `ExportModel`, and this method had no callers.
- Minor formatting cleanup in `CsvExportHandler`.

## Upgrade notes

No migrations. No config changes required. If any downstream code called
`$exportModel->columns()` (it should not have), switch to
`$exportLayout->columns()`.
