<?php

namespace HexagonLabsLLC\LaravelExports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $export_layout_id
 * @property string|null $export_model_id
 * @property string|null $export_model_relation_id
 * @property string $logical_operator
 * @property string $operator
 * @property mixed $value
 * @property string $value_type
 * @property bool $is_request
 * @property bool $is_required
 * @property-read ExportLayout|null $layout
 * @property-read ExportModelRelation|null $modelRelation
 */
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_request' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(ExportLayout::class, 'export_layout_id');
    }

    public function modelRelation(): BelongsTo
    {
        return $this->belongsTo(ExportModelRelation::class, 'export_model_relation_id');
    }
}
