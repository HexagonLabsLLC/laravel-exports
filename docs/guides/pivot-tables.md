# Pivot Tables

Export pivot attributes from BelongsToMany relationships.

## Overview

When you have a many-to-many relationship with pivot data:

```php
class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_at', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }
}
```

You can export the pivot attributes using the `.pivot.` notation:

```php
'value_path' => 'roles.pivot.assigned_at'
```

## Setup

### Model Configuration

Ensure your relationship includes `withPivot()`:

```php
// User.php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->withPivot(['assigned_at', 'expires_at']);
}

// Project.php
public function users(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot(['role', 'joined_at', 'is_lead']);
}
```

### Import Models

Pivot columns are detected whenever the model is synced, which under the default lazy
mode happens on first reference. To populate them up front, run the import:

```bash
php artisan export:import-models --force
```

The command automatically detects pivot columns and stores them:

```php
// Resulting ExportModelRelation:
[
    'relation' => 'roles',
    'has_pivot' => true,
    'pivot_columns' => ['assigned_at', 'expires_at'],
]
```

## Accessing Pivot Data

Use `.pivot.` in the value path:

```php
// Role name (regular access)
'value_path' => 'roles.name',

// Pivot data
'value_path' => 'roles.pivot.assigned_at',
'value_path' => 'roles.pivot.expires_at',
'value_path' => 'roles.pivot.created_at',  // If withTimestamps()
```

## Complete Example

### Setup

```php
// Database: users, roles, role_user (pivot)
// role_user columns: user_id, role_id, assigned_at, expires_at, created_at, updated_at

// User model
public function roles(): BelongsToMany
{
    return $this->belongsToMany(Role::class)
        ->withPivot(['assigned_at', 'expires_at'])
        ->withTimestamps();
}
```

### Create Export

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportModelRelation};

// Get models
$userModel = ExportModel::where('title', 'User')->first();

// Relation record for the roles collection (needed so pivot columns are eager loaded)
$rolesRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'roles')
    ->first();

// Create layout
$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'user_roles_export',
    'title' => 'User Roles Export',
]);

// User columns
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Name',
    'value_path' => 'name',
    'position' => 1,
]);

ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Email',
    'value_path' => 'email',
    'position' => 2,
]);

// Role name (first role)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Primary Role',
    'value_path' => 'roles.name',
    'aggregator' => 'first',
    'position' => 3,
]);

// Pivot: when role was assigned
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $rolesRelation->id,
    'title' => 'Role Assigned',
    'value_path' => 'roles.pivot.assigned_at',
    'aggregator' => 'first',
    'position' => 4,
]);

// Pivot: when role expires
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $rolesRelation->id,
    'title' => 'Role Expires',
    'value_path' => 'roles.pivot.expires_at',
    'aggregator' => 'first',
    'position' => 5,
]);
```

### Result

| Name | Email | Primary Role | Role Assigned | Role Expires |
|------|-------|--------------|---------------|--------------|
| John Doe | john@example.com | Admin | 2025-01-15 | 2026-01-15 |
| Jane Smith | jane@example.com | Editor | 2025-02-01 | null |

## Using Aggregators with Pivot

Since BelongsToMany returns a collection, use aggregators:

### First Item

```php
// Get the first role's assignment date
ExportColumn::create([
    'title' => 'First Role Assigned',
    'value_path' => 'roles.pivot.assigned_at',
    'export_model_relation_id' => $rolesRelation->id,
    'aggregator' => 'first',
]);
```

### Count

```php
// Count roles
ExportColumn::create([
    'title' => 'Role Count',
    'value_path' => 'roles',
    'aggregator' => 'count',
]);
```

### All Values Joined

```php
// Join all role names
ExportColumn::create([
    'title' => 'All Roles',
    'value_path' => 'roles.name',
    'export_function_id' => $arrayJoinFunction->id,
    'export_function_values' => [null, ', '],
]);
```

## Filtering Pivot Data

Column filters with regular operators (anything other than `relation`) constrain
the MAIN query - they limit which users appear in the export, not which role is
extracted from the collection:

```php
// Restrict the export to users with a role that has not expired yet
$notExpiredFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $expiresAtRelation->id,
    'operator' => '>',
    'value' => now()->toDateString(),
    'value_type' => 'string',
]);

