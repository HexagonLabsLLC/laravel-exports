<?php

namespace HexagonLabsLLC\LaravelExports\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ReflectionClass;

class ModelRelationInspector
{
    /**
     * @param  string[]  $omitModels  Models to omit from inspection, e.g. ['SomeModel']
     * @param  string  $modelsPath  Models directory path
     * @param  string  $modelsNamespace  Models namespace
     */
    public function __construct(
        protected ?array $omitModels = null,
        protected ?string $modelsPath = null,
        protected ?string $modelsNamespace = null,
        protected ?bool $deepNesting = false,
        protected ?int $maxNestingDepth = 3
    ) {
        $this->omitModels = $omitModels ?? [];
        $this->modelsPath = $modelsPath ?? app_path('Models');
        $this->modelsNamespace = $modelsNamespace ?? 'App\\Models';
    }

    /**
     * @return string[] Fully qualified model class names
     */
    public function getModels(): array
    {
        $files = File::allFiles($this->modelsPath);
        $models = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getRelativePathname(), 0, -4);
            $class = $this->modelsNamespace.'\\'.strtr($relative, ['/' => '\\']);

            if (in_array(strtr($relative, ['/' => '\\']), $this->omitModels, true)) {
                continue;
            }

            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    /**
     * Get detailed relations for a given model:
     *
     *  [
     *    'contacts' => [
     *       'type'          => 'HasMany',
     *       'related_model' => 'App\Models\Contact',
     *       'is_collection' => true,
     *    ],
     *    // …
     *  ]
     *
     * @return array<string, array{type:string,related_model:string,is_collection:bool}>
     */
    public function getModelRelations(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        $model = new $modelClass;
        $reflection = new ReflectionClass($modelClass);
        $relations = [];

        foreach ($reflection->getMethods() as $method) {
            if (
                $method->class !== $modelClass ||
                ! $method->isPublic() ||
                $method->getNumberOfParameters() > 0 ||
                $method->getName() === '__construct'
            ) {
                continue;
            }
            // invoke it “silently”, so no warnings/DEPRECATEDs leak out
            $return = $this->invokeSilently($model, $method);

            if (! $return instanceof Relation) {
                continue;
            }

            $relationType = (new ReflectionClass($return))->getShortName();
            $relatedModel = get_class($return->getRelated());
            $isCollection = in_array($relationType, [
                'HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany',
            ], true);

            if (in_array(
                substr($relatedModel, strrpos($relatedModel, '\\') + 1),
                $this->omitModels,
                true
            )) {
                continue;
            }

            $relations[$method->getName()] = [
                'type' => $relationType,
                'related_model' => $relatedModel,
                'is_collection' => $isCollection,
            ];
        }

        return $relations;
    }

