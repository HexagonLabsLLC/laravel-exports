<?php

namespace HexagonLabsLLC\LaravelExports\Contracts;

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;

interface FunctionApplicatorInterface
{
    /**
     * Apply the configured transformation function to a value.
     */
    public function applyFunction(mixed $value, ExportColumn $column): mixed;

    /**
     * Apply aggregation to a collection value.
     */
    public function applyAggregation(mixed $value, ?string $aggregator): mixed;
}
