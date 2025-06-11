<?php

namespace Hexlabs\LaravelExports\Services;

use Hexlabs\LaravelExports\{
    Enums\OperatorType,
    Models\ExportSort,
    Models\ExportModel,
    Models\ExportColumn,
    Models\ExportFilter,
    Models\ExportLayout,
    Models\ExportModelRelation,
    Exports\ExportFactory,
    Helpers\ModelRelationInspector,
};
use Illuminate\Database\Eloquent\{
    Builder,
    Model
};

use Illuminate\Support\{
    Str,
    Collection,
    Facades\DB,
    Facades\Log,
};

use Symfony\Component\HttpFoundation\StreamedResponse;

class DynamicExportService
{
    protected ?ExportModel $exportModel = null;

    protected ?ExportLayout $layout = null;

    protected Collection $filters;

    protected Collection $sorts;

    protected Collection $columns;

    protected Collection $relations;

    protected array $requestData = [];

    protected ?ModelRelationInspector $inspector = null;

    public function __construct()
    {
        $this->initializeCollections();
        $this->inspector = new ModelRelationInspector();
    }

    protected function initializeCollections(): void
    {
        $this->filters = collect();
        $this->sorts = collect();
        $this->columns = collect();
        $this->relations = collect();
    }

    /**
     * Execute an export based on a layout
     */
    public function executeExport(ExportLayout|string $layout, array $requestData = []): Collection
    {
        $this->loadLayout($layout);
        $this->requestData = $requestData;
        // Build the base query
        $query = $this->buildQuery();
        // Apply filters
        $query = $this->applyFilters($query);
        // Apply eager loading for relations
        $query = $this->applyEagerLoading($query);
        // Apply sorts
        $query = $this->applySorts($query);
        // Execute query and get results
        $results = $query->get();
        // Process results according to column configuration
        return $this->processResults($results);
    }

    /**
     * Load layout and related data
     */
    protected function loadLayout(ExportLayout|string $layout): void
    {
        if (is_string($layout)) {
            $layout = ExportLayout::find($layout);
        }

        if (! $layout) {
            throw new \Exception('Layout not found');
        }

        $this->layout = $layout;
        // Load related data
        $this->exportModel = $layout->exportModel;
        // Load columns with their relationships (including functions and filters)
        $this->columns = $layout->columns()
            ->with(['modelRelation', 'exportFunction', 'filter.relation'])
            ->orderBy('position')
            ->get();
        // Get column filter IDs to exclude from layout filters
        $columnFilterIds = $layout->columns()->whereNotNull('export_filter_id')->pluck('export_filter_id');
        // Only load filters that are NOT attached to columns
        $this->filters = $layout->filters()
            ->with(['relation'])
            ->whereNotIn('id', $columnFilterIds)
            ->get();
        
        $this->sorts = $layout->sorts()
            ->with(['modelRelation'])
            ->orderBy('priority')
            ->get();
        // Load model relations
        $this->relations = $this->exportModel->relations;
        // Validate layout configuration
        $this->validateLayout($layout);
    }

    /**
     * Build the base query for the model
     */
    protected function buildQuery(): Builder
    {
        $modelClass = $this->exportModel->model;

        if (! class_exists($modelClass)) {
            throw new \Exception("Model class {$modelClass} not found");
        }

        return $modelClass::query();
    }

    /**
     * Apply filters to the query
     */
    protected function applyFilters(Builder $query): Builder
    {
        // Separate request filters from static filters and resolve conflicts
        $requestFilters = $this->filters->where('is_request', true);
        $staticFilters = $this->filters->where('is_request', false);
        // Get request parameter names that have active filters
        $activeRequestParams = [];
        foreach ($requestFilters as $requestFilter) {
            $columnName = $this->getFilterColumnName($requestFilter);
            // Check if this request filter has an active value
            $possibleKeys = [
                $columnName,
                strtolower($columnName),
                Str::snake($columnName),
            ];
            
            foreach ($possibleKeys as $key) {
                if (isset($this->requestData[$key])) {
                    $activeRequestParams[] = $columnName;
                    break;
                }
            }
        }
        
        if (config('app.debug')) {
            Log::info("Filter conflict resolution:", [
                'total_filters' => $this->filters->count(),
                'request_filters' => $requestFilters->count(),
                'static_filters' => $staticFilters->count(),
                'active_request_params' => $activeRequestParams,
            ]);
        }
        // Apply all filters with conflict resolution
        $this->filters->each(function (ExportFilter $filter) use (&$query, $activeRequestParams) {
            // Get the column name from the relation
            $columnName = $this->getFilterColumnName($filter);
            // Skip static filters if there's an active request filter for the same parameter
            if (!$filter->is_request && in_array($columnName, $activeRequestParams)) {
                if (config('app.debug')) {
                    Log::info("Skipping static filter due to active request filter:", [
                        'static_filter_id' => $filter->id,
                        'column_name' => $columnName,
                        'reason' => 'request_filter_takes_priority',
                    ]);
                }
                return; // Skip this static filter
            }
            // For request filters, try multiple ways to get the parameter value
            if ($filter->is_request) {
                $value = null;
                // Try different parameter name patterns
                $possibleKeys = [
                    $columnName,
                    strtolower($columnName),
                    Str::snake($columnName),
                ];
                
                foreach ($possibleKeys as $key) {
                    if (isset($this->requestData[$key])) {
                        $value = $this->requestData[$key];
                        
                        if (config('app.debug')) {
                            Log::info("Request filter matched:", [
                                'filter_column' => $columnName,
                                'matched_key' => $key,
                                'value' => $value,
                                'operator' => $filter->operator,
                            ]);
                        }
                        break;
                    }
                }
                // Skip if required but not provided
                if ($filter->is_required && $value === null) {
                    throw new \Exception("Required filter '{$columnName}' not provided in request");
                }
            } else {
                // Use configured value for non-request filters
                $value = $filter->value;
            }
            // Skip if no value and not checking for null
            if ($value === null && ! in_array($filter->operator, ['null', 'not_null'])) {
                return;
            }

            if (config('app.debug')) {
                Log::info("Applying filter:", [
                    'filter_type' => $filter->is_request ? 'request' : 'static',
                    'column_name' => $columnName,
                    'operator' => $filter->operator,
                    'value' => $value,
                    'filter_id' => $filter->id,
                    'export_model_relation_id' => $filter->export_model_relation_id,
                ]);
            }
            // Apply the filter
            $this->applyFilter($query, $filter, $value);
        });
        // Apply column-specific filters
        $this->applyColumnFilters($query);

        return $query;
    }

