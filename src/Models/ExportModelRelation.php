<?php

namespace HexagonLabsLLC\LaravelExports\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportModelRelation extends Model
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

    protected $table = 'export_model_relations';

    protected $fillable = [
        'export_model_id',
        'title',
        'relation',
        'related_model_id',
        'is_column',
        'is_collection',
    ];

    protected $casts = [
        'is_column' => 'boolean',
        'is_collection' => 'boolean',
    ];

    public function getInstanceAttribute(): Model
    {
        return app($this->model->model);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(ExportModel::class, 'export_model_id');
    }

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(ExportModel::class, 'related_model_id');
    }

    /**
     * Scope to filter by nested relationship path using dot notation.
     * This traverses through the relationship chain to find the final relation.
     *
     * Example: whereNested('workItem.workOrder') will:
     * 1. Start from the current model
     * 2. Find the 'workItem' relation
     * 3. From that related model, find the 'workOrder' relation
     *
     * @param Builder $query
     * @param string $nestedPath
     * @return Builder
     */
    public function scopeWhereNested(Builder $query, string $nestedPath): Builder
    {
        $segments = explode('.', $nestedPath);
        
        // If it's a single segment, just match the relation field
        if (count($segments) === 1) {
            return $query->where('relation', $segments[0]);
        }
        
        // For nested paths, we start from the first segment and traverse forward
        $firstSegment = array_shift($segments);
        
        // First, filter to relations matching the first segment
        $query->where('relation', $firstSegment);
        
        // Then traverse through each subsequent segment
        foreach ($segments as $segment) {
            $query->whereHas('relatedModel.relations', function (Builder $subQuery) use ($segment) {
                $subQuery->where('relation', $segment);
            });
        }
        
        return $query;
    }
}
