<?php

namespace HexagonLabsLLC\LaravelExports\Contracts;

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ResultProcessorInterface
{
    /**
     * Process query results into export-ready format.
     */
    public function process(Collection $results, ExportLayout $layout, array $requestData = []): Collection;

    /**
     * Process a single model into an export row.
     */
    public function processRow(Model $model, ExportLayout $layout, array $requestData = []): array;

    /**
     * Extract the value for a single column from a model.
     */
    public function extractColumnValue(Model $model, ExportColumn $column, array $requestData = []): mixed;
}
