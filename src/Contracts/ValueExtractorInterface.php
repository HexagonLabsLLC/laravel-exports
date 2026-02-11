<?php

namespace HexagonLabsLLC\LaravelExports\Contracts;

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use Illuminate\Database\Eloquent\Model;

interface ValueExtractorInterface
{
    /**
     * Extract a value from a model following a relation path.
     */
    public function extract(Model $model, string $path): mixed;

    /**
     * Resolve a relation value from a model.
     */
    public function resolveRelation(Model $model, string $path): mixed;

    /**
     * Extract a value from a collection based on column configuration.
     */
    public function extractFromCollection(Model $model, ExportColumn $column): mixed;

    /**
     * Extract pivot data from a relation path.
     */
    public function extractPivotValue(Model $model, string $path): mixed;

    /**
     * Check if a path contains pivot notation.
     */
    public function containsPivotPath(string $path): bool;
}
