# Troubleshooting

Common issues and solutions when working with Laravel Exports.

## Inspecting the Query

To see the query an export will run, use `getQuery()`:

```php
$query = $service->getQuery($layout, $requestData);
dd($query->toSql(), $query->getBindings());
```

Errors and warnings are still written to Laravel's default log channel:

```
storage/logs/laravel.log
```

## Common Errors

### Model Not Found

**Error:** `ExportModel not found for title: User`

**Cause:** Model hasn't been imported or title doesn't match.

**Solution:**

```bash
# Re-import models
php artisan export:import-models --force

# Check registered models
php artisan tinker
>>> ExportModel::pluck('title')->toArray()
```

### Relation Not Found

**Error:** `Relation [posts] not found on model [User]`

**Cause:** Relationship method doesn't exist or wasn't discovered.

**Solutions:**

1. Verify the relationship exists on the model:
```php
class User extends Model
{
    public function posts()  // Must exist
    {
        return $this->hasMany(Post::class);
    }
}
```

2. Re-import with relations:
```bash
php artisan export:import-models --force
```

3. Check the ExportModelRelation table:
```php
ExportModelRelation::where('export_model_id', $userModel->id)
    ->where('relation', 'posts')
    ->first();
```

### Nested Relation Invalid

**Error:** `Invalid nested path: workItem.workOrder.customer`

**Cause:** Intermediate relationship doesn't exist.

**Solutions:**

1. Verify each step exists:
```php
// WorkItem must have workOrder()
// WorkOrder must have customer()
```

2. Use deep discovery:
```bash
php artisan export:import-models --deep --deep-level=3
```

3. Check path validation:
```php
$inspector = app(ModelRelationInspector::class);
$valid = $inspector->validateNestedPath(
    WorkItem::class,
    'workOrder.customer'
);
```

### Column Value is Null

**Error:** Column returns null when data exists.

**Causes:**
- Incorrect value_path
- Relation not eager loaded
- Missing aggregator for collections

**Solutions:**

1. Check value_path:
```php
// Wrong: 'path' => 'user_name'
// Right: 'value_path' => 'user.name'
```

2. Verify eager loading by inspecting the built query:
```php
$query = $service->getQuery($layout, $requestData);
dd($query->getEagerLoads());
```

3. Add aggregator for collections:
```php
ExportColumn::create([
    'value_path' => 'posts.title',
    'aggregator' => 'first',  // Required for HasMany
]);
```

### Function Not Found

**Error:** `Function [Format Date] not found`

**Cause:** Functions haven't been seeded.

**Solution:**

```bash
php artisan export:seed-functions

# Or force update
php artisan export:seed-functions --force
```

### Filter Not Applied

**Error:** Filter exists but data isn't filtered.

**Causes:**
- Request filter missing value
- Wrong operator for data type
- Filter attached to wrong model

**Solutions:**

1. Check request-based filter:
```php
// Filter must receive value
$results = $service->executeExport($layout, [
    'status' => 'active',  // Key must match the filter's relation name (or a supported variant)
]);
```

2. Verify operator:
```php
// For arrays, use 'in' not '='
'operator' => 'in',
'value_type' => 'array',
```

3. Check filter's model relation:
```php
$filter = ExportFilter::find($filterId);
echo $filter->export_model_relation_id;  // Should match column's relation
```

### Memory Exhausted

**Error:** `Allowed memory size of X bytes exhausted`

**Cause:** Loading too many records at once.

**Solutions:**

1. Use chunked processing:
```php
$service->executeExportChunked($layout, [], 500, function ($chunk) {
    // Process chunk
});
```

2. Use streaming:
```php
return $service->streamAs($layout, 'csv', 'large.csv', [], [], 500);
```

3. Use background jobs:
```php
$exportId = $service->queueExport($layout, 'csv');
```

4. Increase memory limit (temporary):
```php
ini_set('memory_limit', '512M');
```

### Timeout Error

**Error:** `Maximum execution time exceeded`

**Cause:** Export taking too long.

**Solutions:**

1. Use background jobs for large exports
2. Reduce data with filters
3. Increase timeout for command-line:
```bash
php -d max_execution_time=300 artisan your:command
```

### Pivot Data Missing

**Error:** Pivot columns return null.

**Cause:** Pivot columns not configured in relationship.

**Solutions:**

1. Ensure model uses withPivot():
```php
public function roles()
{
    return $this->belongsToMany(Role::class)
        ->withPivot(['assigned_at', 'expires_at']);  // Required
}
```

