<?php

namespace HexagonLabsLLC\LaravelExports\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection executeExport(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [])
 * @method static void executeExportChunked(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [], int $chunkSize = 1000, ?callable $callback = null)
 * @method static int getExportCount(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [])
 * @method static array executeExportPaginated(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [], int $perPage = 100, int $page = 1)
 * @method static \Illuminate\Database\Eloquent\Builder getQuery(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [])
 * @method static mixed exportTo(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, string $format, array $requestData = [], array $options = [])
 * @method static \Illuminate\Http\Response downloadAs(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = [])
 * @method static bool storeAs(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, string $format, string $path, array $requestData = [], array $options = [])
 * @method static \Illuminate\Http\Response streamAs(\HexagonLabsLLC\LaravelExports\Models\ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = [], int $chunkSize = 1000)
 * @method static array getSupportedFormats()
 *
 * @see \HexagonLabsLLC\LaravelExports\Services\DynamicExportService
 */
class LaravelExports extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-exports';
    }
}
