<?php

namespace HexagonLabsLLC\LaravelExports\Services;

use HexagonLabsLLC\LaravelExports\Enums\OperatorType;
use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Support\Str;

/**
 * Read-only spot checker for layout configurations. Works on persisted and
 * unsaved layouts alike, so the same call serves export:validate, the
 * builder, and pre-save drafts from a UI. Never writes or lazy-syncs.
 */
class LayoutValidator
{
    protected const AGGREGATORS = ['sum', 'count', 'avg', 'average', 'min', 'max', 'first', 'last'];

    protected const VALUE_TYPES = ['array', 'string', 'integer', 'boolean', 'float'];

    protected array $problems = [];

    protected ?string $modelClass = null;

    protected array $schemaCache = [];

    public function __construct(protected ModelRelationInspector $inspector) {}

    public function validate(ExportLayout $layout): array
    {
        $this->problems = [];
        $this->modelClass = null;

        $this->checkModel($layout);
        $this->checkColumns($layout);
        $this->checkFilters($layout);
        $this->checkSorts($layout);

        if ($layout->isPivot()) {
            $this->checkPivot($layout);
        }

        return $this->problems;
    }

    /**
     * Validate a raw attribute payload (e.g. a UI form draft) without
     * persisting anything.
     */
    public function validateDraft(array $attributes): array
    {
        return $this->validate(new ExportLayout($attributes));
    }

    protected function checkModel(ExportLayout $layout): void
    {
        if ($layout->model) {
            if (!class_exists($layout->model)) {
                $this->error('model_class_missing', 'layout', ['model' => $layout->model]);

                return;
            }

            $this->modelClass = $layout->model;

            return;
        }

        if ($layout->export_model_id) {
            $exportModel = $layout->exportModel;

            if (!$exportModel) {
                $this->error('export_model_missing', 'layout', ['id' => $layout->export_model_id]);

                return;
            }

            if (!class_exists($exportModel->model)) {
                $this->error('model_class_missing', 'layout', ['model' => $exportModel->model]);

                return;
            }

            $this->modelClass = $exportModel->model;

            return;
        }

        $this->error('missing_model', 'layout');
    }

    protected function checkColumns(ExportLayout $layout): void
    {
        $persisted = $layout->id ? $layout->columns()->get() : collect();
        $definitions = $layout->column_definitions ?? [];

        if (!$layout->isPivot() && $persisted->isEmpty() && $definitions === []) {
            $this->error('no_columns', 'layout');
        }

        foreach ($persisted as $column) {
            $source = 'column:'.($column->title ?: $column->value_path ?: $column->id);

            if ($column->export_model_relation_id && !$column->modelRelation) {
                $this->error('orphaned_relation', $source, ['id' => $column->export_model_relation_id]);
            }
            if ($column->export_filter_id && !$column->filter) {
                $this->error('orphaned_filter', $source, ['id' => $column->export_filter_id]);
            }
            if ($column->export_function_id && !$column->exportFunction) {
                $this->error('orphaned_function', $source, ['id' => $column->export_function_id]);
            } elseif ($column->exportFunction && !is_callable($column->exportFunction->callable)) {
                $this->error('function_not_callable', $source, ['function' => $column->exportFunction->name]);
            }

            $this->checkColumnShape(
                $source,
                $column->title,
                $column->value_path,
                $column->modelRelation?->relation,
                (bool)$column->is_expanded,
                $column->aggregator,
                $column->format
            );
        }

        foreach ($definitions as $title => $definition) {
            $attributes = is_string($definition) ? ['value_path' => $definition] : $definition;
            $source = 'column_definitions:'.(is_string($title) ? $title : $attributes['title'] ?? $title);

            $this->checkColumnShape(
                $source,
                is_string($title) ? $title : ($attributes['title'] ?? null),
                $attributes['value_path'] ?? null,
                $attributes['relation'] ?? null,
                (bool)($attributes['is_expanded'] ?? false),
                $attributes['aggregator'] ?? null,
                $attributes['format'] ?? null
            );
        }
    }

    protected function checkColumnShape(
        string $source,
        ?string $title,
        ?string $valuePath,
        ?string $relationPath,
        bool $isExpanded,
        ?string $aggregator,
        ?string $format
    ): void {
        if ($title === null && $valuePath === null) {
            $this->error('missing_output_key', $source);
        }

        if ($aggregator !== null && !in_array($aggregator, self::AGGREGATORS, true)) {
            $this->error('unknown_aggregator', $source, ['aggregator' => $aggregator]);
        }

        if ($format !== null && !str_contains($format, '{value}')) {
            $this->warning('missing_placeholder', $source, ['format' => $format]);
        }

        if ($relationPath !== null && $this->modelClass && !$this->pathResolves($relationPath)) {
            $this->error('unknown_path', $source, ['path' => $relationPath, 'model' => $this->modelClass]);
        }

        if ($isExpanded) {
            if ($relationPath === null || !$this->modelClass || !$this->pathIsCollection($relationPath)) {
                $this->error('expansion_requires_collection', $source);
            }
        }

        // A value_path on a relation column may be item-relative, so only
        // root-anchored value paths can be judged with certainty
        if ($valuePath !== null && Str::contains($valuePath, '.') && $this->modelClass) {
            if ($relationPath === null && !$this->pathResolves($valuePath)) {
                $this->error('unknown_path', $source, ['path' => $valuePath, 'model' => $this->modelClass]);
            }
        }
    }

