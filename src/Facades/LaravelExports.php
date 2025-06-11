<?php

namespace Hexlabs\LaravelExports\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection executeExport(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [])
 * @method static void executeExportChunked(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [], int $chunkSize = 1000, ?callable $callback = null)
 * @method static int getExportCount(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [])
 * @method static array executeExportPaginated(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [], int $perPage = 100, int $page = 1)
 * @method static \Illuminate\Database\Eloquent\Builder getQuery(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, array $requestData = [])
 * @method static mixed exportTo(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, string $format, array $requestData = [], array $options = [])
 * @method static \Illuminate\Http\Response downloadAs(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = [])
 * @method static bool storeAs(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, string $format, string $path, array $requestData = [], array $options = [])
 * @method static \Illuminate\Http\Response streamAs(\Hexlabs\LaravelExports\Models\ExportLayout|string $layout, string $format, string $filename, array $requestData = [], array $options = [], int $chunkSize = 1000)
 * @method static array getSupportedFormats()
 *
 * @see \Hexlabs\LaravelExports\Services\DynamicExportService
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
