<?php

namespace HexagonLabsLLC\LaravelExports\Services;

use HexagonLabsLLC\LaravelExports\Enums\OperatorType;
use HexagonLabsLLC\LaravelExports\Exports\ExportFactory;
use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Jobs\ProcessExportJob;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    protected array $validatedPaths = [];

    protected ?ModelRelationInspector $inspector = null;

    protected ?SchemaSync $schemaSync = null;

    public function __construct(?ModelRelationInspector $inspector = null, ?SchemaSync $schemaSync = null)
    {
        $this->initializeCollections();
        $this->inspector = $inspector ?? app(ModelRelationInspector::class);
        $this->schemaSync = $schemaSync ?? app(SchemaSync::class);
    }

    protected function initializeCollections(): void
    {
        $this->filters = collect();
        $this->sorts = collect();
        $this->columns = collect();
        $this->relations = collect();
        $this->validatedPaths = [];
    }

    /**
     * Execute an export based on a layout
     */
    public function executeExport(ExportLayout|string $layout, array $requestData = []): Collection
    {
        $this->loadLayout($layout);
        $this->requestData = $requestData;

        // Check if this is a pivot layout
        if ($this->isPivotLayout()) {
            return $this->executePivotExport();
        }

        // Standard export flow
        $query = $this->buildQuery();
        $query = $this->applyFilters($query);
        $query = $this->applyEagerLoading($query);
        $query = $this->applySorts($query);
        $results = $query->get();

        $this->expandColumns($results);

        return $this->processResults($results);
    }

    /**
     * Whether any loaded column expands into generated columns.
     */
    protected function hasExpandedColumns(): bool
    {
        return $this->columns->contains(fn ($column) => $column->is_expanded && $column->modelRelation);
    }

    /**
     * Expand is_expanded collection columns into one generated column per
     * distinct related value across the result set. Generated columns reuse
     * the relation-operator filter extraction path, so each cell is the
     * column's value_path aggregated over the row's matching related items.
     */
    protected function expandColumns(EloquentCollection $results): void
    {
        if (!$this->hasExpandedColumns()) {
            return;
        }

        $this->columns = $this->columns->flatMap(function ($column) use ($results) {
            if (!$column->is_expanded || !$column->modelRelation) {
                return [$column];
            }

            $relationPath = $column->modelRelation->relation;
            $headerPath = $column->expansion_data['header_path'] ?? 'name';

            if (Str::contains($headerPath, '.')) {
                $results->loadMissing($relationPath.'.'.Str::beforeLast($headerPath, '.'));
            }

            $headers = $results
                ->flatMap(fn ($model) => collect(data_get($model, $relationPath) ?? [])
                    ->map(fn ($item) => data_get($item, $headerPath)))
                ->filter()
                ->unique()
                ->sort()
                ->values();

            return $headers->map(function ($header) use ($column, $relationPath, $headerPath) {
                $generated = $column->replicate();
                $generated->title = $column->format !== null
                    ? str_replace('{value}', (string)$header, $column->format)
                    : (string)$header;
                $generated->format = null;
                $generated->is_expanded = false;
                $generated->setRelation('modelRelation', $column->modelRelation);
                $generated->setRelation('exportFunction', $column->exportFunction);
                $generated->setRelation('filter', new ExportFilter([
                    'operator' => 'relation',
                    'value' => [$relationPath, $headerPath, '=', $header],
                    'value_type' => 'array',
                ]));

                return $generated;
            });
        })->values();
    }

    /**
     * Check if current layout is a pivot export
     */
    protected function isPivotLayout(): bool
    {
        return (bool)$this->layout?->isPivot();
    }

    /**
     * Load layout and related data
     */
    protected function loadLayout(ExportLayout|string $layout): void
    {
        // Reset collections to prevent memory leaks when service is reused (singleton)
        $this->initializeCollections();

        if (is_string($layout)) {
            $layout = ExportLayout::find($layout);
        }

        if (!$layout) {
            throw new \Exception('Layout not found');
        }

        $this->layout = $layout;
        // Load related data; a layout's model FQCN lazy-syncs the catalog
        $this->exportModel = $layout->resolveExportModel();
        // Load columns with their relationships (including functions and filters)
        $this->columns = $layout->columns()
            ->with(['modelRelation', 'exportFunction', 'filter.modelRelation'])
            ->orderBy('position')
            ->get();

        // Layouts can also carry column definitions in their column_definitions
        // JSON field; merge those in by position
        $definedColumns = $layout->buildDefinedColumns();
        if ($definedColumns->isNotEmpty()) {
            $this->columns = $this->columns->concat($definedColumns)->sortBy('position')->values();
        }
        // Get column filter IDs to exclude from layout filters
        $columnFilterIds = $layout->columns()->whereNotNull('export_filter_id')->pluck('export_filter_id');
        // Only load filters that are NOT attached to columns
        $this->filters = $layout->filters()
            ->with(['modelRelation'])
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

        if (!class_exists($modelClass)) {
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
            $possibleKeys = $this->getPossibleRequestKeys($columnName, $requestFilter->id);

            foreach ($possibleKeys as $key) {
                if (isset($this->requestData[$key])) {
                    $activeRequestParams[] = $columnName;
                    break;
                }
            }
        }

        // Apply all filters with conflict resolution
        $this->filters->each(function (ExportFilter $filter) use (&$query, $activeRequestParams) {
            // Get the column name from the relation
            $columnName = $this->getFilterColumnName($filter);
            // Skip static filters if there's an active request filter for the same parameter
            if (!$filter->is_request && in_array($columnName, $activeRequestParams)) {

                return; // Skip this static filter
            }
            // For request filters, try multiple ways to get the parameter value
            if ($filter->is_request) {
                $value = null;
                // Try different parameter name patterns
                $possibleKeys = $this->getPossibleRequestKeys($columnName, $filter->id);

                foreach ($possibleKeys as $key) {
                    if (isset($this->requestData[$key])) {
                        $value = $this->requestData[$key];

                        // Handle array values for operators that expect arrays
                        if (in_array($filter->operator, ['in', 'not_in', 'between']) && is_string($value)) {
                            if (strpos($value, ',') !== false) {
                                $value = array_map('trim', explode(',', $value));
                            } else {
                                $value = [$value];
                            }
                        }

                        break;
                    }
                }
                // Skip if required but not provided
                if ($filter->is_required && $value === null) {
                    throw new \Exception("Required filter '{$columnName}' not provided in request");
                }
            } else {
                $value = $this->decodeFilterValue($filter);
            }
            // Skip if no value and not checking for null
            if ($value === null && !in_array($filter->operator, ['null', 'not_null'])) {
                return;
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
        // Case-insensitive: the enum column stores lowercase 'or' on MySQL
        $isOr = strcasecmp((string)$filter->logical_operator, 'or') === 0;
        $columnName = $this->getFilterColumnName($filter);

        // Special handling for relation operator
        if ($filter->operator === 'relation') {
            // For relation operator, the value structure is different
            // It expects [relation, column, operator, value]
            $this->applyOperator($query, $columnName, $filter->operator, $value, $isOr);
        } elseif ($filter->export_model_relation_id && $filter->modelRelation) {
            // Check if this is a direct column filter or relationship filter
            $isDirectColumn = isset($filter->modelRelation->is_column) && $filter->modelRelation->is_column;

            if ($isDirectColumn && str_contains($filter->modelRelation->relation, '.')) {
                // Smart parsing for nested column relations
                $this->applySmartRelationFilter($query, $filter, $value, $isOr);
            } elseif ($isDirectColumn) {
                // Direct column filtering - use the relation name as column name
                $directColumn = $filter->modelRelation->relation;

                $this->applyOperator($query, $directColumn, $filter->operator, $value, $isOr);
            } else {
                // Relationship filtering - use whereHas
                $relation = $filter->modelRelation->relation;
                $column = $filter->modelRelation->column ?? $columnName;

                // For relationship filters, use whereHas
                $method = $isOr ? 'orWhereHas' : 'whereHas';

                $query->$method($relation, function ($q) use ($filter, $column, $value) {
                    $this->applyOperator($q, $column, $filter->operator, $value, false);
                });
            }
        } else {
            // Direct column filter on main model

            $this->applyOperator($query, $columnName, $filter->operator, $value, $isOr);
        }
    }

    /**
     * Decode a configured filter value according to its value_type and operator.
     * Static values are stored as text; array-shaped operators need real arrays.
     */
    protected function decodeFilterValue(ExportFilter $filter)
    {
        $value = $filter->value;

        if (is_string($value) && ($filter->value_type === 'array' || in_array($filter->operator, ['in', 'not_in', 'between', 'relation'], true))) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_map('trim', explode(',', $value));
        }

        return $value;
    }

    /**
     * Get the column name for a filter
     */
    protected function getFilterColumnName(ExportFilter $filter): string
    {
        if ($filter->export_model_relation_id && $filter->modelRelation) {
            // If filter has a relation, use the relation's name
            // The relation field contains the column path (e.g., "status" or "customer.name")
            return $filter->modelRelation->relation;
        }
        // For request filters without a relation, fall back to the model's primary key
        if ($filter->is_request && $this->exportModel) {
            $modelClass = $this->exportModel->model;
            if (class_exists($modelClass)) {
                return (new $modelClass)->getKeyName();
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
                if (!is_array($value) || count($value) !== 2) {
                    throw new \Exception('Between operator requires array with 2 values');
                }
                $query->$method($column, $value);
                break;

            case 'in':
            case 'not_in':
                // Expects array
                if (!is_array($value)) {
                    $value = [$value];
                }
                $query->$method($column, $value);
                break;

            case 'null':
            case 'not_null':
                // No value needed
                $query->$method($column);
                break;

            case 'like':
                // whereLike expects (column, value), not (column, operator, value)
                $query->$method($column, $value);
                break;

            case 'json_contains':
                // JSON contains - expects column and value
                $query->$method($column, $value);
                break;

            case 'relation':
                // Relation operator - expects relation name, column, operator, and value
                // The value should be an array with [relation, column, operator, value]
                if (!is_array($value) || count($value) < 3) {
                    throw new \Exception('Relation operator requires array with [relation, column, operator, value]');
                }
                [$relation, $relColumn, $relOperator, $relValue] = array_pad($value, 4, null);
                $query->$method($relation, $relColumn, $relOperator, $relValue);
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

            if (!$filter) {
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
                $found = false;
                $possibleKeys = $this->getPossibleRequestKeys($columnName, $columnFilter->id);
                foreach ($possibleKeys as $key) {
                    if (isset($this->requestData[$key])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new \Exception("Required column filter '{$columnName}' not provided in request");
                }
            }
            // Get the value (from request or filter configuration)
            if ($columnFilter->is_request) {
                $value = null;
                $possibleKeys = $this->getPossibleRequestKeys($columnName, $columnFilter->id);
                foreach ($possibleKeys as $key) {
                    if (isset($this->requestData[$key])) {
                        $value = $this->requestData[$key];
                        break;
                    }
                }
            } else {
                $value = $this->decodeFilterValue($columnFilter);
            }
            // Skip if no value and not checking for null
            if ($value === null && !in_array($columnFilter->operator, ['null', 'not_null'])) {
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
                        if ($filter->operator === 'relation' && $filter->modelRelation && !$filter->modelRelation->is_column) {
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
            if ($filter->modelRelation && !$filter->modelRelation->is_column) {
                $relationPath = $filter->modelRelation->relation;
                if (Str::contains($relationPath, '.')) {
                    $this->addNestedRelationPaths($relationPath, $relationsToLoad);
                } else {
                    $relationsToLoad[] = $relationPath;
                }
            }
        });
        // Apply regular relations (with pivot columns where needed)
        if (!empty($relationsToLoad)) {
            $uniqueRelations = array_unique($relationsToLoad);
            // Sort by depth to ensure parent relations are loaded before children
            usort($uniqueRelations, function ($a, $b) {
                return substr_count($a, '.') <=> substr_count($b, '.');
            });

            // Check for relations that need pivot columns
            $withPivot = $this->getRelationsWithPivot($uniqueRelations);

            if (!empty($withPivot)) {
                // Load relations with pivot columns using closures
                $relationsWithCallbacks = [];
                $simpleRelations = [];

                foreach ($uniqueRelations as $relationPath) {
                    if (isset($withPivot[$relationPath])) {
                        $pivotColumns = $withPivot[$relationPath];
                        $relationsWithCallbacks[$relationPath] = function ($q) use ($pivotColumns) {
                            $q->withPivot($pivotColumns);
                        };
                    } else {
                        $simpleRelations[] = $relationPath;
                    }
                }

                if (!empty($simpleRelations)) {
                    $query->with($simpleRelations);
                }
                if (!empty($relationsWithCallbacks)) {
                    $query->with($relationsWithCallbacks);
                }
            } else {
                $query->with($uniqueRelations);
            }
        }
        // Apply constrained relations. Load the relation without SQL constraints -
        // the relation-operator filters are applied at the PHP level in
        // extractCollectionValue(). Eager-load any sub-relations the PHP filter
        // will compare against to avoid N+1 queries.
        $constrainedRelationsToLoad = [];
        foreach ($constrainedRelations as $relationPath => $constraints) {
            $constrainedRelationsToLoad[] = $relationPath;
            foreach ($constraints as $constraint) {
                $filter = $constraint['filter'];
                if ($filter->modelRelation && !$filter->modelRelation->is_column) {
                    $constrainedRelationsToLoad[] = $relationPath.'.'.$filter->modelRelation->relation;
                }
            }
        }
        if (!empty($constrainedRelationsToLoad)) {
            $query->with(array_unique($constrainedRelationsToLoad));
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
        if (!$column->modelRelation) {
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
            $find = fn () => $currentModel->relations()
                ->where('relation', $part)
                ->where('is_column', false)
                ->first();

            $relation = $find();

            // The sync-as-referenced cascade: a related model whose catalog is
            // empty (e.g. a stub row) syncs on first traversal
            if (!$relation && $this->schemaSync->canSync()) {
                $this->schemaSync->syncOnce($currentModel->model);
                $relation = $find();
            }

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
     * Validate a referenced nested path and lazily sync it into the catalog
     * per the schema_sync mode (manual mode never writes).
     */
    protected function validateAndCreateNestedPath(string $path): void
    {
        if (!$this->exportModel) {
            return;
        }

        // Memoized per loadLayout: this runs per row otherwise and each miss costs a query
        if (isset($this->validatedPaths[$path])) {
            return;
        }
        $this->validatedPaths[$path] = true;

        $exists = ExportModelRelation::where('export_model_id', $this->exportModel->id)
            ->where('relation', $path)
            ->exists();

        if ($exists || !$this->schemaSync->canSync()) {
            return;
        }

        $row = $this->schemaSync->syncPath($this->exportModel, $path);

        if (!$row) {
            Log::warning("Invalid nested path detected: {$path}", [
                'model' => $this->exportModel->model,
            ]);

            return;
        }

        $this->relations->push($row);
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

        $this->validateAndCreateNestedPath(implode('.', $parts));
    }

    /**
     * Apply sorts to the query
     */
    protected function applySorts(Builder $query): Builder
    {
        $this->sorts->each(function (ExportSort $sort) use (&$query) {
            if ($sort->export_model_relation_id && $sort->modelRelation && !$sort->modelRelation->is_column) {
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

        if (!$relation) {
            return;
        }

        // Handle different relationship types
        $relationParts = explode('.', $relation->relation);
        $immediateRelation = $relationParts[0];

        // Check if this is a direct relation or nested
        if (count($relationParts) === 1) {
            // Direct relation - we can use orderByRelation for BelongsTo and HasOne
            if (!$relation->is_collection) {
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

        if ($relation instanceof BelongsTo) {
            $relatedTable = $relation->getRelated()->getTable();
            $foreignKey = $relation->getForeignKeyName();
            $ownerKey = $relation->getOwnerKeyName();

            $query->leftJoin($relatedTable, $query->getModel()->getTable().'.'.$foreignKey, '=', $relatedTable.'.'.$ownerKey)
                ->orderBy($relatedTable.'.'.$this->getRelationSortColumn($sort), $sort->direction)
                ->select($query->getModel()->getTable().'.*');
        } elseif ($relation instanceof HasOne) {
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
        }])->orderBy(Str::snake($relationName).'_count', $sort->direction);
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
            $query->getModel()->{$parts[0]}()
                ->getRelated()
                ->newQuery()
                ->whereColumn(
                    $query->getModel()->getTable().'.'.$query->getModel()->getKeyName(),
                    $query->getModel()->{$parts[0]}()->getForeignKeyName()
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

                // Aggregate first so functions receive the scalar (e.g. format a sum as currency)
                if ($column->aggregator && is_iterable($value)) {
                    $value = $this->applyAggregation($value, $column->aggregator);
                }

                // Apply function if configured
                if ($column->export_function_id && $column->exportFunction) {
                    $value = $this->applyColumnFunction($value, $column);
                }

                // Use default if value is null, empty string, empty array, empty collection,
                // or a Model object (which indicates extraction failed to get a specific attribute)
                $isEmpty = $value === null
                    || $value === ''
                    || (is_array($value) && empty($value))
                    || ($value instanceof Collection && $value->isEmpty())
                    || ($value instanceof Model);

                if ($isEmpty) {
                    $value = $this->getColumnDefault($column);
                }

                // Wrap non-empty values in the column's format template
                if ($column->format !== null && $value !== '' && is_scalar($value)) {
                    $value = str_replace('{value}', (string)$value, $column->format);
                }

                // Apply override if present (takes precedence over everything)
                $override = $this->getColumnOverride($column);
                if ($override !== null) {
                    $value = $override;
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
        // If no relation, get direct attribute
        if (!$column->export_model_relation_id) {
            return data_get($model, $column->value_path);
        }

        // Check if this column has a filter that constrained the relation
        // (persisted via export_filter_id, or synthesized by expandColumns)
        if ($column->filter && $column->filter->operator === 'relation') {
            return $this->extractCollectionValue($model, $column);
        }

        // Handle regular relation traversal
        return $this->resolveRelationValue($model, $column);
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

        // Check for pivot path notation (e.g., 'roles.pivot.assigned_at')
        if ($valuePath && $this->containsPivotPath($valuePath)) {
            return $this->extractPivotValueFromPath($model, $valuePath);
        }

        // If relationPath already contains dots, it's a nested relation
        if (Str::contains($relationPath, '.')) {
            // Try to get the value directly using the relation path
            $relatedData = data_get($model, $relationPath);

            if ($relatedData !== null && $valuePath) {
                // If we have a value path, it might be an attribute on the final relation
                // First check if valuePath starts with relationPath
                if (Str::startsWith($valuePath, $relationPath.'.')) {
                    // Get the attribute part after the relation
                    $attribute = Str::after($valuePath, $relationPath.'.');

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
                        $remaining = implode('.', $remainingPath);

                        // Pluck across collections so 'orders.total' with an aggregator works
                        if ($baseRelation instanceof Collection) {
                            return $baseRelation->map(fn ($item) => data_get($item, $remaining))->values();
                        }

                        return data_get($baseRelation, $remaining);
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
                if ($relatedData instanceof Collection) {
                    return $relatedData->map(fn ($item) => data_get($item, $valuePath))->values();
                }
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
            $fallback = $this->extractFallbackAttribute($relationData);
            if ($fallback !== $relationData) {
                return $fallback;
            }

            if (method_exists($relationData, '__toString')) {
                return (string)$relationData;
            }
        }

        return $relationData;
    }

    /**
     * Return the first configured fallback attribute set on the object, or the object itself.
     */
    protected function extractFallbackAttribute(object $object)
    {
        foreach (config('laravel-exports.fallback_attributes', ['name', 'title', 'value', 'label']) as $attr) {
            if (isset($object->$attr)) {
                return $object->$attr;
            }
        }

        return $object;
    }

    /**
     * Compare a collection item's value against an expected value, tolerating
     * type differences (numeric strings vs ints) the way hydrated models produce them.
     */
    protected function compareValues($actual, $expected, string $operator = '='): bool
    {
        $matches = $actual === $expected
            || $actual == $expected
            || (is_numeric($actual) && is_numeric($expected) && (string)$actual === (string)$expected);

        return $operator === '!=' ? !$matches : $matches;
    }

    /**
     * Extract specific values from collections based on filters
     */
    protected function extractCollectionValue(Model $model, ExportColumn $column)
    {
        if (!$column->filter) {
            return $this->getColumnDefault($column);
        }

        $filter = $column->filter;
        $relationPath = $column->modelRelation->relation;

        // Validate nested paths if needed
        if (Str::contains($relationPath, '.')) {
            $this->validateValuePath($relationPath);
        }

        $collection = data_get($model, $relationPath);

        if (!$collection || !is_iterable($collection)) {
            return $this->getColumnDefault($column);
        }

        // Convert to collection if it's not already
        if (!($collection instanceof Collection)) {
            $collection = collect($collection);
        }

        // Filter the collection based on the relation operator filter
        $filtered = $collection->filter(function ($item) use ($filter, $column) {
            // For relation operator filters
            if ($filter->operator === 'relation' && $filter->value) {
                $filterConfig = is_string($filter->value) ? json_decode($filter->value, true) : $filter->value;

                if (is_array($filterConfig) && count($filterConfig) >= 4) {
                    // Format: ['workItem.values', 'type.title', '=', 'Splits']
                    $actualValue = data_get($item, $filterConfig[1]);

                    if ($actualValue instanceof Model) {
                        $actualValue = $this->extractFallbackAttribute($actualValue);
                    }

                    return $this->compareValues($actualValue, $filterConfig[3], $filterConfig[2] ?? '=');
                }
            }

            // Fallback for other filter types
            if (!$filter->modelRelation) {
                return false;
            }

            $filterRelation = $filter->modelRelation->relation;
            $expectedValue = $column->export_filter_values ?? $filter->value;
            $actualValue = data_get($item, $filterRelation);

            if ($actualValue instanceof Model) {
                $actualValue = $this->extractFallbackAttribute($actualValue);
            }

            return $this->compareValues($actualValue, $expectedValue);
        });

        if ($filtered->isEmpty()) {
            return $column->aggregator ? collect() : $this->getColumnDefault($column);
        }

        // Aggregator-bound columns get every matching value so count/sum/avg work;
        // otherwise keep first-match extraction
        if ($column->aggregator) {
            return $filtered->map(fn ($item) => $this->extractItemValue($item, $column, $relationPath))->values();
        }

        return $this->extractItemValue($filtered->first(), $column, $relationPath);
    }

    /**
     * Extract the configured value from a single matched collection item.
     */
    protected function extractItemValue($firstMatch, ExportColumn $column, string $relationPath)
    {
        $extractedValue = null;

        if ($column->value_path) {
            if (Str::contains($column->value_path, '.')) {
                // Complex path handling
                if (Str::startsWith($column->value_path, $relationPath.'.')) {
                    // Get the attribute part after the relation path
                    $attribute = Str::after($column->value_path, $relationPath.'.');
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

        return $extractedValue;
    }

    /**
     * Apply function to column value
     */
    protected function applyColumnFunction($value, ExportColumn $column)
    {
        $function = $column->exportFunction;
        $callable = $function->callable;

        // export_function_values is cast to array on the model
        $values = $column->export_function_values ?? [];

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
                return call_user_func_array($callable, $params);
            } catch (\Throwable $e) {
                // Log function execution errors
                Log::error('Function execution failed:', [
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
            Log::warning('Function not callable:', [
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
        if (!is_iterable($values)) {
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

        // Handle pivot layouts (no chunking - pivot exports are aggregated)
        if ($this->isPivotLayout()) {
            $results = $this->executePivotExport();
            if ($callback) {
                $callback($results);
            }

            return;
        }

        // Expanded columns derive their column set from the full dataset,
        // which the first chunk cannot know
        if ($this->hasExpandedColumns()) {
            throw new \RuntimeException('Expanded columns require a full-dataset export; chunked and queued exports are not supported yet');
        }

        // Standard chunked export flow
        $query = $this->buildQuery();
        $query = $this->applyFilters($query);
        $query = $this->applyEagerLoading($query);
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

        if ($this->hasExpandedColumns()) {
            throw new \RuntimeException('Expanded columns require a full-dataset export; paginated exports are not supported yet');
        }

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
        // executeExport loads the layout; $this->layout is set for the handler
        $data = $this->executeExport($layout, $requestData);

        $handler = ExportFactory::create($format, $this->layout, $options);

        return $handler->export($data);
    }

    /**
     * Export to a specific format and download
     */
    public function downloadAs(ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = []): Response
    {
        $content = $this->exportTo($layout, $format, $requestData, $options);

        $handler = ExportFactory::create($format, $this->layout, $options);

        return $handler->download($content, $filename);
    }

    /**
     * Export to a specific format and store
     */
    public function storeAs(ExportLayout|string $layout, string $format, string $path, array $requestData = [], array $options = []): bool
    {
        $content = $this->exportTo($layout, $format, $requestData, $options);

        $handler = ExportFactory::create($format, $this->layout, $options);

        return $handler->store($content, $path);
    }

    /**
     * Stream export for large datasets
     */
    public function streamAs(ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = [], int $chunkSize = 1000): Response|StreamedResponse
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

        // A required filter without a relation is silently unloadable through the
        // filters() relation (it scopes on export_model_relation_id), so check the table directly
        $misconfigured = ExportFilter::where('export_layout_id', $layout->id)
            ->where('is_required', true)
            ->whereNull('export_model_relation_id')
            ->first();

        if ($misconfigured) {
            throw new \Exception("Required filter '{$misconfigured->id}' has no relation configured");
        }

        // Check for column filters with missing relations
        foreach ($layout->columns as $column) {
            if ($column->export_filter_id && !$column->filter) {
                throw new \Exception("Column '{$column->title}' references non-existent filter ID: {$column->export_filter_id}");
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
                // Skip direct columns - they are model attributes, not Eloquent relationships
                if ($column->modelRelation->is_column) {
                    continue;
                }

                $relationPath = $column->modelRelation->relation;

                // Add the direct relation
                $with[] = $relationPath;

                // For nested value_paths, load intermediate relations, but only when the
                // path starts at the root model; relation-relative paths (e.g. 'category.name'
                // on a tag) would crash eager loading with a RelationNotFoundException
                if ($column->value_path && Str::contains($column->value_path, '.')
                    && $this->isRootRelation(explode('.', $column->value_path)[0])) {
                    $parts = explode('.', $column->value_path);
                    $currentPath = '';

                    foreach ($parts as $i => $part) {
                        if ($i === 0) {
                            $currentPath = $part;
                        } else {
                            $currentPath .= '.'.$part;
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
            if ($filter->modelRelation && !$filter->modelRelation->is_column) {
                $with[] = $filter->modelRelation->relation;
            }
        }

        return array_unique($with);
    }

    /**
     * Whether the given name is a non-column relation on the export model.
     */
    protected function isRootRelation(string $name): bool
    {
        return $this->relations->contains(fn ($r) => !$r->is_column && $r->relation === $name);
    }

    /**
     * Get possible request parameter key variations
     */
    protected function getPossibleRequestKeys(string $columnName, ?string $filterId = null): array
    {
        $keys = [
            $columnName,
            strtolower($columnName),
            Str::snake($columnName),
            str_replace('.', '_', $columnName), // Handle dots replaced with underscores
            str_replace('.', '_', Str::snake($columnName)), // Snake case with underscores
        ];

        // If filter ID is provided, add variations with the filter ID
        if ($filterId) {
            $keys[] = $filterId; // Direct filter ID
            $keys[] = str_replace('-', '_', $filterId); // Filter ID with underscores instead of dashes
        }

        return array_unique($keys);
    }

    /**
     * Get the default value for a column, checking request overrides first.
     */
    protected function getColumnDefault(ExportColumn $column): string
    {
        // Request-based default override by column ID takes priority
        if (isset($this->requestData['defaults'][$column->id])) {
            return $this->requestData['defaults'][$column->id];
        }

        return $column->default ?? '';
    }

    /**
     * Get the override value for a column if one exists in the request.
     * Overrides always replace the value, regardless of whether it's empty.
     */
    protected function getColumnOverride(ExportColumn $column): ?string
    {
        if (isset($this->requestData['overrides'][$column->id])) {
            return $this->requestData['overrides'][$column->id];
        }

        return null;
    }

    /**
     * Apply smart relation filter that parses nested column relations
     */
    protected function applySmartRelationFilter(Builder $query, ExportFilter $filter, $value, bool $isOr): void
    {
        $relationPath = $filter->modelRelation->relation;
        $segments = explode('.', $relationPath);

        // Since is_column = true, last segment is the column
        $column = array_pop($segments);
        $relation = implode('.', $segments);

        // Special handling for 'in' and 'not_in' operators with nested relations
        if (in_array($filter->operator, ['in', 'not_in']) && str_contains($relation, '.')) {
            $this->applyNestedWhereHas($query, $relation, $column, $filter->operator, $value, $isOr);
        } else {
            // Simple single-level relation or operators that work fine with whereRelation
            $method = $isOr ? 'orWhereHas' : 'whereHas';
            $query->$method($relation, function ($q) use ($column, $filter, $value) {
                $this->applyOperator($q, $column, $filter->operator, $value, false);
            });
        }
    }

    /**
     * Apply nested whereHas for operators that need it (like 'in' with nested relations)
     */
    protected function applyNestedWhereHas(Builder $query, string $relationPath, string $column, string $operator, $value, bool $isOr = false): void
    {
        $relations = explode('.', $relationPath);
        $method = $isOr ? 'orWhereHas' : 'whereHas';

        // Build nested whereHas
        $query->$method(array_shift($relations), function ($q) use ($relations, $column, $operator, $value) {
            $this->buildNestedQuery($q, $relations, $column, $operator, $value);
        });
    }

    /**
     * Recursively build nested whereHas queries
     */
    protected function buildNestedQuery(Builder $query, array $remainingRelations, string $column, string $operator, $value): void
    {
        if (empty($remainingRelations)) {
            // We've reached the final level, apply the operator
            $this->applyOperator($query, $column, $operator, $value, false);
        } else {
            // Continue nesting
            $query->whereHas(array_shift($remainingRelations), function ($q) use ($remainingRelations, $column, $operator, $value) {
                $this->buildNestedQuery($q, $remainingRelations, $column, $operator, $value);
            });
        }
    }

    /**
     * Get relations that have pivot columns defined.
     *
     * @return array<string, array> Map of relation path to pivot columns
     */
    protected function getRelationsWithPivot(array $relationPaths): array
    {
        $withPivot = [];

        foreach ($relationPaths as $relationPath) {
            // Get the first segment of the path (the immediate relation)
            $segments = explode('.', $relationPath);
            $firstSegment = $segments[0];

            // Check if this relation has pivot columns in our model relations
            $modelRelation = ExportModelRelation::where('export_model_id', $this->exportModel->id)
                ->where('relation', $firstSegment)
                ->where('is_column', false)
                ->where('has_pivot', true)
                ->first();

            if ($modelRelation && !empty($modelRelation->pivot_columns)) {
                $withPivot[$firstSegment] = $modelRelation->pivot_columns;
            }
        }

        return $withPivot;
    }

    /**
     * Extract a pivot attribute value from a model.
     */
    protected function extractPivotValue(Model $item, string $attribute): mixed
    {
        return data_get($item, 'pivot.'.$attribute);
    }

    /**
     * Check if a value path contains a pivot reference.
     */
    protected function containsPivotPath(string $valuePath): bool
    {
        return Str::contains($valuePath, '.pivot.');
    }

    /**
     * Parse a pivot path into relation path and pivot attribute.
     *
     * @return array{relation: string, attribute: string}|null
     */
    protected function parsePivotPath(string $valuePath): ?array
    {
        if (!$this->containsPivotPath($valuePath)) {
            return null;
        }

        // Split on '.pivot.'
        $parts = explode('.pivot.', $valuePath, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'relation' => $parts[0],
            'attribute' => $parts[1],
        ];
    }

    /**
     * Extract pivot value from a collection or single related item.
     */
    protected function extractPivotValueFromPath(Model $model, string $valuePath): mixed
    {
        $parsed = $this->parsePivotPath($valuePath);

        if (!$parsed) {
            return null;
        }

        $relationPath = $parsed['relation'];
        $pivotAttribute = $parsed['attribute'];

        // Get the related data
        $related = data_get($model, $relationPath);

        if ($related === null) {
            return null;
        }

        // Handle collection of related items
        if ($related instanceof EloquentCollection || $related instanceof Collection) {
            return $related->map(function ($item) use ($pivotAttribute) {
                return $this->extractPivotValue($item, $pivotAttribute);
            });
        }

        // Handle single related item
        if ($related instanceof Model) {
            return $this->extractPivotValue($related, $pivotAttribute);
        }

        return null;
    }

    /**
     * Queue an export for background processing.
     *
     * @return string Export ID for status tracking
     */
    public function queueExport(
        ExportLayout|string $layout,
        string $format = 'csv',
        array $requestData = [],
        array $options = []
    ): string {
        // Get layout ID
        $layoutId = $layout instanceof ExportLayout ? $layout->id : $layout;

        // Generate export ID
        $exportId = (string)Str::uuid();

        // Get config values
        $queue = config('laravel-exports.queue', 'exports');
        $chunkSize = config('laravel-exports.chunk_size', 1000);
        $disk = $options['disk'] ?? config('laravel-exports.disk', 'local');
        $path = $options['path'] ?? config('laravel-exports.path', 'exports');

        // Dispatch the job
        ProcessExportJob::dispatch(
            $exportId,
            $layoutId,
            $format,
            $requestData,
            $options,
            $chunkSize,
            $disk,
            $path
        )->onQueue($queue);

        return $exportId;
    }

    /**
     * Get the status of a queued export.
     */
    public function getExportStatus(string $exportId): ?array
    {
        return ProcessExportJob::getStatus($exportId);
    }

    /**
     * Execute pivot export with dynamic configuration
     */
    protected function executePivotExport(): Collection
    {
        $config = $this->layout->getPivotConfig();

        // Build base query with filters
        $query = $this->buildQuery();
        $query = $this->applyFilters($query);

        // Build pivot query dynamically from config
        $pivotQuery = $this->buildPivotQuery($query, $config);

        // Execute and get raw aggregated data
        // Use toBase() to avoid Eloquent model casting (e.g., datetime casts interfering with formatted week strings)
        $rawData = $pivotQuery->toBase()->get();

        // Get pivot column from layout
        $pivotColumn = $this->columns->firstWhere('is_expanded', true);
        $pivotExpansionData = $pivotColumn ? ($pivotColumn->expansion_data ?? []) : [];

        // Determine dynamic columns
        $dynamicColumns = $this->determinePivotColumns($rawData, $config);

        // Transform to pivot format
        return $this->transformPivotResults($rawData, $dynamicColumns, $config, $pivotExpansionData);
    }

    /**
     * Build pivot query dynamically from configuration
     */
    protected function buildPivotQuery(Builder $baseQuery, array $config): Builder
    {
        $table = $this->exportModel->model::query()->getModel()->getTable();

        // Get relations from config and resolve joins dynamically
        $groupByRelations = $config['group_by'] ?? [];
        $subGroupByRelations = $config['sub_group_by'] ?? [];
        $pivotRelation = $config['pivot_relation'] ?? null;
        $valueRelation = ($config['value_relation'] ?? null) ?: $table;
        $valueColumn = $config['value_column'] ?? 'id';
        $aggregation = $config['aggregation'] ?? 'count';
        $groupByFormat = $config['group_by_format'] ?? null;

        // Build select and group by clauses dynamically
        $selects = [];
        $groupBys = [];

        // Add group by columns
        foreach ($groupByRelations as $relation) {
            $resolved = $this->resolvePivotRelationPath($relation);
            $baseQuery = $this->applyJoinForRelation($baseQuery, $relation);

            // Apply formatting if specified (e.g., week_year for dates)
            if ($groupByFormat === 'week_year') {
                // Check week start day preference (default is ISO Monday-start)
                $weekStart = $config['week_start'] ?? 'monday';

                if ($weekStart === 'sunday') {
                    // Use YEARWEEK with mode 0 for Sunday-Saturday weeks
                    // Format: YEARWEEK returns YYYYWW, convert to YYYY-Www
                    $selectExpr = "CONCAT(LEFT(YEARWEEK({$resolved['table']}.{$resolved['column']}, 0), 4), '-W', RIGHT(YEARWEEK({$resolved['table']}.{$resolved['column']}, 0), 2))";
                } else {
                    // Use DATE_FORMAT with %x (ISO year) and %v (ISO week) for Monday-Sunday weeks
                    // This correctly handles year boundaries (e.g., Dec 29-31 may be Week 1 of next year)
                    $selectExpr = "DATE_FORMAT({$resolved['table']}.{$resolved['column']}, '%x-W%v')";
                }

                $selects[] = DB::raw("{$selectExpr} as {$resolved['alias']}");
                $groupBys[] = $selectExpr; // Store as string for groupBy/orderBy
            } else {
                $selects[] = DB::raw("{$resolved['table']}.{$resolved['column']} as {$resolved['alias']}");
                $groupBys[] = "{$resolved['table']}.{$resolved['column']}";
            }
        }

        // Add sub-group columns
        foreach ($subGroupByRelations as $relation) {
            $resolved = $this->resolvePivotRelationPath($relation);
            $selects[] = DB::raw("{$resolved['table']}.{$resolved['column']} as {$resolved['alias']}");
            $groupBys[] = "{$resolved['table']}.{$resolved['column']}";
            $baseQuery = $this->applyJoinForRelation($baseQuery, $relation);
        }

        // Add pivot column
        if ($pivotRelation) {
            $pivotResolved = $this->resolvePivotRelationPath($pivotRelation);
            $pivotColumnName = $config['pivot_column'] ?? 'id';
            $selects[] = DB::raw("{$pivotResolved['table']}.id as pivot_id");
            $selects[] = DB::raw("{$pivotResolved['table']}.{$pivotColumnName} as pivot_value");
            $groupBys[] = "{$pivotResolved['table']}.id";
            $groupBys[] = "{$pivotResolved['table']}.{$pivotColumnName}";
            $baseQuery = $this->applyJoinForRelation($baseQuery, $pivotRelation);
        }

        // Add aggregation. When the value comes from a relation, join it and resolve
        // relation.column so the aggregate reads the related table, not the base one.
        $aggFunction = strtoupper($aggregation);
        if ($valueRelation === $table) {
            $selects[] = DB::raw("{$aggFunction}({$table}.{$valueColumn}) as aggregated_value");
        } else {
            $valuePath = $valueRelation.'.'.$valueColumn;
            $baseQuery = $this->applyJoinForRelation($baseQuery, $valuePath);
            $valueResolved = $this->resolvePivotRelationPath($valuePath);
            $selects[] = DB::raw("{$aggFunction}({$valueResolved['table']}.{$valueResolved['column']}) as aggregated_value");
        }

        return $baseQuery
            ->select($selects)
            ->groupByRaw(implode(', ', $groupBys))
            ->orderByRaw(implode(', ', $groupBys));
    }

    /**
     * Resolve a relation path to table/column/alias for pivot queries
     */
    protected function resolvePivotRelationPath(string $path): array
    {
        $parts = explode('.', $path);
        $model = $this->exportModel->model;

        $currentModel = new $model;
        $table = $currentModel->getTable();

        foreach ($parts as $index => $part) {
            if ($index === count($parts) - 1) {
                // Last part is the column
                return [
                    'table' => $table,
                    'column' => $part,
                    'alias' => str_replace('.', '_', $path),
                ];
            }

            // Navigate through relation
            if (method_exists($currentModel, $part)) {
                $relation = $currentModel->$part();
                $relatedModel = $relation->getRelated();
                $table = $relatedModel->getTable();
                $currentModel = $relatedModel;
            }
        }

        return [
            'table' => $table,
            'column' => end($parts),
            'alias' => str_replace('.', '_', $path),
        ];
    }

    /**
     * Apply join for a relation path in pivot queries
     */
    protected function applyJoinForRelation(Builder $query, string $path): Builder
    {
        $parts = explode('.', $path);
        $model = $this->exportModel->model;
        $currentModel = new $model;
        $previousTable = $currentModel->getTable();

        foreach ($parts as $index => $part) {
            // Stop before the last part (which is the column)
            if ($index === count($parts) - 1) {
                break;
            }

            if (method_exists($currentModel, $part)) {
                $relation = $currentModel->$part();
                $relatedModel = $relation->getRelated();
                $relatedTable = $relatedModel->getTable();

                // Check if already joined
                $existingJoins = $query->getQuery()->joins ?? [];
                $alreadyJoined = collect($existingJoins)->contains(function ($join) use ($relatedTable) {
                    return $join->table === $relatedTable;
                });

                if (!$alreadyJoined) {
                    // Determine join type and keys based on relation type
                    if ($relation instanceof BelongsTo) {
                        $foreignKey = $relation->getForeignKeyName();
                        $ownerKey = $relation->getOwnerKeyName();
                        $query->leftJoin($relatedTable, "{$previousTable}.{$foreignKey}", '=', "{$relatedTable}.{$ownerKey}");
                    } elseif ($relation instanceof HasOne ||
                              $relation instanceof HasMany) {
                        $foreignKey = $relation->getForeignKeyName();
                        $ownerKey = $relation->getLocalKeyName();
                        $query->leftJoin($relatedTable, "{$previousTable}.{$ownerKey}", '=', "{$relatedTable}.{$foreignKey}");
                    } elseif ($relation instanceof BelongsToMany) {
                        // Handle many-to-many through pivot table
                        $pivotTable = $relation->getTable();
                        $parentKey = $relation->getParentKeyName();
                        $foreignPivotKey = $relation->getForeignPivotKeyName();
                        $relatedPivotKey = $relation->getRelatedPivotKeyName();
                        $relatedKey = $relation->getRelatedKeyName();

                        // Join pivot table
                        $query->leftJoin($pivotTable, "{$previousTable}.{$parentKey}", '=', "{$pivotTable}.{$foreignPivotKey}");
                        // Join related table
                        $query->leftJoin($relatedTable, "{$pivotTable}.{$relatedPivotKey}", '=', "{$relatedTable}.{$relatedKey}");
                    }
                }

                $previousTable = $relatedTable;
                $currentModel = $relatedModel;
            }
        }

        return $query;
    }

    /**
     * Determine dynamic pivot columns from data
     */
    protected function determinePivotColumns(Collection $data, array $config): Collection
    {
        // If specific IDs are provided in request, filter to those
        $filterParam = $config['pivot_filter_param'] ?? null;
        if ($filterParam && !empty($this->requestData[$filterParam])) {
            $ids = is_array($this->requestData[$filterParam])
                ? $this->requestData[$filterParam]
                : array_map('trim', explode(',', $this->requestData[$filterParam]));

            // Get from pivot relation model
            $pivotRelation = $config['pivot_relation'] ?? null;
            if ($pivotRelation) {
                $pivotModel = $this->getModelFromRelation($pivotRelation);
                if ($pivotModel) {
                    return $pivotModel::whereIn('id', $ids)
                        ->orderBy($config['pivot_column'] ?? 'name')
                        ->pluck($config['pivot_column'] ?? 'name', 'id');
                }
            }
        }

        // Otherwise get from data
        return $data->pluck('pivot_value', 'pivot_id')->unique()->sort();
    }

    /**
     * Get the model class from a relation path
     */
    protected function getModelFromRelation(string $path): ?string
    {
        $parts = explode('.', $path);
        $model = $this->exportModel->model;
        $currentModel = new $model;

        foreach ($parts as $index => $part) {
            // Stop before the last part (which is the column)
            if ($index === count($parts) - 1) {
                break;
            }

            if (method_exists($currentModel, $part)) {
                $relation = $currentModel->$part();
                $currentModel = $relation->getRelated();
            } else {
                return null;
            }
        }

        return get_class($currentModel);
    }

    /**
     * Transform raw pivot data into output rows
     */
    protected function transformPivotResults(
        Collection $rawData,
        Collection $dynamicColumns,
        array $config,
        array $pivotExpansionData
    ): Collection {
        $groupBy = $config['group_by'] ?? [];
        $subGroupBy = $config['sub_group_by'] ?? [];

        // Get formatting function if specified
        $formatFunction = null;
        if (!empty($pivotExpansionData['format_function'])) {
            $formatFunction = ExportFunction::find($pivotExpansionData['format_function']);
        }

        // Build pivoted structure
        $pivoted = [];
        foreach ($rawData as $row) {
            // Build group key from group_by relations
            $groupKey = $this->buildPivotGroupKey($row, $groupBy);
            $subGroupKey = $this->buildPivotGroupKey($row, $subGroupBy);

            if (!isset($pivoted[$groupKey])) {
                $pivoted[$groupKey] = [];
            }

            if (!isset($pivoted[$groupKey][$subGroupKey])) {
                $pivoted[$groupKey][$subGroupKey] = [
                    'data' => $this->extractPivotGroupData($row, $subGroupBy),
                    'values' => [],
                    'total' => 0,
                ];
            }

            $value = (float)$row->aggregated_value;
            $pivotId = $row->pivot_id;
            $pivoted[$groupKey][$subGroupKey]['values'][$pivotId] = $value;

            // Only add to total if this pivot column is displayed
            if ($dynamicColumns->has($pivotId)) {
                $pivoted[$groupKey][$subGroupKey]['total'] += $value;
            }
        }

        // Convert to flat rows
        return $this->convertPivotToRows($pivoted, $dynamicColumns, $formatFunction, $config, $groupBy, $subGroupBy);
    }

    /**
     * Build group key from row and relation paths
     */
    protected function buildPivotGroupKey(object $row, array $relations): string
    {
        $parts = [];
        foreach ($relations as $relation) {
            $alias = str_replace('.', '_', $relation);
            $parts[] = $row->$alias ?? '';
        }

        // \x1f keeps group values containing '_' from splitting into the wrong columns
        return implode("\x1f", $parts);
    }

    /**
     * Extract group data from row
     */
    protected function extractPivotGroupData(object $row, array $relations): array
    {
        // Indexed by position so custom sub_group_by_headers still find their values
        $data = [];
        foreach ($relations as $relation) {
            $alias = str_replace('.', '_', $relation);
            $data[] = $row->$alias ?? '';
        }

        return $data;
    }

    /**
     * Convert pivoted data to output rows
     */
    protected function convertPivotToRows(
        array $pivoted,
        Collection $dynamicColumns,
        ?ExportFunction $formatFunction,
        array $config,
        array $groupBy,
        array $subGroupBy
    ): Collection {
        $rows = [];
        $outputFormat = $config['output_format'] ?? 'flat';
        $isGrouped = $outputFormat === 'grouped';

        // Build static column headers (use custom headers from config if provided)
        $customGroupHeaders = $config['group_by_headers'] ?? [];
        $customSubGroupHeaders = $config['sub_group_by_headers'] ?? [];

        $groupHeaders = array_map(function ($r, $i) use ($customGroupHeaders) {
            return $customGroupHeaders[$i] ?? ucfirst(str_replace('.', ' ', $r));
        }, $groupBy, array_keys($groupBy));

        $subGroupHeaders = array_map(function ($r, $i) use ($customSubGroupHeaders) {
            return $customSubGroupHeaders[$i] ?? ucfirst(str_replace('.', ' ', $r));
        }, $subGroupBy, array_keys($subGroupBy));

        // Check if we have actual pivot columns (filter out empty/null keys)
        $hasPivotColumns = $dynamicColumns->filter(fn ($name, $id) => !empty($name) && !empty($id))->isNotEmpty();

        // Get custom total header from config (default: 'Total')
        $totalHeader = $config['total_header'] ?? 'Total';

        foreach ($pivoted as $groupKey => $subGroups) {
            // Add group header row if grouped format
            if ($isGrouped && count($groupBy) > 0) {
                $headerRow = [];
                $groupParts = explode("\x1f", $groupKey);
                foreach ($groupHeaders as $i => $header) {
                    $headerRow[$header] = $groupParts[$i] ?? '';
                }
                foreach ($subGroupHeaders as $header) {
                    $headerRow[$header] = '';
                }
                // Only add dynamic column headers if we have pivot columns
                if ($hasPivotColumns) {
                    foreach ($dynamicColumns as $name) {
                        $headerRow[$name] = '';
                    }
                }
                $headerRow[$totalHeader] = '';
                $rows[] = $headerRow;
            }

            // Add sub-group rows
            foreach ($subGroups as $subGroupKey => $item) {
                $row = [];

                // Add group columns (empty if grouped format)
                foreach ($groupHeaders as $i => $header) {
                    $row[$header] = $isGrouped ? '' : (explode("\x1f", $groupKey)[$i] ?? '');
                }

                // Add sub-group columns
                foreach ($subGroupHeaders as $i => $header) {
                    $row[$header] = $item['data'][$i] ?? '';
                }

                // Only add dynamic columns if we have pivot columns
                if ($hasPivotColumns) {
                    foreach ($dynamicColumns as $pivotId => $name) {
                        $value = $item['values'][$pivotId] ?? 0;
                        $row[$name] = $this->formatPivotValue($value, $formatFunction);
                    }
                }

                // Add total
                $row[$totalHeader] = $this->formatPivotValue($item['total'], $formatFunction);
                $rows[] = $row;
            }
        }

        return collect($rows);
    }

    /**
     * Format pivot value using function if available
     */
    protected function formatPivotValue(float $value, ?ExportFunction $function): string
    {
        if ($function && is_callable($function->callable)) {
            return call_user_func($function->callable, $value);
        }

        return number_format($value, 2);
    }
}