    /**
     * Apply a single filter
     */
    protected function applyFilter(Builder $query, ExportFilter $filter, $value): void
    {
        $isOr = $filter->logical_operator === 'OR';
        $columnName = $this->getFilterColumnName($filter);

        if (config('app.debug')) {
            Log::info("Applying individual filter:", [
                'filter_id' => $filter->id,
                'is_request' => $filter->is_request,
                'operator' => $filter->operator,
                'column_name' => $columnName,
                'value' => $value,
                'has_relation' => !empty($filter->export_model_relation_id),
                'relation_path' => $filter->relation ? $filter->relation->relation : null,
                'logical_operator' => $filter->logical_operator,
            ]);
        }
        // Special handling for relation operator
        if ($filter->operator === 'relation') {
            // For relation operator, the value structure is different
            // It expects [relation, column, operator, value]
            if (config('app.debug')) {
                Log::info("Applying relation operator filter");
            }
            $this->applyOperator($query, $columnName, $filter->operator, $value, $isOr);
        } elseif ($filter->export_model_relation_id && $filter->relation) {
            // Check if this is a direct column filter or relationship filter
            $isDirectColumn = isset($filter->relation->is_column) && $filter->relation->is_column;
            
            if ($isDirectColumn) {
                // Direct column filtering - use the relation name as column name
                $directColumn = $filter->relation->relation;
                
                if (config('app.debug')) {
                    Log::info("Applying direct column filter:", [
                        'column' => $directColumn,
                        'operator' => $filter->operator,
                        'value' => $value,
                        'is_or' => $isOr,
                        'is_column_flag' => true,
                    ]);
                }
                
                $this->applyOperator($query, $directColumn, $filter->operator, $value, $isOr);
            } else {
                // Relationship filtering - use whereHas
                $relation = $filter->relation->relation;
                $column = $filter->relation->column ?? $columnName;

                if (config('app.debug')) {
                    Log::info("Applying relationship filter:", [
                        'relation' => $relation,
                        'column' => $column,
                        'method' => $isOr ? 'orWhereHas' : 'whereHas',
                        'is_column_flag' => false,
                    ]);
                }
                // For relationship filters, use whereHas
                $method = $isOr ? 'orWhereHas' : 'whereHas';

                $query->$method($relation, function ($q) use ($filter, $column, $value) {
                    $this->applyOperator($q, $column, $filter->operator, $value, false);
                });
            }
        } else {
            // Direct column filter on main model
            if (config('app.debug')) {
                Log::info("Applying direct column filter:", [
                    'column' => $columnName,
                    'operator' => $filter->operator,
                    'value' => $value,
                    'is_or' => $isOr,
                ]);
            }

            $this->applyOperator($query, $columnName, $filter->operator, $value, $isOr);
        }
        // Log the resulting query for debugging
        if (config('app.debug')) {
            Log::info("Query after filter application:", [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
            ]);
        }
    }

    /**
     * Get the column name for a filter
     */
    protected function getFilterColumnName(ExportFilter $filter): string
    {
        if ($filter->export_model_relation_id && $filter->relation) {
            // If filter has a relation, use the relation's name
            // The relation field contains the column path (e.g., "status" or "customer.name")
            return $filter->relation->relation;
        }
        // For request filters, use the relation configuration if available
        // This allows proper column mapping without hardcoding
        if ($filter->is_request && $filter->export_model_relation_id && $filter->relation) {
            // Check if this is a direct column reference
            $isDirectColumn = isset($filter->relation->is_column) && $filter->relation->is_column;
            
            if ($isDirectColumn) {
                // For direct columns, use the relation field as the column name
                $columnName = $filter->relation->relation;
                
                if (config('app.debug')) {
                    Log::info("Using direct column for request filter:", [
                        'filter_id' => $filter->id,
                        'relation_id' => $filter->export_model_relation_id,
                        'column_name' => $columnName,
                        'is_column' => true,
                    ]);
                }
                
                return $columnName;
            } else {
                // For relationships, use existing logic
                $relationColumn = $filter->relation->column ?? $filter->relation->relation;
                
                if (config('app.debug')) {
                    Log::info("Using relation-configured column for request filter:", [
                        'filter_id' => $filter->id,
                        'relation_id' => $filter->export_model_relation_id,
                        'column_name' => $relationColumn,
                        'relation_path' => $filter->relation->relation,
                        'is_column' => false,
                    ]);
                }
                
                return $relationColumn;
            }
        }
        // For request filters, try to determine column name from context
        if ($filter->is_request) {
            // If value contains column information
            if ($filter->value_type === 'array' && $filter->value) {
                $value = is_string($filter->value) ? json_decode($filter->value, true) : $filter->value;
                if (is_array($value) && isset($value['column'])) {
                    return $value['column'];
                }
            }
            // For request filters, we need a more robust way to determine the column
            // Check if there's a relation that matches the layout's model
            if ($this->exportModel && $this->exportModel->relations) {
                // Look for a matching relation in the current export model
                $relation = $this->exportModel->relations()
                    ->where('column', '!=', null)
                    ->first();
                
                if ($relation) {
                    return $relation->column;
                }
            }
            // Try common primary key patterns
            if ($this->exportModel) {
                $modelClass = $this->exportModel->model;
                if (class_exists($modelClass)) {
                    $instance = new $modelClass;
                    return $instance->getKeyName();
                }
            }
        }
        // Parse the value to get column information if it contains column data
        if ($filter->value_type === 'array' && $filter->value) {
            $value = is_string($filter->value) ? json_decode($filter->value, true) : $filter->value;
            if (is_array($value) && isset($value['column'])) {
                return $value['column'];
            }
        }
        // Otherwise, fallback to a default column name
        return 'id';
    }

    /**
     * Apply operator to query
     */
    protected function applyOperator(Builder $query, string $column, string $operator, $value, bool $isOr): void
    {
        $method = OperatorType::getCallable($operator, $isOr);

        switch ($operator) {
            case 'between':
                // Expects array with two values
                if (! is_array($value) || count($value) !== 2) {
                    throw new \Exception('Between operator requires array with 2 values');
                }
                $query->$method($column, $value);
                break;

            case 'in':
            case 'not_in':
                // Expects array
                if (! is_array($value)) {
                    $value = [$value];
                }
                $query->$method($column, $value);
                break;

            case 'null':
            case 'not_null':
                // No value needed
                $query->$method($column);
                break;

            case 'json_contains':
                // JSON contains - expects column and value
                $query->$method($column, $value);
                break;

            case 'relation':
                // Relation operator - expects relation name, column, operator, and value
                // The value should be an array with [relation, column, operator, value]
                if (! is_array($value) || count($value) < 3) {
                    throw new \Exception('Relation operator requires array with [relation, column, operator, value]');
                }
                [$relation, $relColumn, $relOperator, $relValue] = $value;
                $query->$method($relation, $relColumn, $relOperator, $relValue ?? null);
                break;

            default:
                // Standard operators (=, !=, >, <, >=, <=, like)
                $query->$method($column, $operator, $value);
                break;
        }
    }

