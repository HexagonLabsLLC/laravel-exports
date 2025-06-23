<?php

namespace HexagonLabsLLC\LaravelExports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExportModel extends Model
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

    protected $table = 'export_models';

    protected $fillable = [
        'title',
        'model',
    ];

    protected $appends = [
        'instance',
    ];
    
    public function getInstanceAttribute(): Model
    {
        return app($this->model);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ExportModelRelation::class, 'export_model_id');
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(ExportLayout::class, 'export_model_id');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(ExportFilter::class, 'export_model_id');
    }

    public function sorts(): HasMany
    {
        return $this->hasMany(ExportSort::class, 'export_model_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ExportColumn::class, 'export_model_id');
    }
}
