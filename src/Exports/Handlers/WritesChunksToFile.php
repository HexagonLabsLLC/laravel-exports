<?php

namespace HexagonLabsLLC\LaravelExports\Exports\Handlers;

/**
 * Opt-in for handlers that can write rows to a file as they arrive, so queued
 * exports never hold the full result set in memory. Without it, queued exports
 * buffer every row and call export().
 */
interface WritesChunksToFile
{
    /**
     * Write the export to a local file path.
     *
     * $dataCallback works like the one passed to stream(): call it with a
     * function that receives each chunk (a Collection of row arrays) in order.
     */
    public function writeChunksToFile(callable $dataCallback, string $path): void;
}