// The column still extracts the FIRST role from the full collection
ExportColumn::create([
    'title' => 'Active Role',
    'value_path' => 'roles.name',
    'export_model_relation_id' => $rolesRelation->id,
    'export_filter_id' => $notExpiredFilter->id,
    'aggregator' => 'first',
]);
```

To extract only matching items from the collection itself, use a filter with the
`relation` operator instead. See the
[Collection Filtering example](../examples/advanced/collection-filtering.md).

## Pivot with Transformation

Apply functions to pivot data:

```php
$formatDate = ExportFunction::where('name', 'Format Date')->first();

ExportColumn::create([
    'title' => 'Assigned Date',
    'value_path' => 'roles.pivot.assigned_at',
    'export_model_relation_id' => $rolesRelation->id,
    'aggregator' => 'first',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'F j, Y'],
]);
```

## Project Members Example

Project has many Users through pivot with role:

```php
// Project model
public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'project_members')
        ->withPivot(['role', 'joined_at', 'is_lead']);
}
```

Then configure the export:

```php
// Relation record for the members collection
$membersRelation = ExportModelRelation::where('export_model_id', $projectModel->id)
    ->where('relation', 'members')
    ->first();

// Export columns
ExportColumn::create([
    'title' => 'Project Name',
    'value_path' => 'name',
    'position' => 1,
]);

ExportColumn::create([
    'title' => 'Team Size',
    'value_path' => 'members',
    'aggregator' => 'count',
    'position' => 2,
]);

ExportColumn::create([
    'title' => 'Lead Name',
    'value_path' => 'members.name',
    'export_model_relation_id' => $membersRelation->id,
    'export_filter_id' => $isLeadFilter->id,  // pivot.is_lead = true
    'aggregator' => 'first',
    'position' => 3,
]);

ExportColumn::create([
    'title' => 'Lead Since',
    'value_path' => 'members.pivot.joined_at',
    'export_model_relation_id' => $membersRelation->id,
    'export_filter_id' => $isLeadFilter->id,
    'aggregator' => 'first',
    'export_function_id' => $formatDate->id,
    'position' => 4,
]);
```

## Checking Pivot Configuration

Verify pivot columns are detected:

```php
$rolesRelation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'roles')
    ->first();

// Check pivot info
dump($rolesRelation->has_pivot);        // true
dump($rolesRelation->pivot_columns);     // ['assigned_at', 'expires_at']
```

## Troubleshooting

### Pivot Data Not Available

1. **Check withPivot()**: Ensure the relationship includes `withPivot()`
2. **Re-sync the model**: `php artisan export:import-models --force`, or set `schema_sync` to `verify` so a changed model re-syncs itself
3. **Verify column names**: Check `pivot_columns` matches your pivot table

### Empty Values

1. **Check aggregator**: Use `first` or `last` for single values
2. **Verify data exists**: Some records may not have pivot data
3. **Set default**: Provide a default value for null pivot data

### Wrong Values

1. **Check path syntax**: Use `.pivot.` not just `.`
2. **Verify column name**: Must match exactly
3. **Enable debug mode**: Check logs for extraction details

## Best Practices

1. **Always use withPivot()**: Specify which columns you need
2. **Use aggregators**: BelongsToMany returns collections
3. **Set defaults**: Pivot data is often nullable
4. **Re-import after changes**: Update relations when pivot changes
5. **Test with data**: Verify paths work before exporting

## Related Documentation

- [Creating Layouts](creating-layouts.md) - Column configuration
- [Aggregations](aggregations.md) - Working with collections
- [Pivot Data Export Example](../examples/advanced/pivot-data-export.md)
