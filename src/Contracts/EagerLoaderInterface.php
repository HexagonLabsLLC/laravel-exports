<?php

namespace HexagonLabsLLC\LaravelExports\Contracts;

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Database\Eloquent\Builder;

interface EagerLoaderInterface
{
    /**
     * Apply eager loading to the query based on layout configuration.
     */
    public function apply(Builder $query, ExportLayout $layout): Builder;

    /**
     * Build the array of relations to eager load.
     */
    public function buildEagerLoadArray(ExportLayout $layout): array;
}