2. Re-import to detect pivot columns:
```bash
php artisan export:import-models --force
```

3. Verify pivot was detected:
```php
$relation = ExportModelRelation::where('relation', 'roles')->first();
// $relation->has_pivot should be true
// $relation->pivot_columns should contain column names
```

### JSON Export Invalid

**Error:** Invalid JSON output or encoding errors.

**Cause:** Data contains invalid UTF-8 characters.

**Solution:** Clean the source data before exporting. The JSON handler supports these options: `pretty`, `unescaped_slashes`, `unescaped_unicode`, `wrap_data`, `include_meta`.

```php
$service->exportTo($layout, 'json', [], [
    'pretty' => true,
    'unescaped_unicode' => true,
]);
```

### CSV Delimiter Issues

**Error:** CSV columns not separated correctly.

**Cause:** Data contains delimiter character.

**Solutions:**

1. Use different delimiter:
```php
$service->exportTo($layout, 'csv', [], [
    'delimiter' => ';',
]);
```

2. Verify enclosure:
```php
$service->exportTo($layout, 'csv', [], [
    'enclosure' => '"',
    'escape' => '\\',
]);
```

## Performance Issues

### Slow Query

**Symptom:** Export takes very long to start.

**Diagnosis:**

```php
// Enable query logging
DB::enableQueryLog();
$results = $service->executeExport($layout);
dd(DB::getQueryLog());
```

**Solutions:**

1. Add database indexes:
```php
Schema::table('orders', function (Blueprint $table) {
    $table->index('status');
    $table->index('created_at');
});
```

2. Limit eager loading depth
3. Use specific filters to reduce dataset

### N+1 Query Problem

**Symptom:** Many similar queries in logs.

**Diagnosis:**
- Check for missing eager loading
- Look for queries inside loops

**Solution:**
The service automatically eager loads relations. If you see N+1:

```php
// Check that relation is in export_model_relations
ExportModelRelation::where('relation', 'posts')->first();
```

### Large Memory Usage

**Symptom:** Memory grows during export.

**Diagnosis:**

```php
// Add memory tracking
$start = memory_get_usage();
$results = $service->executeExport($layout);
$used = memory_get_usage() - $start;
logger("Memory used: " . number_format($used / 1024 / 1024, 2) . " MB");
```

**Solutions:**

1. Use streaming for large datasets
2. Process in smaller chunks

## Queue Issues

### Job Not Processing

**Symptom:** Queued export stays in "processing" forever.

**Solutions:**

1. Verify queue worker is running:
```bash
php artisan queue:work --queue=exports
```

2. Check failed jobs:
```bash
php artisan queue:failed
```

3. Retry failed job:
```bash
php artisan queue:retry [job-id]
```

### Status Not Updating

**Symptom:** `getStatus()` returns stale data.

**Cause:** Cache not clearing.

**Solution:**

```php
// Clear cache
Cache::forget("export_status:{$exportId}");

// Or check status TTL
// config/laravel-exports.php
'status_ttl' => 86400,  // 24 hours
```

### Export File Not Found

**Symptom:** Export completed but file missing.

**Solutions:**

1. Check storage configuration:
```php
// Verify disk exists
Storage::disk(config('laravel-exports.disk'))->exists($path);
```

2. Check file path:
```php
$path = ProcessExportJob::getFilePath($exportId);
Storage::disk('local')->path($path);
```

## Configuration Issues

### Service Provider Not Loaded

**Error:** `Class 'ExportModel' not found`

**Solution:**

```php
// config/app.php
'providers' => [
    HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider::class,
],
```

### Migrations Not Run

**Error:** `Table 'export_models' doesn't exist`

**Solution:**

```bash
php artisan migrate
```

### Config Not Published

**Error:** `config('laravel-exports.queue')` returns null

**Solution:**

```bash
php artisan vendor:publish \
    --provider="HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider"
```

## Getting Help

If you're still experiencing issues:

1. Inspect the built query with `getQuery()` and check the logs for errors and warnings
2. Check the [API Reference](reference/api.md) for correct method signatures
3. Review [examples](examples/) for working configurations
4. Report issues at the project repository

## Quick Checklist

When troubleshooting, verify:

- [ ] Models are imported (`export:import-models`)
- [ ] Functions are seeded (`export:seed-functions`)
- [ ] Migrations have run (`migrate:status`)
- [ ] value_path uses correct dot notation
- [ ] Aggregators are set for collection relations
- [ ] Request filters receive values
- [ ] Queue workers are running for background exports