    protected function checkFilters(ExportLayout $layout): void
    {
        // Bypass the filters() scope so relationless rows are inspected too
        $persisted = $layout->id
            ? ExportFilter::where('export_layout_id', $layout->id)->orderBy('id')->get()
            : collect();
        $definitions = $layout->filter_definitions ?? [];

        $first = true;
        foreach ($persisted as $filter) {
            $source = 'filter:'.$filter->id;

            if ($filter->export_model_relation_id && !$filter->modelRelation) {
                $this->error('orphaned_relation', $source, ['id' => $filter->export_model_relation_id]);
            }
            if ($filter->is_required && !$filter->export_model_relation_id) {
                $this->error('required_without_relation', $source);
            }

            $this->checkFilterShape(
                $source,
                $filter->operator,
                $filter->value_type,
                $filter->value,
                (bool)$filter->is_request,
                $filter->modelRelation?->relation,
                (string)$filter->logical_operator,
                $first
            );
            $first = false;
        }

        foreach ($definitions as $index => $definition) {
            $source = "filter_definitions[{$index}]";

            $missing = array_diff(['path', 'operator'], array_keys((array)$definition));
            if ($missing !== []) {
                $this->error('missing_definition_keys', $source, ['keys' => implode(', ', $missing)]);

                continue;
            }

            $this->checkFilterShape(
                $source,
                $definition['operator'],
                $definition['value_type'] ?? null,
                $definition['value'] ?? null,
                (bool)($definition['is_request'] ?? false),
                $definition['path'],
                (string)($definition['logical_operator'] ?? ''),
                $first
            );
            $first = false;
        }
    }

    protected function checkFilterShape(
        string $source,
        string $operator,
        ?string $valueType,
        $value,
        bool $isRequest,
        ?string $path,
        string $logicalOperator,
        bool $isFirst
    ): void {
        try {
            OperatorType::getOperator($operator);
        } catch (\InvalidArgumentException) {
            $this->error('unknown_operator', $source, ['operator' => $operator]);
        }

        if ($valueType !== null && !in_array($valueType, self::VALUE_TYPES, true)) {
            $this->error('unknown_value_type', $source, ['value_type' => $valueType]);
        }

        if (!$isRequest && $value === null && !in_array($operator, ['null', 'not_null'], true)) {
            $this->warning('skipped_static_filter', $source);
        }

        $decoded = is_string($value) ? (json_decode($value, true) ?? $value) : $value;

        if ($operator === 'between' && !$isRequest && is_array($decoded) && count($decoded) !== 2) {
            $this->error('between_requires_two', $source);
        }

        // Scalar relation values are a working shorthand; only malformed arrays are errors
        if ($operator === 'relation' && is_array($decoded) && count($decoded) < 3) {
            $this->error('relation_config_shape', $source);
        }

        if ($path !== null && $this->modelClass && !$this->pathResolves($path)) {
            $this->error('unknown_path', $source, ['path' => $path, 'model' => $this->modelClass]);
        }

        if ($isFirst && strcasecmp($logicalOperator, 'or') === 0) {
            $this->warning('leading_or', $source);
        }
    }

    protected function checkSorts(ExportLayout $layout): void
    {
        $persisted = $layout->id ? $layout->sorts()->get() : collect();
        $definitions = $layout->sort_definitions ?? [];

        foreach ($persisted as $sort) {
            $source = 'sort:'.$sort->id;

            if ($sort->export_model_relation_id && !$sort->modelRelation) {
                $this->error('orphaned_relation', $source, ['id' => $sort->export_model_relation_id]);

                continue;
            }

            $this->checkSortShape(
                $source,
                $sort->direction,
                $sort->modelRelation?->relation,
                (bool)($sort->modelRelation?->is_collection),
                $sort->modelRelation?->metadata['sort_column'] ?? null
            );
        }

        foreach ($definitions as $index => $definition) {
            $source = "sort_definitions[{$index}]";

            if (!isset($definition['path'])) {
                $this->error('missing_definition_keys', $source, ['keys' => 'path']);

                continue;
            }

            $path = $definition['path'];

            if ($this->modelClass && !$this->pathResolves($path)) {
                $this->error('unknown_path', $source, ['path' => $path, 'model' => $this->modelClass]);

                continue;
            }

            $this->checkSortShape(
                $source,
                $definition['direction'] ?? 'asc',
                $path,
                $this->modelClass ? $this->pathIsCollection($path) : false,
                $definition['sort_column'] ?? null
            );
        }
    }