    /**
     * Apply column-specific filters to the query
     */
    protected function applyColumnFilters(Builder $query): void
    {
        // Get columns that have filters
        $columnsWithFilters = $this->columns->filter(fn ($column) => $column->export_filter_id);

        foreach ($columnsWithFilters as $column) {
            // Load the filter if not already loaded
            $filter = $column->filter;

            if (! $filter) {
                continue;
            }
            // Clone the filter to avoid modifying the original
            $columnFilter = clone $filter;
            // If column has specific filter values, use those instead of the filter's default
            if ($column->export_filter_values !== null) {
                $columnFilter->value = $column->export_filter_values;
            }
            // Get the column name from the filter
            $columnName = $this->getFilterColumnName($columnFilter);
            // Skip if required from request but not provided
            if ($columnFilter->is_request && $columnFilter->is_required) {
                if (! isset($this->requestData[$columnName])) {
                    throw new \Exception("Required column filter '{$columnName}' not provided in request");
                }
            }
            // Get the value (from request or filter configuration)
            $value = $columnFilter->is_request
                ? ($this->requestData[$columnName] ?? null)
                : $columnFilter->value;
            // Skip if no value and not checking for null
            if ($value === null && ! in_array($columnFilter->operator, ['null', 'not_null'])) {
                continue;
            }
            // Skip filters with 'relation' operator - these are handled in applyEagerLoading
            if ($columnFilter->operator === 'relation') {
                continue;
            }
            // Apply the filter
            $this->applyFilter($query, $columnFilter, $value);
        }
    }

    /**
     * Apply eager loading for relationships used in columns
     */
    protected function applyEagerLoading(Builder $query): Builder
    {
        // Use improved eager loading method
        $relationsToLoad = $this->buildEagerLoadArray();
        $constrainedRelations = [];
        // Collect relations from columns that need constraints
        $this->columns
            ->filter(fn ($column) => $column->export_model_relation_id)
            ->each(function ($column) use (&$constrainedRelations, &$relationsToLoad) {
                $relationPath = $this->buildRelationPath($column);
                if ($relationPath) {
                    // Check if this column has a filter that should constrain the relation
                    if ($column->export_filter_id && $column->filter) {
                        $filter = $column->filter;
                        // If it's a relation operator filter, we need to apply constraints
                        if ($filter->operator === 'relation' && $filter->relation && !$filter->relation->is_column) {
                            // Store the constraint for this relation
                            if (!isset($constrainedRelations[$relationPath])) {
                                $constrainedRelations[$relationPath] = [];
                            }
                            
                            $constrainedRelations[$relationPath][] = [
                                'filter' => $filter,
                                'values' => $column->export_filter_values ?? $filter->value,
                                'column' => $column,
                            ];
                        } else {
                            // Regular relation without constraints
                            $relationsToLoad[] = $relationPath;
                        }
                    } else {
                        // For nested paths, add all intermediate relations
                        if (Str::contains($relationPath, '.')) {
                            $this->addNestedRelationPaths($relationPath, $relationsToLoad);
                        } else {
                            $relationsToLoad[] = $relationPath;
                        }
                    }
                }
            });
        // Collect relations from filters (but not direct columns)
        $this->filters->each(function (ExportFilter $filter) use (&$relationsToLoad) {
            if ($filter->relation && !$filter->relation->is_column) {
                $relationPath = $filter->relation->relation;
                if (Str::contains($relationPath, '.')) {
                    $this->addNestedRelationPaths($relationPath, $relationsToLoad);
                } else {
                    $relationsToLoad[] = $relationPath;
                }
            }
        });
        // Apply regular relations
        if (! empty($relationsToLoad)) {
            $uniqueRelations = array_unique($relationsToLoad);
            // Sort by depth to ensure parent relations are loaded before children
            usort($uniqueRelations, function ($a, $b) {
                return substr_count($a, '.') <=> substr_count($b, '.');
            });
            
            $query->with($uniqueRelations);
        }
        // Apply constrained relations
        foreach ($constrainedRelations as $relationPath => $constraints) {
            // Skip if somehow we got a null relation path
            if (!$relationPath) {
                continue;
            }
            // Group constraints by their filter configuration
            $groupedConstraints = [];
            
            foreach ($constraints as $constraint) {
                $filter = $constraint['filter'];
                $value = $constraint['values'];
                
                if ($filter->relation) {
                    $key = $filter->relation->relation . '|' . $filter->operator . '|' . $filter->logical_operator;
                    if (!isset($groupedConstraints[$key])) {
                        $groupedConstraints[$key] = [
                            'relation' => $filter->relation->relation,
                            'operator' => $filter->operator,
                            'logical_operator' => $filter->logical_operator,
                            'values' => [],
                        ];
                    }
                    $groupedConstraints[$key]['values'][] = $value;
                }
            }
            // Apply the constraints using whereRelation
            $query->with([$relationPath => function ($q) use ($groupedConstraints) {
                foreach ($groupedConstraints as $config) {
                    $relation = $config['relation'];
                    $operator = '='; // Default operator for the nested relation
                    $isOr = strtolower($config['logical_operator']) === 'or';
                    
                    if (count($config['values']) === 1) {
                        // Single value constraint
                        $method = $isOr ? 'orWhereRelation' : 'whereRelation';
                        $q->$method($relation, 'title', $operator, $config['values'][0]);
                    } else {
                        // Multiple values - use whereIn
                        $method = $isOr ? 'orWhereRelation' : 'whereRelation';
                        $q->$method($relation, function ($query) use ($config) {
                            $query->whereIn('title', $config['values']);
                        });
                    }
                }
            }]);
        }

        return $query;
    }

    /**
     * Add all intermediate paths for nested relations
     * For "workItem.workOrder.customer.contact", this adds:
     * - workItem
     * - workItem.workOrder
     * - workItem.workOrder.customer
     * - workItem.workOrder.customer.contact
     * 
     * Note: This assumes the fullPath contains only relations, not attributes
     */
    protected function addNestedRelationPaths(string $fullPath, array &$relationsToLoad): void
    {
        $segments = explode('.', $fullPath);
        $currentPath = '';
        
        foreach ($segments as $segment) {
            $currentPath = $currentPath ? "{$currentPath}.{$segment}" : $segment;
            $relationsToLoad[] = $currentPath;
        }
    }

