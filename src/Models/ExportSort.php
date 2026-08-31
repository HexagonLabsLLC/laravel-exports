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
 * @property string $direction
 * @property int $priority
 * @property-read ExportModelRelation|null $modelRelation
 * @property-read ExportLayout|null $layout
 */
class ExportSort extends Model
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

    protected $table = 'export_sorts';

    protected $fillable = [
        'export_layout_id',
        'export_model_id',
        'export_model_relation_id',
        'direction',
        'priority',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(ExportModel::class, 'export_model_id');
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
