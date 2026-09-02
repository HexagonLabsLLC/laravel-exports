<?php

namespace HexagonLabsLLC\LaravelExports\Models;

use HexagonLabsLLC\LaravelExports\Services\SchemaSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string|null $export_model_id
 * @property string|null $model
 * @property string $name
 * @property string|null $title
 * @property string|null $description
 * @property bool $is_pivot
 * @property array|null $pivot_config
 * @property array|null $column_definitions
 * @property array|null $filter_definitions
 * @property array|null $sort_definitions
 * @property-read ExportModel|null $exportModel
 * @property-read Collection<int, ExportFilter> $filters
 * @property-read Collection<int, ExportColumn> $columns
 * @property-read Collection<int, ExportSort> $sorts
 */
class ExportLayout extends Model
{
    use HasUuids;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    protected $table = 'export_layouts';

    protected $fillable = [
        'export_model_id',
        'model',
        'name',
        'title',
        'description',
        'is_pivot',
        'pivot_config',
        'column_definitions',
        'filter_definitions',
        'sort_definitions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pivot' => 'boolean',
            'pivot_config' => 'array',
            'column_definitions' => 'array',
            'filter_definitions' => 'array',
            'sort_definitions' => 'array',
        ];
    }

    /**
     * MySQL's JSON type does not preserve object key order, so the string-keyed
     * column shorthand is converted to an ordered list before storage. Positions
     * stay late-bound (assigned at load after persisted columns) so interleaving
     * semantics are unchanged.
     */
    public function setColumnDefinitionsAttribute(?array $definitions): void
    {
        if ($definitions !== null) {
            $entries = [];

            foreach ($definitions as $title => $definition) {
                $entry = is_string($definition) ? ['value_path' => $definition] : $definition;

                if (is_string($title) && !isset($entry['title'])) {
                    $entry['title'] = $title;
                }

                $entries[] = $entry;
            }

            $definitions = $entries;
        }

        $this->attributes['column_definitions'] = $definitions === null ? null : json_encode($definitions);
    }

    /**
     * Check if this layout is a pivot export.
     */
    public function isPivot(): bool
    {
        return (bool)$this->is_pivot;
    }

    /**
     * Get the pivot configuration for this layout.
     *
     * Expected structure:
     * [
     *     'group_by' => ['relation.column'],       // Primary grouping columns
     *     'sub_group_by' => ['relation.column'],   // Sub-grouping columns
     *     'pivot_relation' => 'relation.name',     // Relation for dynamic columns
     *     'pivot_column' => 'name',                // Column to use for pivot headers
     *     'value_relation' => 'table',             // Source for aggregated values
     *     'value_column' => 'amount',              // Column to aggregate
     *     'aggregation' => 'sum',                  // sum, count, avg, min, max
     *     'output_format' => 'flat',               // flat or grouped
     *     'pivot_filter_param' => 'type_ids',      // Request param for filtering pivot columns
     * ]
     */
    public function getPivotConfig(): array
    {
        return $this->pivot_config ?? [];
    }

    public function exportModel(): BelongsTo
    {
        return $this->belongsTo(ExportModel::class, 'export_model_id');
    }

    /**
     * The layout's export model row. A model FQCN wins over the FK and is
     * synced into the catalog on first reference per the schema_sync mode.
     */
    public function resolveExportModel(): ExportModel
    {
        if ($this->model) {
            return app(SchemaSync::class)->ensureFresh($this->model);
        }

        return $this->exportModel
            ?? throw new \InvalidArgumentException('Layout has neither an export model nor a model class');
    }

    /**
     * Resolve a relation path to its catalog row, lazily syncing the model
     * (and dotted paths) per the schema_sync mode. Column rows win over
     * same-named relations; use a dotted path to force the relation.
     */
    public function resolveRelationRow(string $path): ExportModelRelation
    {
        $exportModel = $this->resolveExportModel();

        $find = fn () => ExportModelRelation::where('export_model_id', $exportModel->id)
            ->where('relation', $path)
            ->orderByDesc('is_column')
            ->first();

        $row = $find();

        if (!$row) {
            $sync = app(SchemaSync::class);

            if ($sync->canSync()) {
                $sync->syncOnce($exportModel->model);
                $row = $find();

                if (!$row && Str::contains($path, '.')) {
                    $row = $sync->syncPath($exportModel, $path) ?? $sync->syncColumnPath($exportModel, $path);
                }
            }
        }

        return $row ?? throw new \InvalidArgumentException(
            "Relation '{$path}' is not registered for this layout's export model. Run export:import-models or create the ExportModelRelation first."
        );
    }

    public function filters(): HasMany
    {
        // Ordered by id (HasUuids generates ordered uuids) so filter
        // application - and or-group placement - follows creation order
        return $this->hasMany(ExportFilter::class, 'export_layout_id')
            ->whereNotNull('export_model_relation_id')
            ->orderBy('id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ExportColumn::class, 'export_layout_id')
            ->orderBy('position');
    }

    /**
     * Bulk-create columns from an array. Entries may be:
     * - 'Title' => 'value.path' (string shorthand)
     * - 'Title' => [attributes] (title taken from the key unless set)
     * - [attributes] (list style)
     * A 'relation' key is resolved to export_model_relation_id against this
     * layout's export model; positions auto-increment past the current max.
     */
    public function addColumns(array $columns): static
    {
        $position = (int)$this->columns()->max('position');

        foreach ($columns as $title => $definition) {
            $this->columns()->create($this->normalizeColumnDefinition($title, $definition, $position));
        }

        return $this;
    }

    /**
     * Build unsaved ExportColumn models from the column_definitions JSON field.
     * Same entry shapes as addColumns(); definitions without a position
     * slot in after the layout's persisted columns.
     */
    public function buildDefinedColumns(): Collection
    {
        if (empty($this->column_definitions)) {
            return new Collection;
        }

        $position = (int)$this->columns()->max('position');
        $models = new Collection;

        foreach ($this->column_definitions as $title => $definition) {
            $attributes = $this->normalizeColumnDefinition($title, $definition, $position);
            $attributes['export_layout_id'] = $this->id;
            $models->push(new ExportColumn($attributes));
        }

        return $models;
    }

    /**
     * Build unsaved ExportFilter models from the filter_definitions JSON field.
     * Entry shape: {path, operator, value, value_type?, logical_operator?,
     * is_request?, is_required?, column?}.
     */
    public function buildDefinedFilters(): Collection
    {
        if (empty($this->filter_definitions)) {
            return new Collection;
        }

        $models = new Collection;

        foreach ($this->filter_definitions as $definition) {
            $row = $this->resolveRelationRow($definition['path']);

            $filter = new ExportFilter(collect($definition)
                ->only(['operator', 'value', 'value_type', 'logical_operator', 'is_request', 'is_required'])
                ->put('export_layout_id', $this->id)
                ->put('export_model_relation_id', $row->id)
                ->all());

            if (!empty($definition['column'])) {
                $row = clone $row;
                $row->column = $definition['column'];
            }

            $filter->setRelation('modelRelation', $row);
            $models->push($filter);
        }

        return $models;
    }

    /**
     * Build unsaved ExportSort models from the sort_definitions JSON field.
     * Entry shape: {path, direction?, priority?, sort_column?}. Sorts without
     * a priority slot in after persisted sorts.
     */
    public function buildDefinedSorts(): Collection
    {
        if (empty($this->sort_definitions)) {
            return new Collection;
        }

        $models = new Collection;

        foreach ($this->sort_definitions as $i => $definition) {
            $row = $this->resolveRelationRow($definition['path']);

            $sort = new ExportSort([
                'export_layout_id' => $this->id,
                'export_model_relation_id' => $row->id,
                'direction' => $definition['direction'] ?? 'asc',
                'priority' => $definition['priority'] ?? 1000 + $i,
            ]);

            if (!empty($definition['sort_column'])) {
                $row = clone $row;
                $row->metadata = ['sort_column' => $definition['sort_column']];
            }

            $sort->setRelation('modelRelation', $row);
            $models->push($sort);
        }

        return $models;
    }

    /**
     * Normalize one addColumns/column_definitions entry into ExportColumn attributes.
     */
    protected function normalizeColumnDefinition(int|string $title, array|string $definition, int &$position): array
    {
        $attributes = is_string($definition) ? ['value_path' => $definition] : $definition;

        if (is_string($title) && !isset($attributes['title'])) {
            $attributes['title'] = $title;
        }

        if ($relation = $attributes['relation'] ?? null) {
            unset($attributes['relation']);
            $attributes['export_model_relation_id'] ??= $this->resolveRelationRow($relation)->id;
        }

        if (isset($attributes['position'])) {
            $position = max($position, (int)$attributes['position']);
        } else {
            $attributes['position'] = ++$position;
        }

        return $attributes;
    }

    public function sorts(): HasMany
    {
        return $this->hasMany(ExportSort::class, 'export_layout_id')
            ->orderBy('priority');
    }
}