    /**
     * Build relation path from column configuration
     */
    protected function buildRelationPath(ExportColumn $column): ?string
    {
        if (! $column->modelRelation) {
            return null;
        }

        // Skip if it's a direct column, not a relation
        if ($column->modelRelation->is_column) {
            return null;
        }

        // Get the base relation
        $baseRelation = $column->modelRelation->relation;
        
        // If the modelRelation already contains dot notation (e.g., "worker.user"), 
        // validate and potentially create the missing parts
        if (Str::contains($baseRelation, '.')) {
            $this->validateAndCreateNestedPath($baseRelation);
            return $baseRelation;
        }

        // If no value_path, just return the direct relation
        if (!$column->value_path || !Str::contains($column->value_path, '.')) {
            return $baseRelation;
        }

        // Parse the value_path to separate relations from the final attribute
        $parts = explode('.', $column->value_path);
        
        // If the first part matches the base relation, start from there
        if ($parts[0] === $baseRelation) {
            // Remove the base relation from parts since it's already included
            array_shift($parts);
        }
        
        // Start with the base relation
        $relationParts = [$baseRelation];
        
        // Check each subsequent part to see if it's a relation or attribute
        // We need to stop before the final attribute
        $currentModel = $column->modelRelation->relatedModel;
        
        foreach ($parts as $part) {
            if (!$currentModel) {
                break;
            }
            
            // Check if this part is a relation on the current model
            $relation = $currentModel->relations()
                ->where('relation', $part)
                ->where('is_column', false)
                ->first();
                
            if ($relation) {
                // It's a relation, add to path
                $relationParts[] = $part;
                $currentModel = $relation->relatedModel;
            } else {
                // It's an attribute, stop here
                break;
            }
        }

        $fullPath = implode('.', $relationParts);
        
        // Validate and create the path if it doesn't exist in our records
        if (count($relationParts) > 1) {
            $this->validateAndCreateNestedPath($fullPath);
        }

        return $fullPath;
    }

    /**
     * Validate a nested path and create missing relations on-the-fly
     */
    protected function validateAndCreateNestedPath(string $path): void
    {
        if (!$this->exportModel || !$this->inspector) {
            return;
        }

        // Check if this nested path already exists in our records
        $exists = ExportModelRelation::where('export_model_id', $this->exportModel->id)
            ->where('relation', $path)
            ->exists();

        if ($exists) {
            return; // Path already exists
        }

        // Validate the path using ModelRelationInspector
        $validation = $this->inspector->validateNestedPath($this->exportModel->model, $path);

        if (!$validation['valid']) {
            Log::warning("Invalid nested path detected: {$path}", [
                'error' => $validation['error'],
                'model' => $this->exportModel->model,
            ]);
            return;
        }

        // Path is valid, create the missing relation record
        $this->createNestedRelation($path, $validation);
    }

    /**
     * Create a nested relation record from validation results
     */
    protected function createNestedRelation(string $path, array $validation): void
    {
        try {
            // Find the related export model
            $relatedExportModel = null;
            if ($validation['final_model']) {
                $relatedExportModel = ExportModel::where('model', $validation['final_model'])->first();
            }

            // Determine if it's a collection based on the final segment
            $segments = $validation['segments'];
            $isCollection = false;
            if (!empty($segments)) {
                $lastSegment = end($segments);
                $isCollection = $lastSegment['is_collection'] ?? false;
            }

            // Create the nested relation
            ExportModelRelation::create([
                'export_model_id' => $this->exportModel->id,
                'relation' => $path,
                'title' => $this->generateNestedTitle($path),
                'is_column' => false,
                'is_collection' => $isCollection,
                'related_model_id' => $relatedExportModel?->id,
            ]);

            Log::info("Created missing nested relation on-the-fly: {$path}", [
                'model' => $this->exportModel->model,
                'final_model' => $validation['final_model'],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to create nested relation: {$path}", [
                'error' => $e->getMessage(),
                'model' => $this->exportModel->model,
            ]);
        }
    }

    /**
     * Generate a human-readable title for nested relations
     */
    protected function generateNestedTitle(string $path): string
    {
        $segments = explode('.', $path);
        $titles = array_map(function ($segment) {
            return Str::title(str_replace('_', ' ', $segment));
        }, $segments);

        return implode(' → ', $titles);
    }

    /**
     * Validate and create missing nested relations from value_path
     */
    protected function validateValuePath(string $valuePath): void
    {
        if (!$this->exportModel || !$this->inspector) {
            return;
        }

        // Extract the relation part from value path (excluding final attribute)
        $parts = explode('.', $valuePath);
        
        // Remove the last part (which is likely an attribute)
        array_pop($parts);
        
        if (empty($parts)) {
            return; // No relations in the path
        }

        $relationPath = implode('.', $parts);
        
        // Check if this relation path exists
        $exists = ExportModelRelation::where('export_model_id', $this->exportModel->id)
            ->where('relation', $relationPath)
            ->exists();

        if (!$exists) {
            // Validate and create the missing relation
            $this->validateAndCreateNestedPath($relationPath);
        }
    }

    /**
     * Apply sorts to the query
     */
    protected function applySorts(Builder $query): Builder
    {
        $this->sorts->each(function (ExportSort $sort) use (&$query) {
            if ($sort->export_model_relation_id && $sort->modelRelation) {
                $this->applySortForRelation($query, $sort);
            } else {
                // Direct column sort
                $columnName = $sort->modelRelation ? $sort->modelRelation->relation : 'id';
                $query->orderBy($columnName, $sort->direction);
            }
        });

        return $query;
    }

    /**
     * Apply sorting for related model columns
     */
    protected function applySortForRelation(Builder $query, ExportSort $sort): void
    {
        $relation = $sort->modelRelation;

        if (! $relation) {
            return;
        }

        // Handle different relationship types
        $relationParts = explode('.', $relation->relation);
        $immediateRelation = $relationParts[0];

        // Check if this is a direct relation or nested
        if (count($relationParts) === 1) {
            // Direct relation - we can use orderByRelation for BelongsTo and HasOne
            if (! $relation->is_collection) {
                // For BelongsTo and HasOne relationships, we can use a join
                $this->applyRelationJoinSort($query, $sort, $immediateRelation);
            } else {
                // For HasMany and BelongsToMany, we need to use a subquery
                $this->applyRelationSubquerySort($query, $sort, $immediateRelation);
            }
        } else {
            // Nested relation - more complex, use subquery approach
            $this->applyNestedRelationSort($query, $sort, $relation->relation);
        }
    }

