# Pivot Data Export

Export pivot table attributes from BelongsToMany relationships.

## Scenario

Export users with their role assignments including when roles were assigned and when they expire.

## Models

```php
// User.php
class User extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_at', 'expires_at', 'assigned_by'])
            ->withTimestamps();
    }
}

// Role.php
class Role extends Model
{
    // name, permissions columns
}
```

## Database Schema

```sql
-- users table
CREATE TABLE users (
    id UUID PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255)
);

-- roles table
CREATE TABLE roles (
    id UUID PRIMARY KEY,
    name VARCHAR(255)
);

-- role_user pivot table
CREATE TABLE role_user (
    user_id UUID,
    role_id UUID,
    assigned_at TIMESTAMP,
    expires_at TIMESTAMP NULL,
    assigned_by UUID NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    PRIMARY KEY (user_id, role_id)
);
```

## Sample Data

```php
// users
$users = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
];

// roles
$roles = [
    ['id' => 1, 'name' => 'Admin'],
    ['id' => 2, 'name' => 'Editor'],
    ['id' => 3, 'name' => 'Viewer'],
];

// role_user (pivot)
$roleUser = [
    ['user_id' => 1, 'role_id' => 1, 'assigned_at' => '2024-01-15', 'expires_at' => '2025-01-15'],
    ['user_id' => 1, 'role_id' => 2, 'assigned_at' => '2024-02-01', 'expires_at' => null],
    ['user_id' => 2, 'role_id' => 2, 'assigned_at' => '2024-03-01', 'expires_at' => '2024-12-31'],
    ['user_id' => 2, 'role_id' => 3, 'assigned_at' => '2024-03-01', 'expires_at' => null],
];
```

## Setup

### 1. Import Models (Optional)

Lazy sync detects pivot columns on first reference; run the import to pre-populate them.

```bash
php artisan export:import-models --force
```

This auto-detects pivot columns:

```php
$rolesRelation = ExportModelRelation::where('relation', 'roles')->first();
// has_pivot: true
// pivot_columns: ['assigned_at', 'expires_at', 'assigned_by']
```

### 2. Create Layout

```php
use HexagonLabsLLC\LaravelExports\Models\{ExportModel, ExportLayout, ExportColumn, ExportFilter, ExportFunction, ExportModelRelation};

$userModel = ExportModel::where('title', 'User')->first();
$formatDate = ExportFunction::where('name', 'Format Date')->first();
$arrayJoin = ExportFunction::where('name', 'Array Join')->first();

$layout = ExportLayout::create([
    'export_model_id' => $userModel->id,
    'name' => 'user_roles_export',
    'title' => 'User Roles Export',
]);
```

### 3. Create Columns

```php
// User info
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

// Role count
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Role Count',
    'value_path' => 'roles',
    'aggregator' => 'count',
    'position' => 3,
]);

// All role names joined
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Roles',
    'value_path' => 'roles.name',
    'export_function_id' => $arrayJoin->id,
    'export_function_values' => [null, ', '],
    'position' => 4,
]);

// Primary role (first)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'title' => 'Primary Role',
    'value_path' => 'roles.name',
    'aggregator' => 'first',
    'position' => 5,
]);

// Primary role assigned date (pivot)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $rolesRelation->id,
    'title' => 'Role Assigned',
    'value_path' => 'roles.pivot.assigned_at',
    'aggregator' => 'first',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'M j, Y'],
    'position' => 6,
]);

// Primary role expiry (pivot)
ExportColumn::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $rolesRelation->id,
    'title' => 'Role Expires',
    'value_path' => 'roles.pivot.expires_at',
    'aggregator' => 'first',
    'export_function_id' => $formatDate->id,
    'export_function_values' => [null, 'M j, Y'],
    'default' => 'Never',
    'position' => 7,
]);
```

## Execute

```php
$service = new DynamicExportService();
return $service->downloadAs($layout, 'csv', 'user-roles.csv');
```

## Expected Output

```csv
Name,Email,Role Count,Roles,Primary Role,Role Assigned,Role Expires
John Doe,john@example.com,2,"Admin, Editor",Admin,"Jan 15, 2024","Jan 15, 2025"
Jane Smith,jane@example.com,2,"Editor, Viewer",Editor,"Mar 1, 2024","Dec 31, 2024"
```