    /**
     * Invoke a no‑arg method on a model while suppressing
     * warnings, notices, and deprecated messages.
     */
    protected function invokeSilently(object $model, \ReflectionMethod $method)
    {
        // Temporarily suppress E_WARNING, E_NOTICE, E_DEPRECATED
        $oldReporting = error_reporting();
        error_reporting($oldReporting & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

        // Track transaction level before method call
        $transactionsBefore = DB::transactionLevel();

        try {
            $result = $method->invoke($model);
            
            // If the method started a transaction, roll it back
            $transactionsAfter = DB::transactionLevel();
            if ($transactionsAfter > $transactionsBefore) {
                for ($i = $transactionsAfter; $i > $transactionsBefore; $i--) {
                    DB::rollBack();
                }
            }
            
        } catch (\Throwable) {
            // Roll back any transactions that might have been started
            $transactionsAfter = DB::transactionLevel();
            if ($transactionsAfter > $transactionsBefore) {
                for ($i = $transactionsAfter; $i > $transactionsBefore; $i--) {
                    DB::rollBack();
                }
            }
            $result = null;
        } finally {
            // restore original error level
            error_reporting($oldReporting);
        }

        return $result;
    }

    /**
     * Walk a dot‑notation path (e.g. "workOrder.contact") and return
     * the final relation’s details in the same format as getModelRelations().
     *
     * @throws \RuntimeException if any segment is not a Relation
     */
    public function resolveRelationPath(string $modelClass, string $path): array
    {
        $segments = explode('.', $path);
        $currentClass = $modelClass;
        $relation = null;

        foreach ($segments as $segment) {
            if (! method_exists($currentClass, $segment)) {
                throw new \RuntimeException("Method {$segment} not found on {$currentClass}");
            }

            $model = new $currentClass;
            $result = $model->$segment();

            if (! $result instanceof Relation) {
                throw new \RuntimeException("{$segment} on {$currentClass} is not an Eloquent relation");
            }

            $relation = $result;
            $currentClass = get_class($relation->getRelated());
        }

        // now relation holds the last Relation object
        $relationType = (new ReflectionClass($relation))->getShortName();
        $relatedModel = get_class($relation->getRelated());
        $isCollection = in_array($relationType, [
            'HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany',
        ], true);

        return [
            'type' => $relationType,
            'related_model' => $relatedModel,
            'is_collection' => $isCollection,
        ];
    }

    /**
     * Validate a nested relation path (e.g. "workItem.workOrder.customer.contact")
     * Returns validation result with details about each segment
     *
     * @return array{
     *     valid: bool,
     *     path: string,
     *     segments: array<array{
     *         name: string,
     *         model: string,
     *         type: string,
     *         is_collection: bool
     *     }>,
     *     final_model: ?string,
     *     final_columns: ?string[],
     *     error: ?string
     * }
     */
    public function validateNestedPath(string $modelClass, string $path): array
    {
        $segments = explode('.', $path);
        $currentClass = $modelClass;
        $segmentDetails = [];
        
        try {
            foreach ($segments as $index => $segment) {
                if (! method_exists($currentClass, $segment)) {
                    return [
                        'valid' => false,
                        'path' => $path,
                        'segments' => $segmentDetails,
                        'final_model' => null,
                        'final_columns' => null,
                        'error' => "Method '{$segment}' not found on {$currentClass} at segment " . ($index + 1),
                    ];
                }

                $model = new $currentClass;
                
                // Track transaction level and rollback if needed
                $transactionsBefore = DB::transactionLevel();
                
                try {
                    $result = $model->$segment();
                    
                    // Rollback any transactions started
                    while (DB::transactionLevel() > $transactionsBefore) {
                        DB::rollBack();
                    }
                } catch (\Throwable $e) {
                    // Rollback any transactions
                    while (DB::transactionLevel() > $transactionsBefore) {
                        DB::rollBack();
                    }
                    
                    return [
                        'valid' => false,
                        'path' => $path,
                        'segments' => $segmentDetails,
                        'final_model' => null,
                        'final_columns' => null,
                        'error' => "Error invoking '{$segment}' on {$currentClass}: " . $e->getMessage(),
                    ];
                }

                if (! $result instanceof Relation) {
                    return [
                        'valid' => false,
                        'path' => $path,
                        'segments' => $segmentDetails,
                        'final_model' => null,
                        'final_columns' => null,
                        'error' => "'{$segment}' on {$currentClass} is not an Eloquent relation",
                    ];
                }

                $relationType = (new ReflectionClass($result))->getShortName();
                $relatedModel = get_class($result->getRelated());
                $isCollection = in_array($relationType, [
                    'HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany',
                ], true);

                $segmentDetails[] = [
                    'name' => $segment,
                    'model' => $currentClass,
                    'type' => $relationType,
                    'is_collection' => $isCollection,
                ];

                $currentClass = $relatedModel;
            }

            // Get columns of final model
            $finalColumns = $this->getModelColumns($currentClass);

            return [
                'valid' => true,
                'path' => $path,
                'segments' => $segmentDetails,
                'final_model' => $currentClass,
                'final_columns' => $finalColumns,
                'error' => null,
            ];
            
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'path' => $path,
                'segments' => $segmentDetails,
                'final_model' => null,
                'final_columns' => null,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return string[] Column names for the model's table
     */
    public function getModelColumns(string $modelClass): array
    {
        /** @var Model $model */
        $model = new $modelClass;
        $columns = $model
            ->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($model->getTable());

        return $columns;
    }

    /**
     * Get both columns and relations for a single model
     *
     * @return array{
     *     columns: string[],
     *     relations: array<string, array{type:string,related_model:string,is_collection:bool}>
     * }
     */
    public function getModelData(string $modelClass): array
    {
        if ($this->deepNesting) {
            return [
                'columns' => $this->getModelColumns($modelClass),
                'relations' => $this->getModelRelations($modelClass),
                'nested_paths' => $this->getNestedRelationPaths($modelClass, $this->maxNestingDepth),
            ];
        }

        return [
            'columns' => $this->getModelColumns($modelClass),
            'relations' => $this->getModelRelations($modelClass),
        ];
    }
  
    public function getNestedRelationPaths(string $modelClass, int $maxDepth = 3): array
    {
        $paths = [];
        $visited = []; // Prevent circular references
        
        $this->discoverNestedPaths($modelClass, '', 1, $maxDepth, $paths, $visited);
        
        return $paths;
    }
  
    protected function discoverNestedPaths(
        string $modelClass,
        string $currentPath,
        int $currentDepth,
        int $maxDepth,
        array &$paths,
        array &$visited
    ): void {
        // Prevent infinite recursion
        if (in_array($modelClass, $visited) || $currentDepth > $maxDepth) {
            return;
        }

        $visited[] = $modelClass;
        $relations = $this->getModelRelations($modelClass);

        foreach ($relations as $relationName => $relationInfo) {
            $relatedModel = $relationInfo['related_model'];
            $newPath = $currentPath ? "{$currentPath}.{$relationName}" : $relationName;

            // Skip if related model is in omit list
            $modelBaseName = substr($relatedModel, strrpos($relatedModel, '\\') + 1);
            if (in_array($modelBaseName, $this->omitModels)) {
                continue;
            }

            // Skip if this would create a circular path (model already in current path)
            if ($this->wouldCreateCircularPath($newPath, $relatedModel, $paths)) {
                continue;
            }

            // Add this path (but don't fetch columns yet to save memory/time)
            $paths[$newPath] = [
                'path' => $newPath,
                'depth' => $currentDepth,
                'final_model' => $relatedModel,
                'final_columns' => [], // Lazy load columns only when needed
                'is_collection' => $relationInfo['is_collection'],
            ];

            // Continue deeper if we haven't reached max depth
            if ($currentDepth < $maxDepth && class_exists($relatedModel)) {
                $this->discoverNestedPaths(
                    $relatedModel,
                    $newPath,
                    $currentDepth + 1,
                    $maxDepth,
                    $paths,
                    $visited
                );
            }
        }

        // Remove from visited when backtracking
        array_pop($visited);
    }

    /**
     * Check if adding this path would create a circular reference
     */
    protected function wouldCreateCircularPath(string $path, string $targetModel, array $existingPaths): bool
    {
        // Check if this model appears earlier in the path
        $segments = explode('.', $path);
        
        // Build the path progressively to check each level
        $currentPath = '';
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentPath = $i === 0 ? $segments[$i] : $currentPath . '.' . $segments[$i];
            
            if (isset($existingPaths[$currentPath]) && $existingPaths[$currentPath]['final_model'] === $targetModel) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @return array<
     *   string, array{
     *     columns: string[],
     *     relations: array<string, array{type:string,related_model:string,is_collection:bool}>
     *   }
     * >
     */
    public function inspectAll(): array
    {
        $output = [];

        foreach ($this->getModels() as $modelClass) {
            $output[$modelClass] = $this->getModelData($modelClass);
        }

        return $output;
    }
}