    /**
     * Apply join-based sorting for BelongsTo and HasOne relationships
     */
    protected function applyRelationJoinSort(Builder $query, ExportSort $sort, string $relationName): void
    {
        $relation = $query->getModel()->$relationName();

        if ($relation instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
            $relatedTable = $relation->getRelated()->getTable();
            $foreignKey = $relation->getForeignKeyName();
            $ownerKey = $relation->getOwnerKeyName();

            $query->leftJoin($relatedTable, $query->getModel()->getTable().'.'.$foreignKey, '=', $relatedTable.'.'.$ownerKey)
                ->orderBy($relatedTable.'.'.$this->getRelationSortColumn($sort), $sort->direction)
                ->select($query->getModel()->getTable().'.*');
        } elseif ($relation instanceof \Illuminate\Database\Eloquent\Relations\HasOne) {
            $relatedTable = $relation->getRelated()->getTable();
            $foreignKey = $relation->getForeignKeyName();
            $localKey = $relation->getLocalKeyName();

            $query->leftJoin($relatedTable, $query->getModel()->getTable().'.'.$localKey, '=', $relatedTable.'.'.$foreignKey)
                ->orderBy($relatedTable.'.'.$this->getRelationSortColumn($sort), $sort->direction)
                ->select($query->getModel()->getTable().'.*');
        }
    }

    /**
     * Apply subquery-based sorting for HasMany and BelongsToMany relationships
     */
    protected function applyRelationSubquerySort(Builder $query, ExportSort $sort, string $relationName): void
    {
        $column = $this->getRelationSortColumn($sort);

        // For collection relationships, we'll sort by an aggregate (e.g., COUNT, MIN, MAX)
        // Default to COUNT if no specific aggregation is specified
        $query->withCount([$relationName => function ($q) use ($column) {
            if ($column !== 'id') {
                $q->select(DB::raw("COUNT(DISTINCT {$column})"));
            }
        }])->orderBy($relationName.'_count', $sort->direction);
    }

    /**
     * Apply sorting for nested relationships
     */
    protected function applyNestedRelationSort(Builder $query, ExportSort $sort, string $relationPath): void
    {
        // For nested relations, we'll use a more complex approach
        // This is a simplified implementation that may need enhancement
        $parts = explode('.', $relationPath);
        $sortColumn = $this->getRelationSortColumn($sort);

        // Use whereHas with orderByRaw for nested relations
        $query->orderBy(
            $query->getModel()->$parts[0]()
                ->getRelated()
                ->newQuery()
                ->whereColumn(
                    $query->getModel()->getTable().'.'.$query->getModel()->getKeyName(),
                    $query->getModel()->$parts[0]()->getForeignKeyName()
                )
                ->select($sortColumn)
                ->limit(1)
                ->toBase(),
            $sort->direction
        );
    }

    /**
     * Get the column to sort by from the sort configuration
     */
    protected function getRelationSortColumn(ExportSort $sort): string
    {
        // If the relation has metadata with a specific column, use it
        // Otherwise default to 'id' or the primary key
        if ($sort->modelRelation && $sort->modelRelation->metadata) {
            return $sort->modelRelation->metadata['sort_column'] ?? 'id';
        }

        return 'id';
    }

    /**
     * Process results according to column configuration
     */
    protected function processResults(Collection $results): Collection
    {
        return $results->map(function (Model $model) {
            $row = [];

            foreach ($this->columns as $column) {
                $key = $column->title ?: $column->value_path;
                $value = $this->extractColumnValue($model, $column);

                // Apply function if configured
                if ($column->export_function_id && $column->exportFunction) {
                    $originalValue = $value;
                    $value = $this->applyColumnFunction($value, $column);
                    
                    if (config('app.debug')) {
                        Log::info("Function application result:", [
                            'column_title' => $column->title,
                            'function_id' => $column->export_function_id,
                            'function_name' => $column->exportFunction->name ?? 'unknown',
                            'original_value' => $originalValue,
                            'transformed_value' => $value,
                            'function_applied' => $originalValue !== $value,
                        ]);
                    }
                }

                // Apply aggregation if configured
                if ($column->aggregator && is_iterable($value)) {
                    $value = $this->applyAggregation($value, $column->aggregator);
                }

                // Use default if value is null or empty
                if ($value === null || $value === '') {
                    $defaultValue = $column->default ?? '';
                    
                    if (config('app.debug') && ($value === null || $value === '')) {
                        Log::info("Using default value for empty column:", [
                            'column_title' => $column->title,
                            'original_value' => $value,
                            'column_default' => $column->default,
                            'final_value' => $defaultValue,
                        ]);
                    }
                    
                    $value = $defaultValue;
                }

                // Skip if configured to omit empty
                if ($column->omit_on_empty && empty($value)) {
                    continue;
                }

                $row[$key] = $value;
            }

            return $row;
        });
    }

    /**
     * Extract value from model based on column configuration
     */
    protected function extractColumnValue(Model $model, ExportColumn $column)
    {
        // Add debugging if enabled
        $this->debugRelationLoading($model, $column);
        
        $value = null;
        
        // If no relation, get direct attribute
        if (! $column->export_model_relation_id) {
            $value = data_get($model, $column->value_path ?? $column->relation);
            
            if (config('app.debug')) {
                Log::info("Direct attribute extraction:", [
                    'column_title' => $column->title,
                    'value_path' => $column->value_path,
                    'extracted_value' => $value,
                ]);
            }
            
            return $value;
        }

        // Check if this column has a filter that constrained the relation
        if ($column->export_filter_id && $column->filter && $column->filter->operator === 'relation') {
            $value = $this->extractCollectionValue($model, $column);
            
            if (config('app.debug')) {
                Log::info("Collection value extraction:", [
                    'column_title' => $column->title,
                    'filter_operator' => $column->filter->operator,
                    'extracted_value' => $value,
                ]);
            }
            
            return $value;
        }

        // Handle regular relation traversal
        $value = $this->resolveRelationValue($model, $column);
        
        if (config('app.debug')) {
            Log::info("Relation value extraction:", [
                'column_title' => $column->title,
                'relation_path' => $column->modelRelation->relation ?? 'none',
                'value_path' => $column->value_path,
                'extracted_value' => $value,
                'value_type' => gettype($value),
            ]);
        }
        
        return $value;
    }

