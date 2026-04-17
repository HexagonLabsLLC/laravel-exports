<?php

namespace HexagonLabsLLC\LaravelExports\Tests\TestModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'test_categories';

    protected $fillable = [
        'name',
    ];

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }
}
