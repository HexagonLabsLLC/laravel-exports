<?php

namespace HexagonLabsLLC\LaravelExports\Console\Commands;

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Services\SchemaSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:import-models 
                            {--path=app/Models : Directory to scan for models}
                            {--namespace=App\\Models : Base namespace for models}
                            {--filter=* : Pattern to filter model files}
                            {--omit=* : Models to omit from relation inspection}
                            {--force : Force re-import existing models}
                            {--skip-relations : Skip syncing model columns and relations}
                            {--deep : Discover nested relationships with dot notation (e.g., user.posts.comments)}
                            {--deep-level=2 : Maximum depth for nested relationship discovery}
                            {--deep-columns : Also create nested column paths (warning: can create many records)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-discover and register Eloquent models for export';

    protected ?ModelRelationInspector $inspector = null;

    protected string $logFile;

    public function handle(): int
    {
        // Initialize log file
        $this->logFile = storage_path('logs/import-models-'.date('Y-m-d-H-i-s').'.log');
        File::put($this->logFile, "Import Models Debug Log\n".str_repeat('=', 50)."\n\n");
        $this->log('Starting import at '.date('Y-m-d H:i:s'));
        $this->log('Transaction level at start: '.DB::transactionLevel());

        $rawPath = $this->option('path');
        $path = str_starts_with($rawPath, DIRECTORY_SEPARATOR) ? $rawPath : base_path($rawPath);
        $namespace = $this->option('namespace');
        $filter = $this->option('filter');
        $omit = $this->option('omit');

        $this->log('Options:');
        $this->log("  Path: $path");
        $this->log("  Namespace: $namespace");
        $this->log('  Filter: '.json_encode($filter));
        $this->log('  Omit: '.json_encode($omit));

        // Handle array input for filter option
        if (is_array($filter)) {
            $filter = $filter[0] ?? '*';
        }

        // Handle array input for omit option
        if (is_array($omit)) {
            $omit = $omit[0] ?? '';
        }

        $force = $this->option('force');
        $skipRelations = $this->option('skip-relations');

        $this->log('  Force: '.($force ? 'true' : 'false'));
        $this->log('  Skip Relations: '.($skipRelations ? 'true' : 'false'));

        // Create inspector with omit list and deep nesting option
        $omitList = $omit ? explode(',', $omit) : [];
        $deepLevel = min(5, max(1, (int)$this->option('deep-level')));
        $this->log('Transaction level before ModelRelationInspector: '.DB::transactionLevel());
        $this->inspector = new ModelRelationInspector($omitList, $path, $namespace, $this->option('deep'), $deepLevel);
        $this->log('Transaction level after ModelRelationInspector: '.DB::transactionLevel());
        $this->log("\nCreated ModelRelationInspector with omit list: ".json_encode($omitList));
        $this->log('Deep nesting enabled: '.($this->option('deep') ? 'YES' : 'NO'));
        $this->log('Max nesting depth: '.$deepLevel);

        if (!File::isDirectory($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $this->info("Scanning for models in: {$path}");
        $this->info("Using namespace: {$namespace}");

        $models = $this->inspector->getModels();

        if ($filter && $filter !== '*') {
            $models = array_filter($models, fn ($modelClass) => fnmatch($filter, class_basename($modelClass)));
        }

        if (empty($models)) {
            $this->warn('No models found matching criteria.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($models).' model(s)');

        $imported = 0;
        $skipped = 0;

        // Phase 1: Import all models first
        foreach ($models as $modelClass) {
            $modelName = class_basename($modelClass);

            if ($this->importModel($modelClass, $modelName, $force)) {
                $imported++;
                $this->line("Imported: {$modelName} ({$modelClass})");
            } else {
                $skipped++;
                $this->line("- Skipped: {$modelName} (already exists)");
            }
        }

        $this->newLine();
        $this->info("Phase 1 complete: {$imported} models imported, {$skipped} skipped");

        if (!$skipRelations) {
            // Phase 2: Add columns for all models
            $this->newLine();
            $this->info('Phase 2: Adding columns for all models...');
            foreach ($models as $modelClass) {
                $this->syncModelColumns($modelClass);
            }

            // Count after Phase 2
            $phase2Columns = ExportModelRelation::where('is_column', true)->count();
            $phase2Relations = ExportModelRelation::where('is_column', false)->count();
            $this->log("\nAfter Phase 2 - Columns: {$phase2Columns}, Relations: {$phase2Relations}");
            $this->info("After Phase 2 - Columns: {$phase2Columns}, Relations: {$phase2Relations}");

            // Phase 3: Add relations for all models (with immediate linking)
            $this->newLine();
            $this->info('Phase 3: Adding relations for all models...');
            foreach ($models as $modelClass) {
                $this->syncModelRelations($modelClass);
            }

            // Count after Phase 3
            $phase3Columns = ExportModelRelation::where('is_column', true)->count();
            $phase3Relations = ExportModelRelation::where('is_column', false)->count();
            $this->log("\nAfter Phase 3 - Columns: {$phase3Columns}, Relations: {$phase3Relations}");
            $this->info("After Phase 3 - Columns: {$phase3Columns}, Relations: {$phase3Relations}");

            // Phase 4: Deep relationship discovery (if enabled)
            if ($this->option('deep')) {
                $deepLevel = min(5, max(1, (int)$this->option('deep-level')));
                $this->newLine();
                $this->info("Phase 4: Discovering nested relationships (depth: {$deepLevel})...");

                $totalDiscovered = 0;
                foreach ($models as $modelClass) {
                    $discovered = $this->syncNestedRelations($modelClass, $deepLevel);
                    $totalDiscovered += $discovered;
                }

                // Count after Phase 4
                $phase4Columns = ExportModelRelation::where('is_column', true)->count();
                $phase4Relations = ExportModelRelation::where('is_column', false)->count();
                $this->log("\nAfter Phase 4 - Columns: {$phase4Columns}, Relations: {$phase4Relations}");
                $this->info("After Phase 4 - Columns: {$phase4Columns}, Relations: {$phase4Relations}");
                $this->info("Discovered {$totalDiscovered} nested relationships");
            }
        }

        // Count total relations in database
        $this->log("\nPerforming final database count...");
        try {
            $instance = new ExportModelRelation;
            $connection = $instance->getConnectionName() ?: 'default';
            $this->log('Database connection: '.$connection);
            $this->log('Table name: '.$instance->getTable());
            $this->log('Transaction level: '.DB::transactionLevel());
        } catch (\Exception $e) {
            $this->log('Error getting database info: '.$e->getMessage());
        }

        try {
            // First check if the table exists
            $tableExists = DB::getSchemaBuilder()->hasTable('export_model_relations');
            $this->log("Table 'export_model_relations' exists: ".($tableExists ? 'YES' : 'NO'));

            if (!$tableExists) {
                $this->log("ERROR: Table 'export_model_relations' does not exist!");
                $this->log('Available tables: '.implode(', ', DB::getSchemaBuilder()->getTableListing()));
                $totalRelations = 0;
                $totalColumns = 0;
                $totalModelRelations = 0;
            } else {
                $totalRelations = ExportModelRelation::count();
                $totalColumns = ExportModelRelation::where('is_column', true)->count();
                $totalModelRelations = ExportModelRelation::where('is_column', false)->count();

                // Double-check with raw query
                $rawCount = DB::table('export_model_relations')->count();
                $this->log("Raw DB count: $rawCount");
            }
        } catch (\Exception $e) {
            $this->log('Error counting records: '.$e->getMessage());
            $this->log('Stack trace: '.$e->getTraceAsString());
            $totalRelations = 'ERROR';
            $totalColumns = 'ERROR';
            $totalModelRelations = 'ERROR';
        }

        $this->log("\nImport completed at ".date('Y-m-d H:i:s'));
        $this->log('Database totals:');
        $this->log("  - Total export_model_relations: {$totalRelations}");
        $this->log("  - Columns (is_column=true): {$totalColumns}");
        $this->log("  - Relations (is_column=false): {$totalModelRelations}");

        $this->newLine();
        $this->info("Import completed. Imported {$imported} models.");
        $this->info('Database totals:');
        $this->info("  - Total export_model_relations: {$totalRelations}");
        $this->info("  - Columns (is_column=true): {$totalColumns}");
        $this->info("  - Relations (is_column=false): {$totalModelRelations}");

        // Auto-commit any transactions to persist data
        while (DB::transactionLevel() > 0) {
            DB::commit();
        }

        $this->newLine();
        $this->info("Debug log written to: {$this->logFile}");

        return self::SUCCESS;
    }

    /**
     * Get the fully qualified class name from a file path.
     */
    protected function getClassNameFromFile(string $file, string $basePath, string $baseNamespace): ?string
    {
        $relativePath = str_replace($basePath.'/', '', $file);
        $relativePath = str_replace('/', '\\', $relativePath);
        $className = $baseNamespace.'\\'.str_replace('.php', '', $relativePath);

        return $className;
    }

    /**
     * Import a model into the export_models table.
     */
    protected function importModel(string $modelClass, string $modelName, bool $force): bool
    {
        $existing = ExportModel::where('model', $modelClass)->first();

        if ($existing) {
            if (!$force) {
                return false;
            }

            $existing->update(['title' => Str::headline($modelName)]);

            return true;
        }

        ExportModel::create([
            'title' => Str::headline($modelName),
            'model' => $modelClass,
        ]);

        return true;
    }

    /**
     * Sync model columns for the given model class.
     */
    protected function syncModelColumns(string $modelClass): void
    {
        $this->log("\n--- Syncing columns for: $modelClass ---");

        try {
            $exportModel = app(SchemaSync::class)->syncModel($modelClass);
        } catch (\Exception $e) {
            $this->log('ERROR syncing columns: '.$e->getMessage());
            $this->error("Failed to sync {$modelClass}: ".$e->getMessage());

            return;
        }

        $count = $exportModel->relations()->where('is_column', true)->count();
        $this->line('  -> '.class_basename($modelClass).': Synced '.$count.' columns');
    }

    /**
     * Sync model relations for the given model class.
     */
    protected function syncModelRelations(string $modelClass): void
    {
        $this->log("\n--- Syncing relations for: $modelClass ---");

        try {
            $exportModel = app(SchemaSync::class)->syncModel($modelClass);
        } catch (\Exception $e) {
            $this->log('ERROR syncing relations: '.$e->getMessage());

            return;
        }

        $count = $exportModel->relations()->where('is_column', false)->count();
        $this->line('  -> '.class_basename($modelClass).': Found '.$count.' relations, synced '.$count);
    }

    /**
     * Log a message to the debug file
     */
    protected function log($message): void
    {
        if ($this->logFile) {
            File::append($this->logFile, '['.date('H:i:s').'] '.$message."\n");
        }
    }

    /**
     * Sync nested relations using ModelRelationInspector's deep discovery
     */
    protected function syncNestedRelations(string $modelClass, int $maxDepth): int
    {
        $this->log("\n--- Syncing nested relations for: $modelClass (max depth: $maxDepth) ---");

        $exportModel = ExportModel::where('model', $modelClass)->first();
        if (!$exportModel) {
            $this->log("ERROR: Export model not found for $modelClass");

            return 0;
        }

        // Get nested relation paths from inspector
        $nestedPaths = $this->inspector->getNestedRelationPaths($modelClass, $maxDepth);
        $this->log('Found '.count($nestedPaths).' nested paths');

        $created = 0;
        $skipped = 0;
        $processedCount = 0;
        $totalPaths = count($nestedPaths);

        // Skip creating columns for nested paths by default to reduce volume
        $createNestedColumns = $this->option('deep-columns') ?? false;

        foreach ($nestedPaths as $path => $pathInfo) {
            // Skip single-level paths as they're already handled in Phase 3
            if (substr_count($path, '.') === 0) {
                continue;
            }

            $processedCount++;

            // Show progress for large datasets
            if ($processedCount % 100 === 0) {
                $this->info("    Progress: {$processedCount}/{$totalPaths} paths processed...");
            }

            $this->log("\nProcessing nested path: $path");

            // Check if this nested relation already exists
            $exists = ExportModelRelation::where('export_model_id', $exportModel->id)
                ->where('relation', $path)
                ->exists();

            if (!$exists) {
                try {
                    // Find the related export model
                    $relatedExportModel = ExportModel::where('model', $pathInfo['final_model'])->first();

                    // Create the nested relation
                    $relation = ExportModelRelation::create([
                        'export_model_id' => $exportModel->id,
                        'relation' => $path,
                        'title' => $this->generateNestedTitle($path),
                        'is_column' => false,
                        'is_collection' => $pathInfo['is_collection'],
                        'related_model_id' => $relatedExportModel?->id,
                    ]);

                    $created++;
                    $this->log("SUCCESS: Created nested relation: $path");
                } catch (\Exception $e) {
                    $this->log("ERROR creating nested relation $path: ".$e->getMessage());
                }
            } else {
                $skipped++;
                $this->log("SKIP: Nested relation already exists: $path");
            }

            // Only create nested column paths if explicitly requested
            if ($createNestedColumns && isset($pathInfo['final_columns']) && is_array($pathInfo['final_columns'])) {
                // Limit columns to prevent explosion
                $columnLimit = 10;
                $columnsToProcess = array_slice($pathInfo['final_columns'], 0, $columnLimit);

                foreach ($columnsToProcess as $column) {
                    $columnPath = "{$path}.{$column}";

                    $columnExists = ExportModelRelation::where('export_model_id', $exportModel->id)
                        ->where('relation', $columnPath)
                        ->exists();

                    if (!$columnExists) {
                        try {
                            ExportModelRelation::create([
                                'export_model_id' => $exportModel->id,
                                'relation' => $columnPath,
                                'title' => $this->generateNestedTitle($columnPath),
                                'is_column' => true,
                                'is_collection' => false,
                                'related_model_id' => null,
                            ]);

                            $created++;
                            $this->log("SUCCESS: Created nested column: $columnPath");
                        } catch (\Exception $e) {
                            $this->log("ERROR creating nested column $columnPath: ".$e->getMessage());
                        }
                    }
                }
            }
        }

        $this->line('  -> '.class_basename($modelClass).': Created '.$created.' nested relations/columns, skipped '.$skipped);

        return $created;
    }

    /**
     * Generate a human-readable title for nested relations
     */
    protected function generateNestedTitle(string $path): string
    {
        $segments = explode('.', $path);
        $titles = array_map(function ($segment) {
            return Str::headline($segment);
        }, $segments);

        return implode(' > ', $titles);
    }
}