    /**
     * Resolve nested relation values properly
     */
    protected function resolveRelationValue(Model $model, ExportColumn $column)
    {
        if (!$column->modelRelation) {
            return data_get($model, $column->value_path);
        }

        $relationPath = $column->modelRelation->relation;
        $valuePath = $column->value_path;

        // Debug logging - enhanced for user relations
        if (config('app.debug') && (Str::contains($relationPath, 'worker') || Str::contains($relationPath, 'user'))) {
            Log::info("Resolving relation:", [
                'relation_path' => $relationPath,
                'value_path' => $valuePath,
                'model_type' => get_class($model),
                'model_id' => $model->id,
                'relation_exists' => method_exists($model, explode('.', $relationPath)[0]),
                'eager_loaded' => $model->relationLoaded(explode('.', $relationPath)[0]),
            ]);
        }

        // If relationPath already contains dots, it's a nested relation
        if (Str::contains($relationPath, '.')) {
            // Try to get the value directly using the relation path
            $relatedData = data_get($model, $relationPath);
            
            if ($relatedData !== null && $valuePath) {
                // If we have a value path, it might be an attribute on the final relation
                // First check if valuePath starts with relationPath
                if (Str::startsWith($valuePath, $relationPath . '.')) {
                    // Get the attribute part after the relation
                    $attribute = Str::after($valuePath, $relationPath . '.');
                    return data_get($relatedData, $attribute);
                } else {
                    // Try the value path as-is
                    return data_get($model, $valuePath);
                }
            }
            
            return $relatedData;
        }

        // Handle nested paths like 'user.name' or 'workItem.workType.name'
        if ($valuePath && Str::contains($valuePath, '.')) {
            // Check if we need to create missing nested relations for this path
            $this->validateValuePath($valuePath);
            
            // Use the full value path directly
            $relatedData = data_get($model, $valuePath);
            
            // If direct path didn't work, try building it step by step
            if ($relatedData === null) {
                // Start with the base relation
                $baseRelation = data_get($model, $relationPath);
                
                if ($baseRelation) {
                    // Extract the remaining path after the base relation
                    $pathParts = explode('.', $valuePath);
                    $relationParts = explode('.', $relationPath);
                    
                    // Remove the relation parts from the value path
                    $remainingPath = array_slice($pathParts, count($relationParts));
                    
                    if (!empty($remainingPath)) {
                        return data_get($baseRelation, implode('.', $remainingPath));
                    }
                    
                    return $baseRelation;
                }
            }
            
            return $relatedData;
        }

        // Simple case - use value path if provided, otherwise relation path
        if ($valuePath) {
            $value = data_get($model, $valuePath);
            
            // If value path didn't work, try extracting from the relation
            if ($value === null && $relationPath) {
                $relatedData = data_get($model, $relationPath);
                if ($relatedData) {
                    // Check if value path is an attribute on the related model
                    $value = data_get($relatedData, $valuePath);
                }
            }
            
            return $value;
        }

        // Default to relation data
        $relationData = data_get($model, $relationPath);
        
        // If it's a model/object and we don't have a specific value path,
        // try to get a meaningful value (like name, title, or convert to string)
        if (is_object($relationData) && !is_iterable($relationData)) {
            // Try common attribute names
            foreach (['name', 'title', 'value', 'label'] as $attr) {
                if (isset($relationData->$attr)) {
                    return $relationData->$attr;
                }
            }
            
            // Try to convert to string
            if (method_exists($relationData, '__toString')) {
                return (string) $relationData;
            }
        }
        
        return $relationData;
    }

