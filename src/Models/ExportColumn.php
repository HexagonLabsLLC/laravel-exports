<?php

namespace Hexlabs\LaravelExports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportColumn extends Model
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

    protected $table = 'export_columns';

    protected $fillable = [
        'export_layout_id',
        'export_function_id',
        'export_function_values',
        'export_filter_id',
        'export_filter_values',
        'export_model_relation_id',
        'aggregator',
        'title',
        'value_path',
        'default',
        'position',
        'is_expanded',
        'expansion_data',
        'omit_on_empty',
    ];

    protected $casts = [
        'export_function_values' => 'array',
        'export_filter_values' => 'array',
        'is_expanded' => 'boolean',
        'omit_on_empty' => 'boolean',
        'expansion_data' => 'array',
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

    public function exportFunction(): BelongsTo
    {
        return $this->belongsTo(ExportFunction::class, 'export_function_id');
    }

    public function filter(): BelongsTo
    {
        return $this->belongsTo(ExportFilter::class, 'export_filter_id');
    }
}
