<?php

namespace Hexlabs\LaravelExports\Models;

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
        'description',
    ];

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