    protected function checkSortShape(string $source, string $direction, ?string $path, bool $isCollection, ?string $sortColumn): void
    {
        if (!in_array(strtolower($direction), ['asc', 'desc'], true)) {
            $this->error('unknown_direction', $source, ['direction' => $direction]);
        }

        if ($path !== null && $this->modelClass && !$this->pathResolves($path)) {
            $this->error('unknown_path', $source, ['path' => $path, 'model' => $this->modelClass]);

            return;
        }

        if ($isCollection && $sortColumn === null) {
            $this->warning('collection_sort_count', $source);
        }
    }

    protected function checkPivot(ExportLayout $layout): void
    {
        $config = $layout->getPivotConfig();

        if ($config === [] || empty($config['group_by'])) {
            $this->error('missing_pivot_config', 'pivot_config');

            return;
        }

        $aggregation = $config['aggregation'] ?? 'count';
        if (!in_array($aggregation, ['sum', 'count', 'avg', 'min', 'max'], true)) {
            $this->error('unknown_pivot_aggregation', 'pivot_config', ['aggregation' => $aggregation]);
        }

        $outputFormat = $config['output_format'] ?? 'flat';
        if (!in_array($outputFormat, ['flat', 'grouped'], true)) {
            $this->error('unknown_output_format', 'pivot_config', ['format' => $outputFormat]);
        }

        if (!$this->modelClass) {
            return;
        }

        $paths = array_merge($config['group_by'], $config['sub_group_by'] ?? []);
        if (!empty($config['pivot_relation'])) {
            $paths[] = $config['pivot_relation'];
        }

        // value_relation may name the base table itself, which is not a relation
        $valueRelation = $config['value_relation'] ?? null;
        if ($valueRelation && $valueRelation !== (new $this->modelClass)->getTable()) {
            $paths[] = $valueRelation.'.'.($config['value_column'] ?? 'id');
        }

        foreach ($paths as $path) {
            if (!$this->pivotPathResolves($path)) {
                $this->error('unknown_pivot_path', 'pivot_config', ['path' => $path, 'model' => $this->modelClass]);
            }
        }
    }

    /**
     * Read-only path classification mirroring runtime resolution: a base
     * column, a relation name, a valid relation chain, or a dotted attribute
     * on a valid relation prefix all pass.
     */
    protected function pathResolves(string $path): bool
    {
        $schema = $this->schema($this->modelClass);

        if (!Str::contains($path, '.')) {
            return in_array($path, $schema['columns'], true) || isset($schema['relations'][$path]);
        }

        if ($this->inspector->validateNestedPath($this->modelClass, $path)['valid']) {
            return true;
        }

        $segments = explode('.', $path);
        $attribute = array_pop($segments);
        $prefix = $this->inspector->validateNestedPath($this->modelClass, implode('.', $segments));

        return $prefix['valid']
            && $prefix['final_model'] !== null
            && in_array($attribute, $this->inspector->getModelColumns($prefix['final_model']), true);
    }

    protected function pathIsCollection(string $path): bool
    {
        if (!Str::contains($path, '.')) {
            return (bool)($this->schema($this->modelClass)['relations'][$path]['is_collection'] ?? false);
        }

        $validation = $this->inspector->validateNestedPath($this->modelClass, $path);
        $segments = $validation['segments'];
        $last = $segments ? end($segments) : null;

        return $validation['valid'] && (bool)($last['is_collection'] ?? false);
    }

    /**
     * Pivot paths are relation segments ending in a column, walked over the
     * real Eloquent models the same way buildPivotQuery joins them.
     */
    protected function pivotPathResolves(string $path): bool
    {
        $segments = explode('.', $path);
        $column = array_pop($segments);
        $currentClass = $this->modelClass;

        foreach ($segments as $segment) {
            $relations = $this->schema($currentClass)['relations'];

            if (!isset($relations[$segment])) {
                return false;
            }

            $currentClass = $relations[$segment]['related_model'];
        }

        return in_array($column, $this->inspector->getModelColumns($currentClass), true);
    }

    protected function schema(string $modelClass): array
    {
        return $this->schemaCache[$modelClass] ??= [
            'columns' => $this->inspector->getModelColumns($modelClass),
            'relations' => $this->inspector->getModelRelations($modelClass),
        ];
    }

    protected function error(string $code, string $source, array $params = []): void
    {
        $this->problem('error', $code, $source, $params);
    }

    protected function warning(string $code, string $source, array $params = []): void
    {
        $this->problem('warning', $code, $source, $params);
    }

    protected function problem(string $severity, string $code, string $source, array $params): void
    {
        $this->problems[] = [
            'severity' => $severity,
            'code' => $code,
            'source' => $source,
            'params' => $params,
            'message' => __('laravel-exports::validation.'.$code, $params),
        ];
    }
}
