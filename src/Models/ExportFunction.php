<?php

namespace Hexlabs\LaravelExports\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ExportFunction extends Model
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

    protected $table = 'export_functions';

    protected $fillable = [
        'name',
        'callable',
        'parameter_count',
        'value_parameter_index',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
