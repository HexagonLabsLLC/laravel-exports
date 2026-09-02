<?php

namespace HexagonLabsLLC\LaravelExports\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $export_model_id
 * @property string $name
 * @property string|null $title
 * @property string|null $description
 * @property bool $is_pivot
 * @property array|null $pivot_config
 * @property array|null $column_definitions
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
        'name',
        'title',
        'description',
        'is_pivot',
        'pivot_config',
        'column_definitions',
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
        ];
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

    public function filters(): HasMany
    {
        return $this->hasMany(ExportFilter::class, 'export_layout_id')
            ->whereNotNull('export_model_relation_id');
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
            $attributes['export_model_relation_id'] ??= ExportModelRelation::where('export_model_id', $this->export_model_id)
                ->where('relation', $relation)
                ->value('id')
                ?? throw new \InvalidArgumentException(
                    "Relation '{$relation}' is not registered for this layout's export model. Run export:import-models or create the ExportModelRelation first."
                );
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