## Pivot Access Syntax

Access pivot data using `.pivot.`:

```php
// Role name (regular)
'value_path' => 'roles.name',

// Pivot assigned_at
'value_path' => 'roles.pivot.assigned_at',

// Pivot expires_at
'value_path' => 'roles.pivot.expires_at',

// Pivot timestamps (if withTimestamps())
'value_path' => 'roles.pivot.created_at',
'value_path' => 'roles.pivot.updated_at',
```

## Filtering by Pivot Data

Export only users with unexpired roles:

```php
// The filter relation lives on the collection item's export model (Role)
// with an item-relative path - it is evaluated against each role
$roleModel = ExportModel::where('title', 'Role')->first();

$expiresRelation = ExportModelRelation::create([
    'export_model_id' => $roleModel->id,
    'relation' => 'pivot.expires_at',
    'title' => 'Role Expires At',
    'is_column' => true,
]);

// Filter for non-expired (null or future)
$activeFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $expiresRelation->id,
    'operator' => 'relation',
    'value' => null,  // Or use > with today's date
]);

// Column with filter
ExportColumn::create([
    'title' => 'Active Role',
    'value_path' => 'roles.name',
    'export_model_relation_id' => $rolesRelation->id,
    'export_filter_id' => $activeFilter->id,
    'aggregator' => 'first',
]);
```

## Project Members Example

```php
// Project BelongsToMany Users with pivot: role, joined_at, is_lead

$layout = ExportLayout::create([
    'export_model_id' => $projectModel->id,
    'name' => 'project_team_export',
    'title' => 'Project Team Export',
]);

// Project name
ExportColumn::create([
    'title' => 'Project',
    'value_path' => 'name',
    'position' => 1,
]);

// Team size
ExportColumn::create([
    'title' => 'Team Size',
    'value_path' => 'members',
    'aggregator' => 'count',
    'position' => 2,
]);

// Members collection relation on the Project model
$membersRelation = ExportModelRelation::where('export_model_id', $projectModel->id)
    ->where('relation', 'members')
    ->first();

// Item-relative pivot path on the member's export model (User)
$isLeadRelation = ExportModelRelation::create([
    'export_model_id' => $memberUserModel->id,
    'relation' => 'pivot.is_lead',
    'title' => 'Is Lead',
    'is_column' => true,
]);

// Filter for lead members
$isLeadFilter = ExportFilter::create([
    'export_layout_id' => $layout->id,
    'export_model_relation_id' => $isLeadRelation->id,
    'operator' => 'relation',
    'value' => true,
]);

// Lead name
ExportColumn::create([
    'title' => 'Lead',
    'value_path' => 'members.name',
    'export_model_relation_id' => $membersRelation->id,
    'export_filter_id' => $isLeadFilter->id,
    'aggregator' => 'first',
    'default' => 'No lead',
    'position' => 3,
]);

// Lead role from pivot
ExportColumn::create([
    'title' => 'Lead Role',
    'value_path' => 'members.pivot.role',
    'export_model_relation_id' => $membersRelation->id,
    'export_filter_id' => $isLeadFilter->id,
    'aggregator' => 'first',
    'position' => 4,
]);

// When lead joined
ExportColumn::create([
    'title' => 'Lead Since',
    'value_path' => 'members.pivot.joined_at',
    'export_model_relation_id' => $membersRelation->id,
    'export_filter_id' => $isLeadFilter->id,
    'aggregator' => 'first',
    'export_function_id' => $formatDate->id,
    'position' => 5,
]);
```

## Verify Pivot Detection

Check that pivot columns were detected:

```php
$relation = ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'roles')
    ->first();

dump($relation->has_pivot);       // true
dump($relation->pivot_columns);    // ['assigned_at', 'expires_at', 'assigned_by']
```

If not detected, re-run import:

```bash
php artisan export:import-models --force
```

## Notes

- Ensure model uses `withPivot()` for the columns you need
- Use `.pivot.` notation to access pivot data
- Always use aggregators with BelongsToMany (returns collection)
- Set defaults for nullable pivot columns
