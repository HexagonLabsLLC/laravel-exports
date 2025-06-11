<?php

namespace Hexlabs\LaravelExports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportFilter extends Model
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

    protected $table = 'export_filters';

    protected $fillable = [
        'export_layout_id',
        'export_model_id',
        'export_model_relation_id',
        'logical_operator',
        'operator',
        'value',
        'value_type',
        'is_request',
        'is_required',
    ];

    protected $casts = [
        'is_request' => 'boolean',
        'is_required' => 'boolean',
    ];

    public function layout(): BelongsTo
    {
        return $this->belongsTo(ExportLayout::class, 'export_layout_id');
    }

    public function relation(): BelongsTo
    {
        return $this->belongsTo(ExportModelRelation::class, 'export_model_relation_id');
    }

    public function modelRelation(): BelongsTo
    {
        return $this->belongsTo(ExportModelRelation::class, 'export_model_relation_id');
    }
}
