<?php

namespace HexagonLabsLLC\LaravelExports\Contracts;

use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Database\Eloquent\Builder;

interface FilterApplicatorInterface
{
    /**
     * Apply all layout filters to a query.
     */
    public function apply(Builder $query, ExportLayout $layout, array $requestData = []): Builder;

    /**
     * Apply a single filter to a query.
     */
    public function applyFilter(Builder $query, ExportFilter $filter, mixed $value = null): Builder;

    /**
     * Apply column-specific filters for eager loading constraints.
     */
    public function applyColumnFilters(Builder $query, ExportLayout $layout, array $requestData = []): Builder;
}
