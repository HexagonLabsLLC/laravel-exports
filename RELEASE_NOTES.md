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
- Test suite: 92 tests, 334 assertions, including first coverage of the pivot
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
