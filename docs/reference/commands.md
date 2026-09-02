# Commands Reference

Artisan commands provided by the package.

## export:import-models

Import and register Eloquent models for exporting.

```bash
php artisan export:import-models [options]
```

Optional under the default `lazy` (and `verify`) schema sync mode: a model or relation path missing from the catalog is reflected and registered the first time a layout references it. Run this command to pre-populate the catalog in one pass (useful for UI picklists), or when `laravel-exports.schema_sync` is set to `manual`.

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--path` | Directory to scan (relative to `base_path()` or absolute) | `app/Models` |
| `--namespace` | Base namespace | `App\Models` |
| `--filter` | Class name pattern (fnmatch against the class basename, e.g. `*User*`) | `*` |
| `--omit` | Models to exclude from the scan and from relation inspection (comma-separated, class names relative to `--namespace`) | (none) |
| `--force` | Re-import existing models | false |
| `--skip-relations` | Skip syncing columns and relationships | false |
| `--deep` | Discover nested relationships | false |
| `--deep-level` | Maximum depth for nested discovery (clamped to 1-5) | 2 |
| `--deep-columns` | Create nested column paths during deep discovery | false |

### Basic Usage

```bash
# Scan default app/Models directory
php artisan export:import-models
```

### Custom Directory

```bash
php artisan export:import-models \
    --path=app/Domain/Models \
    --namespace=App\\Domain\\Models
```

### Import a Subset

Narrow an import to matching class names:

```bash
php artisan export:import-models --filter=*User*
```

Or scope by directory:

```bash
php artisan export:import-models \
    --path=app/Models/Billing \
    --namespace=App\\Models\\Billing
```

### Exclude Models

```bash
# Omit specific models from the scan and from relation inspection
php artisan export:import-models --omit=AuditLog,TempRecord
```

### Force Re-Import

```bash
# Update existing models and their relations
php artisan export:import-models --force
```

### Skip Relations

```bash
# Import models without discovering columns/relationships
php artisan export:import-models --skip-relations
```

### Deep Relationship Discovery

```bash
# Discover nested relationships (default 2 levels)
php artisan export:import-models --deep

# Discover up to 3 levels deep
php artisan export:import-models --deep --deep-level=3

# Maximum 5 levels (values outside 1-5 are clamped)
php artisan export:import-models --deep --deep-level=5

# Also create nested column paths during deep discovery
php artisan export:import-models --deep --deep-columns
```

### What Gets Discovered

**Columns:**
- All database table columns for each model

**Relationships:**
- HasOne, HasMany
- BelongsTo, BelongsToMany
- HasOneThrough, HasManyThrough
- MorphOne, MorphMany, MorphTo, MorphToMany

**Pivot Data (BelongsToMany):**
- Detects `withPivot()` columns
- Stores in `has_pivot` and `pivot_columns` fields

**Nested Paths (with --deep):**
- `posts` (level 1)
- `posts.comments` (level 2)
- `posts.comments.author` (level 3)

### Output Example

```
Scanning for models in: /app/app/Models
Using namespace: App\Models
Found 5 model(s)
Imported: User (App\Models\User)
Imported: Post (App\Models\Post)
...

Phase 1 complete: 5 models imported, 0 skipped

Phase 2: Adding columns for all models...
  -> User: Synced 8 columns
  ...
After Phase 2 - Columns: 25, Relations: 0

Phase 3: Adding relations for all models...
  -> User: Found 4 relations, synced 4
  ...
After Phase 3 - Columns: 25, Relations: 15

Import completed. Imported 5 models.
Database totals:
  - Total export_model_relations: 40
  - Columns (is_column=true): 25
  - Relations (is_column=false): 15

Debug log written to: /app/storage/logs/import-models-2026-01-01-12-00-00.log
```

---

## export:seed-functions

Seed built-in transformation functions.

```bash
php artisan export:seed-functions [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--force` | Update existing functions | false |

### Basic Usage

```bash
# Seed functions (skip existing)
php artisan export:seed-functions
```

### Force Update

```bash
# Update existing functions with latest definitions
php artisan export:seed-functions --force
```

### Functions Seeded

**Date/Time (4):**
- Format Date
- Format Date Human
- Format Timestamp
- Date Difference

**String (7):**
- Uppercase
- Lowercase
- Title Case
- Truncate
- Slug
- Replace
- Extract

**Number (4):**
- Format Number
- Format Currency
- Round
- Percentage

**Boolean (1):**
- Boolean Text

**Array/JSON (3):**
- JSON Extract
- Array Join
- Array Count

**Utility (4):**
- Default Value
- Concatenate
- Hash
- Mask

**Total: 23 functions**

### Output Example

```
Seeding transformation functions...
Created: Format Date
Created: Format Date Human
...
Skipped: Hash (already exists)
Created: Mask

Transformation functions seeding complete!
Created: 22, Updated: 0, Skipped: 1
```

The command then prints a table of every function in the database (name, callable, parameters, description).

---

## export:validate

Validates every layout's configuration without running any exports and without writing to the database.

```bash
php artisan export:validate              # all layouts
php artisan export:validate --layout=posts_report   # one layout, by name or id
```

Each layout with problems prints a table of severity, source, and message; the summary line counts layouts, errors, and warnings. The command exits non-zero when any error-severity problem exists, so it slots into CI and deploy pipelines. Warnings alone (a skipped static filter, a format without a `{value}` placeholder, a collection sort without `sort_column`) do not fail the run.

```
Layout: posts_report
+----------+-----------------------+------------------------------------------------------+
| Severity | Source                | Message                                              |
+----------+-----------------------+------------------------------------------------------+
| error    | column:Tag Total      | Aggregator 'summ' is not supported                   |
| error    | filter_definitions[0] | Path 'user.nmae' does not resolve on App\Models\Post |
| warning  | column:Created        | Format 'Created at' has no {value} placeholder       |
+----------+-----------------------+------------------------------------------------------+

3 layouts checked, 2 errors, 1 warnings
```

Messages come from the `laravel-exports::validation` lang namespace and can be overridden or translated; see the API reference.

## Common Workflows

### Initial Setup

```bash
# 1. Run migrations
php artisan migrate

# 2. Optional under lazy sync: pre-populate the catalog in one pass
php artisan export:import-models --deep

# 3. Seed transformation functions
php artisan export:seed-functions
```

### After Adding New Models

```bash
# Import only new models (existing are skipped)
php artisan export:import-models --deep
```

### After Modifying Models

```bash
# Force re-import to update relations
php artisan export:import-models --force --deep
```

### Deployment Script

```bash
#!/bin/bash

# Run migrations
php artisan migrate --force

# Update export models
php artisan export:import-models --force --deep

# Update transformation functions
php artisan export:seed-functions --force
```

---

## Troubleshooting

### Command Not Found

Ensure the service provider is registered:

```php
// config/app.php
'providers' => [
    HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider::class,
],
```

### Memory Issues

For large codebases, import one directory at a time:

```bash
php artisan export:import-models --path=app/Models/Billing --namespace=App\\Models\\Billing
php artisan export:import-models --path=app/Models/Crm --namespace=App\\Models\\Crm
```

Or skip the bulk import entirely and let lazy sync register models as layouts reference them.

### Missing Relations

If relations aren't being discovered:

1. Check relationship method return types
2. Ensure related models exist
3. Try running with `--force` flag
4. Check for circular references (use `--omit` if needed)

### Database Errors

If getting database errors:

```bash
# Check migrations have run
php artisan migrate:status
```
