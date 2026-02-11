<?php

namespace HexagonLabsLLC\LaravelExports\Contracts;

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Database\Eloquent\Builder;

interface QueryBuilderInterface
{
    /**
     * Build the base query for an export layout.
     */
    public function build(ExportLayout $layout): Builder;

    /**
     * Apply filters to the query based on request data.
     */
    public function applyFilters(Builder $query, ExportLayout $layout, array $requestData = []): Builder;

    /**
     * Apply sorting to the query.
     */
    public function applySorts(Builder $query, ExportLayout $layout): Builder;
}
