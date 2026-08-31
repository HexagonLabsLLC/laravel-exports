# Commands Reference

Artisan commands provided by the package.

## export:import-models

Import and register Eloquent models for exporting.

```bash
php artisan export:import-models [options]
```

### Options

| Option | Description | Default |
|--------|-------------|---------|
| `--path` | Directory to scan (relative to `base_path()` or absolute) | `app/Models` |
| `--namespace` | Base namespace | `App\Models` |
| `--filter` | File pattern filter | `*` |
| `--omit` | Models to exclude from relation inspection | (none) |
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

### Filter Models

```bash
# Import only User-related models
php artisan export:import-models --filter=*User*

# Import models starting with Order
php artisan export:import-models --filter=Order*
```

### Exclude Models

```bash
# Omit specific models from relation inspection
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
Importing models from app/Models with namespace App\Models...

Found 5 model(s):
  [1/5] User
    - Created export model: User
    - Synced 8 columns, 4 relationships
  [2/5] Post
    - Created export model: Post
    - Synced 5 columns, 3 relationships
  ...

Import complete!
  Models imported: 5
  Total columns: 25
  Total relationships: 15
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

  [1/23] Format Date - created
  [2/23] Format Date Human - created
  [3/23] Format Timestamp - created
  [4/23] Date Difference - created
  ...
  [21/23] Concatenate - created
  [22/23] Hash - already exists (use --force to update)
  [23/23] Mask - created

Seeding complete!
  Created: 22
  Skipped: 1
```

---

## Common Workflows

### Initial Setup

```bash
# 1. Run migrations
php artisan migrate

# 2. Import all models with deep discovery
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

For large codebases, import in batches:

```bash
php artisan export:import-models --filter=User*
php artisan export:import-models --filter=Order*
php artisan export:import-models --filter=Product*
```

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
