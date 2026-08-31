# Importing Models

The `export:import-models` command discovers and registers your Eloquent models for exporting.

## Basic Usage

```bash
php artisan export:import-models
```

This scans `app/Models` and registers all Eloquent models in the `export_models` table. It also discovers columns and relationships for each model.

## Command Options

### Path and Namespace

Scan a custom directory:

```bash
php artisan export:import-models \
    --path=app/Domain/Models \
    --namespace=App\\Domain\\Models
```

`--path` accepts either a path relative to `base_path()` or an absolute path, which is useful for models that live outside the Laravel app root (shared packages, symlinked directories, etc.):

```bash
php artisan export:import-models \
    --path=/srv/shared/packages/billing/src/Models \
    --namespace=Company\\Billing\\Models
```

### Filter Models

Import only specific models:

```bash
# Import only User-related models
php artisan export:import-models --filter=*User*

# Import models starting with "Order"
php artisan export:import-models --filter=Order*
```

### Omit Models

Exclude specific models from relation inspection:

```bash
php artisan export:import-models --omit=User,Post,Comment
```

This is useful when certain models cause issues during inspection.

### Force Re-Import

Update existing models and their relations:

```bash
php artisan export:import-models --force
```

Without `--force`, existing models are skipped.

### Skip Relations

Import models without discovering columns and relationships:

```bash
php artisan export:import-models --skip-relations
```

## Deep Relationship Discovery

The `--deep` option discovers nested relationships using dot notation.

### Basic Deep Discovery

```bash
php artisan export:import-models --deep
```

This discovers:
- Direct relationships: `posts`, `comments`
- Nested relationships: `posts.comments`, `posts.author`

### Control Nesting Depth

```bash
# Discover up to 3 levels deep
php artisan export:import-models --deep --deep-level=3
```

**Example levels:**
- Level 1: `posts`
- Level 2: `posts.comments`
- Level 3: `posts.comments.author`
- Level 4: `posts.comments.author.profile`
- Level 5: `posts.comments.author.profile.company`

Maximum depth is 5.

### Deep Discovery Example

Given these models:

```php
// User.php
public function posts() { return $this->hasMany(Post::class); }
public function profile() { return $this->hasOne(Profile::class); }

// Post.php
public function comments() { return $this->hasMany(Comment::class); }
public function author() { return $this->belongsTo(User::class); }

// Comment.php
public function author() { return $this->belongsTo(User::class); }
```

Running with `--deep --deep-level=3` creates these relations for User:

| Relation | Related Model | Type |
|----------|---------------|------|
| `posts` | Post | Collection |
| `posts.comments` | Comment | Collection |
| `posts.comments.author` | User | Single |
| `posts.author` | User | Single |
| `profile` | Profile | Single |

## What Gets Discovered

### Columns

Table columns are discovered using database reflection:

```php
// For users table with: id, name, email, created_at, updated_at

// Creates ExportModelRelation records:
// - id (column)
// - name (column)
// - email (column)
// - created_at (column)
// - updated_at (column)
```

### Relationships

Eloquent relationships are discovered using reflection:

```php
// For User with: posts(), profile(), roles()

// Creates ExportModelRelation records:
// - posts (hasMany, collection)
// - profile (hasOne, single)
// - roles (belongsToMany, collection with pivot)
```

### Pivot Tables

For BelongsToMany relationships, pivot columns are auto-detected:

```php
// User.php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->withPivot(['assigned_at', 'expires_at']);
}

// Creates ExportModelRelation:
// relation: 'roles'
// has_pivot: true
// pivot_columns: ['assigned_at', 'expires_at']
```

## Programmatic Import

You can also register a model programmatically:

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModel;

// Register a model manually
$exportModel = ExportModel::create([
    'title' => 'User',
    'model' => 'App\Models\User',
]);
```

To discover its columns and relationships, run the import command afterward:

```bash
php artisan export:import-models --force
```

## Accessing Registered Models

### List All Models

```php
use HexagonLabsLLC\LaravelExports\Models\ExportModel;

$models = ExportModel::all();

foreach ($models as $model) {
    echo "{$model->title}: {$model->model}\n";
}
```

### Get Model with Relations

```php
$userModel = ExportModel::where('title', 'User')
    ->with('relations')
    ->first();

foreach ($userModel->relations as $relation) {
    echo "{$relation->relation}: ";
    echo $relation->is_column ? 'column' : 'relationship';
    echo $relation->is_collection ? ' (collection)' : '';
    echo "\n";
}
```

### Access the Eloquent Model Instance

```php
$exportModel = ExportModel::where('title', 'User')->first();

// Get the actual Eloquent model instance
$eloquentModel = $exportModel->instance;

// Now you can use it
$users = $eloquentModel::all();
```

## Finding Relations

### By Name

```php
$emailRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'email')
    ->first();
```

### Nested Relations

Use the `whereNested` scope for nested paths:

```php
// Find the posts.comments relation for User
$relation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->whereNested('posts.comments')
    ->first();
```

The `whereNested` scope traverses the relationship chain to find the correct relation.

### All Relations for a Model

```php
$relations = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('is_column', false)  // Only relationships, not columns
    ->get();
```

### All Columns for a Model

```php
$columns = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('is_column', true)
    ->get();
```

## Re-running Import

### When to Re-run

Re-run the import command when:

1. You add new models to your application
2. You add new relationships to existing models
3. You add new columns to your database tables
4. You install the package in a new environment

### Safe Re-import

Without `--force`, existing data is preserved:

```bash
php artisan export:import-models
# Only adds new models and relations
```

### Full Re-import

With `--force`, existing data is updated:

```bash
php artisan export:import-models --force
# Updates all models and their relations
```

## Troubleshooting

### Model Not Found

If a model isn't being imported:

1. Check the path and namespace options
2. Ensure the class extends `Illuminate\Database\Eloquent\Model`
3. Check for syntax errors in the model file

### Relations Not Discovered

If relations aren't being discovered:

1. Ensure relationship methods have proper return type hints
2. Check that related models exist and are importable
3. Try running with `--force` to refresh

### Circular References

The system handles circular references automatically. For deeply nested models that reference each other, use `--omit` if needed:

```bash
php artisan export:import-models --deep --omit=AuditLog
```

### Memory Issues

For large applications with many models:

```bash
# Import in batches
php artisan export:import-models --filter=User*
php artisan export:import-models --filter=Order*
php artisan export:import-models --filter=Product*
```

## Best Practices

1. **Run on Deploy**: Include the import command in your deployment script
2. **Use Deep Discovery**: Enable `--deep` to get nested relationship support
3. **Control Depth**: Start with `--deep-level=2` and increase if needed
4. **Re-run After Schema Changes**: Keep relations in sync with your models
5. **Use Omit Sparingly**: Only exclude models that cause issues

## Related Documentation

- [Creating Layouts](creating-layouts.md) - Use imported models to create exports
- [Nested Relationships](nested-relationships.md) - Using dot notation in exports
- [Database Schema](../concepts/database-schema.md) - How models are stored