    /**
     * Extract specific values from collections based on filters
     */
    protected function extractCollectionValue(Model $model, ExportColumn $column)
    {
        if (!$column->export_filter_id || !$column->filter) {
            if (config('app.debug')) {
                Log::info("Collection extraction skipped - no filter:", [
                    'column_title' => $column->title,
                    'has_filter_id' => !empty($column->export_filter_id),
                    'has_filter' => !empty($column->filter),
                ]);
            }
            return $column->default ?? '';
        }

        $filter = $column->filter;
        $relationPath = $column->modelRelation->relation;
        
        // Validate nested paths if needed
        if (Str::contains($relationPath, '.')) {
            $this->validateValuePath($relationPath);
        }
        
        $collection = data_get($model, $relationPath);

        if (config('app.debug')) {
            Log::info("Collection extraction details:", [
                'column_title' => $column->title,
                'relation_path' => $relationPath,
                'value_path' => $column->value_path,
                'collection_type' => gettype($collection),
                'collection_count' => is_iterable($collection) ? count($collection) : 'not iterable',
                'filter_relation' => $filter->relation ? $filter->relation->relation : 'no relation',
                'expected_value' => $column->export_filter_values ?? $filter->value,
                'collection_sample' => is_iterable($collection) && count($collection) > 0 ? collect($collection)->take(2)->toArray() : 'empty or not iterable',
            ]);
        }

        if (!$collection || !is_iterable($collection)) {
            return $column->default ?? '';
        }

        // Convert to collection if it's not already
        if (!($collection instanceof \Illuminate\Support\Collection)) {
            $collection = collect($collection);
        }

        // Filter the collection based on the relation operator filter
        $filtered = $collection->filter(function($item) use ($filter, $column) {
            // For relation operator filters
            if ($filter->operator === 'relation' && $filter->value) {
                $filterConfig = is_string($filter->value) ? json_decode($filter->value, true) : $filter->value;
                
                if (is_array($filterConfig) && count($filterConfig) >= 4) {
                    // Format: ['workItem.values', 'type.title', '=', 'Splits']
                    $filterPath = $filterConfig[1]; // 'type.title'
                    $expectedValue = $filterConfig[3]; // 'Splits'
                    $actualValue = data_get($item, $filterPath);
                    
                    if (config('app.debug')) {
                        Log::info("Relation filter check:", [
                            'column_title' => $column->title,
                            'filter_path' => $filterPath,
                            'expected_value' => $expectedValue,
                            'actual_value' => $actualValue,
                            'matches' => $actualValue === $expectedValue,
                        ]);
                    }
                    
                    return $actualValue === $expectedValue;
                }
            }
            
            // Fallback for other filter types
            if (!$filter->relation) {
                return false;
            }
            
            $filterRelation = $filter->relation->relation;
            $expectedValue = $column->export_filter_values ?? $filter->value;
            $actualValue = data_get($item, $filterRelation);
            
            if (config('app.debug')) {
                Log::info("Collection item filter check:", [
                    'filter_relation' => $filterRelation,
                    'expected_value' => $expectedValue,
                    'actual_value' => $actualValue,
                    'matches' => $actualValue === $expectedValue,
                    'item_type' => gettype($item),
                    'item_id' => is_object($item) && isset($item->id) ? $item->id : 'no id',
                    'item_data' => is_object($item) ? get_object_vars($item) : $item,
                ]);
            }
            
            // Try exact match first
            if ($actualValue === $expectedValue) {
                return true;
            }
            
            // Try loose comparison for cases where types might differ
            if ($actualValue == $expectedValue) {
                if (config('app.debug')) {
                    Log::info("Collection filter matched with loose comparison", [
                        'actual_value' => $actualValue,
                        'expected_value' => $expectedValue,
                        'actual_type' => gettype($actualValue),
                        'expected_type' => gettype($expectedValue),
                    ]);
                }
                return true;
            }
            
            // Try string comparison for ID-based matches
            if (is_numeric($actualValue) && is_numeric($expectedValue)) {
                return (string)$actualValue === (string)$expectedValue;
            }
            
            return false;
        });

        if (config('app.debug')) {
            Log::info("Collection filtering result:", [
                'column_title' => $column->title,
                'original_count' => $collection->count(),
                'filtered_count' => $filtered->count(),
            ]);
        }

        // Return the value from the first matching item
        $firstMatch = $filtered->first();
        
        if (!$firstMatch) {
            if (config('app.debug')) {
                Log::info("No matches found in collection filter, using fallback strategy:", [
                    'column_title' => $column->title,
                    'collection_count' => $collection->count(),
                    'filter_relation' => $filter->relation ? $filter->relation->relation : 'no relation',
                    'expected_value' => $column->export_filter_values ?? $filter->value,
                ]);
            }
            
            // No match found - return default value
            return $column->default ?? '';
        }

        // Extract the specific value path from the matched item
        $extractedValue = null;
        
        if ($column->value_path) {
            if (Str::contains($column->value_path, '.')) {
                // Complex path handling
                if (Str::startsWith($column->value_path, $relationPath . '.')) {
                    // Get the attribute part after the relation path
                    $attribute = Str::after($column->value_path, $relationPath . '.');
                    $extractedValue = data_get($firstMatch, $attribute);
                } else {
                    // Try different extraction strategies
                    $pathParts = explode('.', $column->value_path);
                    $relationParts = explode('.', $relationPath);
                    
                    // Check if value_path starts with relation parts
                    $valueStartsWithRelation = true;
                    for ($i = 0; $i < count($relationParts) && $i < count($pathParts); $i++) {
                        if ($pathParts[$i] !== $relationParts[$i]) {
                            $valueStartsWithRelation = false;
                            break;
                        }
                    }
                    
                    if ($valueStartsWithRelation && count($pathParts) > count($relationParts)) {
                        $remainingPath = array_slice($pathParts, count($relationParts));
                        $extractedValue = data_get($firstMatch, implode('.', $remainingPath));
                    } else {
                        // Use the full value_path on the matched item
                        $extractedValue = data_get($firstMatch, $column->value_path);
                    }
                }
            } else {
                // Simple attribute name
                $extractedValue = data_get($firstMatch, $column->value_path);
            }
        }
        
        // If no specific value extracted, try common attributes
        if ($extractedValue === null) {
            foreach (['value', 'name', 'title', 'label'] as $attr) {
                $extractedValue = data_get($firstMatch, $attr);
                if ($extractedValue !== null) {
                    break;
                }
            }
        }
        
        // Fall back to the item itself
        if ($extractedValue === null) {
            $extractedValue = $firstMatch;
        }

        if (config('app.debug')) {
            Log::info("Collection value extraction result:", [
                'column_title' => $column->title,
                'value_path' => $column->value_path,
                'extracted_value' => $extractedValue,
                'first_match_type' => gettype($firstMatch),
            ]);
        }

        return $extractedValue;
    }

    /**
     * Apply function to column value
     */
    protected function applyColumnFunction($value, ExportColumn $column)
    {
        $function = $column->exportFunction;
        $callable = $function->callable;

        // Parse function values
        $values = json_decode($column->export_function_values, true) ?? [];

        // Prepare parameters
        $params = [];
        $paramIndex = $function->value_parameter_index ?? 0;

        for ($i = 0; $i < $function->parameter_count; $i++) {
            if ($i === $paramIndex) {
                $params[] = $value;
            } else {
                $params[] = $values[$i] ?? null;
            }
        }

        // Call the function with error handling
        if (is_callable($callable)) {
            try {
                $result = call_user_func_array($callable, $params);
                
                // Debug logging for function execution
                if (config('app.debug')) {
                    Log::info("Function executed successfully:", [
                        'function_name' => $function->name,
                        'callable' => $callable,
                        'input_value' => $value,
                        'params' => $params,
                        'result' => $result,
                    ]);
                }
                
                return $result;
            } catch (\Throwable $e) {
                // Log function execution errors
                Log::error("Function execution failed:", [
                    'function_name' => $function->name,
                    'callable' => $callable,
                    'input_value' => $value,
                    'params' => $params,
                    'error' => $e->getMessage(),
                    'column_title' => $column->title,
                ]);
                
                // Return original value on error
                return $value;
            }
        } else {
            Log::warning("Function not callable:", [
                'function_name' => $function->name,
                'callable' => $callable,
                'column_title' => $column->title,
            ]);
        }

        return $value;
    }

    /**
     * Apply aggregation to a collection of values
     */
    protected function applyAggregation($values, string $aggregator)
    {
        if (! is_iterable($values)) {
            return $values;
        }

        $collection = collect($values);

        return match ($aggregator) {
            'sum' => $collection->sum(),
            'count' => $collection->count(),
            'avg', 'average' => $collection->avg(),
            'min' => $collection->min(),
            'max' => $collection->max(),
            'first' => $collection->first(),
            'last' => $collection->last(),
            default => $values,
        };
    }


    /**
     * Execute export with chunking for large datasets
     */
    public function executeExportChunked(ExportLayout|string $layout, array $requestData = [], int $chunkSize = 1000, ?callable $callback = null): void
    {
        $this->loadLayout($layout);
        $this->requestData = $requestData;

        // Build the base query
        $query = $this->buildQuery();

        // Apply filters
        $query = $this->applyFilters($query);

        // Apply eager loading for relations
        $query = $this->applyEagerLoading($query);

        // Apply sorts
        $query = $this->applySorts($query);

        // Process in chunks
        $query->chunk($chunkSize, function ($results) use ($callback) {
            $processed = $this->processResults($results);

            if ($callback) {
                $callback($processed);
            }
        });
    }

