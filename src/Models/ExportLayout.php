<?php

namespace HexagonLabsLLC\LaravelExports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        ];
    }

    /**
     * Check if this layout is a pivot export.
     */
    public function isPivot(): bool
    {
        return (bool) $this->is_pivot;
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

    public function sorts(): HasMany
    {
        return $this->hasMany(ExportSort::class, 'export_layout_id')
            ->orderBy('priority');
    }
}
