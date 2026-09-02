<?php

namespace HexagonLabsLLC\LaravelExports\Services;

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use Illuminate\Support\Str;

class SchemaSync
{
    protected array $fresh = [];

    protected array $reflected = [];

    protected array $syncedThisRequest = [];

    public function __construct(protected ModelRelationInspector $inspector) {}

    public function mode(): string
    {
        return config('laravel-exports.schema_sync', 'lazy');
    }

    public function canSync(): bool
    {
        return $this->mode() !== 'manual';
    }

    /**
     * The quick check: return the catalog row for a model class, syncing per
     * the configured schema_sync mode (lazy = sync on miss, verify = also
     * re-sync on schema drift, manual = throw on miss).
     */
    public function ensureFresh(string $modelClass): ExportModel
    {
        if (isset($this->fresh[$modelClass])) {
            return $this->fresh[$modelClass];
        }

        $existing = ExportModel::where('model', $modelClass)->first();

        if ($this->mode() === 'manual') {
            if (!$existing) {
                throw new \RuntimeException(
                    "Model {$modelClass} is not registered in the export catalog. Run export:import-models or set laravel-exports.schema_sync to 'lazy'."
                );
            }

            return $this->fresh[$modelClass] = $existing;
        }

        if ($existing && ($this->mode() === 'lazy' || $existing->schema_hash === $this->schemaHash($modelClass))) {
            return $this->fresh[$modelClass] = $existing;
        }

        return $this->syncModel($modelClass);
    }

    /**
     * Sync a model at most once per request; used by lookup-miss retry hooks
     * so repeated misses do not re-sync per row.
     */
    public function syncOnce(string $modelClass): ExportModel
    {
        return $this->syncedThisRequest[$modelClass] ??= $this->syncModel($modelClass);
    }

    /**
     * Reflect a model class and upsert its catalog rows. Related classes get
     * stub export_models rows so related_model_id links resolve; their own
     * relations sync lazily when referenced.
     */
    public function syncModel(string $modelClass): ExportModel
    {
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} not found");
        }

        $data = $this->reflect($modelClass);

        $exportModel = ExportModel::updateOrCreate(
            ['model' => $modelClass],
            [
                'title' => Str::headline(class_basename($modelClass)),
                'schema_hash' => $this->schemaHash($modelClass),
            ]
        );

        foreach ($data['columns'] as $column) {
            ExportModelRelation::updateOrCreate(
                ['export_model_id' => $exportModel->id, 'relation' => $column, 'is_column' => true],
                ['title' => Str::headline($column), 'is_collection' => false, 'related_model_id' => null]
            );
        }

        foreach ($data['relations'] as $name => $info) {
            $related = ExportModel::firstOrCreate(
                ['model' => $info['related_model']],
                ['title' => Str::headline(class_basename($info['related_model']))]
            );

            ExportModelRelation::updateOrCreate(
                ['export_model_id' => $exportModel->id, 'relation' => $name, 'is_column' => false],
                [
                    'title' => Str::headline($name),
                    'is_collection' => $info['is_collection'],
                    'related_model_id' => $related->id,
                    'has_pivot' => $info['has_pivot'] ?? false,
                    'pivot_columns' => $info['pivot_columns'] ?? null,
                ]
            );
        }

        return $this->fresh[$modelClass] = $exportModel->refresh();
    }

    /**
     * Validate and upsert one referenced nested relation path (e.g.
     * "workOrder.customer"). Returns null when the path is invalid.
     */
    public function syncPath(ExportModel $exportModel, string $path): ?ExportModelRelation
    {
        $validation = $this->inspector->validateNestedPath($exportModel->model, $path);

        if (!$validation['valid']) {
            return null;
        }

        $segments = $validation['segments'];
        $last = $segments ? end($segments) : null;

        $related = null;
        if (!empty($validation['final_model'])) {
            $related = ExportModel::firstOrCreate(
                ['model' => $validation['final_model']],
                ['title' => Str::headline(class_basename($validation['final_model']))]
            );
        }

        return ExportModelRelation::updateOrCreate(
            ['export_model_id' => $exportModel->id, 'relation' => $path, 'is_column' => false],
            [
                'title' => $this->nestedTitle($path),
                'is_collection' => $last['is_collection'] ?? false,
                'related_model_id' => $related?->id,
            ]
        );
    }

    /**
     * Validate and upsert a dotted column path (e.g. "workOrder.invoice.custom_id":
     * a relation prefix ending in an attribute), the shape smart relation
     * filters consume. Returns null when the path is invalid.
     */
    public function syncColumnPath(ExportModel $exportModel, string $path): ?ExportModelRelation
    {
        $segments = explode('.', $path);
        $attribute = array_pop($segments);
        $prefix = implode('.', $segments);

        $validation = $this->inspector->validateNestedPath($exportModel->model, $prefix);

        if (!$validation['valid'] || empty($validation['final_model'])) {
            return null;
        }

        if (!in_array($attribute, $this->inspector->getModelColumns($validation['final_model']), true)) {
            return null;
        }

        return ExportModelRelation::updateOrCreate(
            ['export_model_id' => $exportModel->id, 'relation' => $path, 'is_column' => true],
            ['title' => $this->nestedTitle($path), 'is_collection' => false]
        );
    }

    /**
     * The UI schema endpoint: fully synced columns and relations for a model.
     */
    public function describe(string $modelClass): array
    {
        $exportModel = $this->ensureFresh($modelClass);

        if (!$exportModel->schema_hash && $this->canSync()) {
            $exportModel = $this->syncModel($modelClass);
        }

        $rows = $exportModel->relations()->orderBy('relation')->get();

        return [
            'model' => $exportModel,
            'columns' => $rows->where('is_column', true)->values(),
            'relations' => $rows->where('is_column', false)->values(),
        ];
    }

    protected function reflect(string $modelClass): array
    {
        return $this->reflected[$modelClass] ??= $this->inspector->getModelData($modelClass);
    }

    protected function schemaHash(string $modelClass): string
    {
        $data = $this->reflect($modelClass);

        $columns = $data['columns'];
        sort($columns);

        $relations = [];
        foreach ($data['relations'] as $name => $info) {
            $relations[$name] = [
                $info['type'] ?? null,
                $info['related_model'] ?? null,
                (bool)($info['is_collection'] ?? false),
                (bool)($info['has_pivot'] ?? false),
                $info['pivot_columns'] ?? null,
            ];
        }
        ksort($relations);

        return sha1(json_encode([$columns, $relations]));
    }

    protected function nestedTitle(string $path): string
    {
        return implode(' > ', array_map(fn ($segment) => Str::headline($segment), explode('.', $path)));
    }
}