    /**
     * Get the total count of records that would be exported
     */
    public function getExportCount(ExportLayout|string $layout, array $requestData = []): int
    {
        $this->loadLayout($layout);
        $this->requestData = $requestData;

        // Build the base query
        $query = $this->buildQuery();

        // Apply filters only (no need for eager loading or sorts for count)
        $query = $this->applyFilters($query);

        return $query->count();
    }

    /**
     * Execute export with pagination
     */
    public function executeExportPaginated(ExportLayout|string $layout, array $requestData = [], int $perPage = 100, int $page = 1): array
    {
        $this->loadLayout($layout);
        $this->requestData = $requestData;

        // Build the base query
        $query = $this->buildQuery();

        // Apply filters
        $query = $this->applyFilters($query);

        // Apply eager loading for relations
        $query = $this->applyEagerLoading($query);

        // Apply sorts
        $query = $this->applySorts($query);

        // Paginate results
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // Process results
        $processed = $this->processResults($paginated->getCollection());

        return [
            'data' => $processed,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ],
        ];
    }

    /**
     * Get the built query (useful for debugging)
     */
    public function getQuery(ExportLayout|string $layout, array $requestData = []): Builder
    {
        $this->loadLayout($layout);
        $this->requestData = $requestData;

        // Build the base query
        $query = $this->buildQuery();

        // Apply filters
        $query = $this->applyFilters($query);

        // Apply eager loading for relations
        $query = $this->applyEagerLoading($query);

        // Apply sorts
        $query = $this->applySorts($query);

        return $query;
    }

    /**
     * Export to a specific format and get the content
     */
    public function exportTo(ExportLayout|string $layout, string $format, array $requestData = [], array $options = []): mixed
    {
        $this->loadLayout($layout);

        // Get the data
        $data = $this->executeExport($this->layout, $requestData);

        // Create handler and export
        $handler = ExportFactory::create($format, $this->layout, $options);

        return $handler->export($data);
    }

    /**
     * Export to a specific format and download
     */
    public function downloadAs(ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = []): \Illuminate\Http\Response
    {
        $this->loadLayout($layout);

        // Get the exported content
        $content = $this->exportTo($this->layout, $format, $requestData, $options);

        // Create handler and download
        $handler = ExportFactory::create($format, $this->layout, $options);

        return $handler->download($content, $filename);
    }

    /**
     * Export to a specific format and store
     */
    public function storeAs(ExportLayout|string $layout, string $format, string $path, array $requestData = [], array $options = []): bool
    {
        $this->loadLayout($layout);

        // Get the exported content
        $content = $this->exportTo($this->layout, $format, $requestData, $options);

        // Create handler and store
        $handler = ExportFactory::create($format, $this->layout, $options);

        return $handler->store($content, $path);
    }

    /**
     * Stream export for large datasets
     */
    public function streamAs(ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = [], int $chunkSize = 1000): \Illuminate\Http\Response|StreamedResponse
    {
        $this->loadLayout($layout);

        // Create handler
        $handler = ExportFactory::create($format, $this->layout, $options);

        // Stream with chunked data callback
        return $handler->stream(function ($processChunk) use ($requestData, $chunkSize) {
            $this->executeExportChunked($this->layout, $requestData, $chunkSize, $processChunk);
        }, $filename);
    }

    /**
     * Get supported export formats
     */
    public static function getSupportedFormats(): array
    {
        return ExportFactory::getSupportedFormats();
    }

    /**
     * Validate layout configuration
     */
    protected function validateLayout(ExportLayout $layout): void
    {
        // Check for orphaned relations
        foreach ($layout->columns as $column) {
            if ($column->export_model_relation_id && !$column->modelRelation) {
                throw new \Exception("Column '{$column->title}' references non-existent relation ID: {$column->export_model_relation_id}");
            }
        }

        // Check for required filters without proper configuration
        $requiredFilters = $layout->filters()->where('is_required', true)->get();
        foreach ($requiredFilters as $filter) {
            if ($filter->is_request && !isset($filter->request_key)) {
                // For now, just warn in logs instead of throwing exception
                if (config('app.debug')) {
                    Log::warning("Required request filter '{$filter->id}' missing request_key field");
                }
            }
            
            if (!$filter->relation && !$filter->export_model_relation_id) {
                throw new \Exception("Required filter '{$filter->id}' has no relation configured");
            }
        }

        // Check for column filters with missing relations
        foreach ($layout->columns as $column) {
            if ($column->export_filter_id && !$column->filter) {
                throw new \Exception("Column '{$column->title}' references non-existent filter ID: {$column->export_filter_id}");
            }
        }
    }

    /**
     * Debug relation loading
     */
    protected function debugRelationLoading(Model $model, ExportColumn $column): void
    {
        if (config('app.debug')) {
            Log::info("Processing column: {$column->title}");
            Log::info("Model relation ID: " . ($column->export_model_relation_id ?? 'none'));
            Log::info("Relation path: " . ($column->modelRelation->relation ?? 'none'));
            Log::info("Value path: {$column->value_path}");
            
            if ($column->modelRelation) {
                $relationPath = $column->modelRelation->relation;
                $relatedData = data_get($model, $relationPath);
                Log::info("Related data exists: " . ($relatedData ? 'yes' : 'no'));
                Log::info("Related data type: " . gettype($relatedData));
                
                if (is_object($relatedData)) {
                    Log::info("Related data class: " . get_class($relatedData));
                    if ($relatedData instanceof \Illuminate\Database\Eloquent\Collection) {
                        Log::info("Collection count: " . $relatedData->count());
                    }
                }
            }
        }
    }

    /**
     * Improved eager loading that handles nested paths
     */
    protected function buildEagerLoadArray(): array
    {
        $with = [];

        foreach ($this->columns as $column) {
            if ($column->export_model_relation_id && $column->modelRelation) {
                $relationPath = $column->modelRelation->relation;
                
                // Add the direct relation
                $with[] = $relationPath;

                // For nested paths in value_path, ensure all intermediate relations are loaded
                if ($column->value_path && Str::contains($column->value_path, '.')) {
                    $parts = explode('.', $column->value_path);
                    $currentPath = '';
                    
                    foreach ($parts as $i => $part) {
                        if ($i === 0) {
                            $currentPath = $part;
                        } else {
                            $currentPath .= '.' . $part;
                        }
                        
                        // Don't add the final attribute, only relations
                        if ($i < count($parts) - 1) {
                            $with[] = $currentPath;
                        }
                    }
                }
            }
        }

        // Add relations from filters (but not direct columns)
        foreach ($this->filters as $filter) {
            if ($filter->relation && !$filter->relation->is_column) {
                $with[] = $filter->relation->relation;
            }
        }
        

        return array_unique($with);
    }
}
