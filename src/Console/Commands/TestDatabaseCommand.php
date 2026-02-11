<?php

namespace HexagonLabsLLC\LaravelExports\Console\Commands;

use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestDatabaseCommand extends Command
{
    protected $signature = 'export:test-db';

    protected $description = 'Test database connectivity and relations';

    public function handle(): int
    {
        $this->info('Testing Laravel Exports Database...');

        // 1. Check table existence
        $this->line("\n1. Checking tables:");
        $tables = ['export_models', 'export_model_relations'];
        foreach ($tables as $table) {
            $exists = DB::getSchemaBuilder()->hasTable($table);
            $this->line("   - {$table}: ".($exists ? '✓ EXISTS' : '✗ MISSING'));
        }

        // 2. Count records
        $this->line("\n2. Current record counts:");
        try {
            $models = ExportModel::count();
            $relations = ExportModelRelation::count();
            $columns = ExportModelRelation::where('is_column', true)->count();
            $modelRelations = ExportModelRelation::where('is_column', false)->count();

            $this->line("   - Export Models: {$models}");
            $this->line("   - Total Relations: {$relations}");
            $this->line("   - Columns (is_column=true): {$columns}");
            $this->line("   - Relations (is_column=false): {$modelRelations}");
        } catch (\Exception $e) {
            $this->error('   Error counting: '.$e->getMessage());
        }

        // 3. Test creating a relation
        $this->line("\n3. Testing relation creation:");

        // Find first model
        $model = ExportModel::first();
        if (! $model) {
            $this->error('   No models found to test with!');

            return self::FAILURE;
        }

        $this->line("   Using model: {$model->title} (ID: {$model->id})");

        // Enable query log
        DB::enableQueryLog();

        try {
            // Try to create a test relation
            $testRelation = 'test_relation_'.time();
            $this->line("   Creating test relation: {$testRelation}");

            $relation = ExportModelRelation::create([
                'export_model_id' => $model->id,
                'relation' => $testRelation,
                'title' => 'Test Relation',
                'is_column' => false,
                'is_collection' => true,
                'related_model_id' => null,
            ]);

            $this->info("   ✓ Created with ID: {$relation->id}");

            // Verify it exists
            $exists = ExportModelRelation::find($relation->id);
            $this->line('   Verification: '.($exists ? '✓ Found in DB' : '✗ NOT FOUND'));

            // Check with raw query
            $raw = DB::table('export_model_relations')->where('id', $relation->id)->first();
            $this->line('   Raw query: '.($raw ? '✓ Found' : '✗ NOT FOUND'));

        } catch (\Exception $e) {
            $this->error('   ✗ Failed: '.$e->getMessage());
        }

        // 4. Show queries
        $this->line("\n4. SQL Queries executed:");
        $queries = DB::getQueryLog();
        foreach ($queries as $query) {
            $this->line('   '.$query['query']);
            if (! empty($query['bindings'])) {
                $this->line('      Bindings: '.json_encode($query['bindings']));
            }
        }

        // 5. Test unique constraint
        $this->line("\n5. Testing unique constraint:");
        try {
            // Try to create duplicate with same relation name
            $dup = ExportModelRelation::create([
                'export_model_id' => $model->id,
                'relation' => $testRelation,
                'title' => 'Duplicate Test',
                'is_column' => true, // Different is_column
                'is_collection' => false,
                'related_model_id' => null,
            ]);
            $this->error('   ✗ No unique constraint - duplicate created!');
        } catch (\Exception $e) {
            $this->info('   ✓ Unique constraint exists: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
