<?php

namespace HexagonLabsLLC\LaravelExports\Exports;

use HexagonLabsLLC\LaravelExports\Exports\Handlers\CsvExportHandler;
use HexagonLabsLLC\LaravelExports\Exports\Handlers\ExportHandler;
use HexagonLabsLLC\LaravelExports\Exports\Handlers\JsonExportHandler;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;

class ExportFactory
{
    /**
     * Registered export handlers
     */
    protected static array $handlers = [
        'csv' => CsvExportHandler::class,
        'json' => JsonExportHandler::class,
    ];

    /**
     * Create an export handler instance
     *
     * @param  string  $format  The export format (csv, json)
     * @param  ExportLayout  $layout  The export layout
     * @param  array  $options  Handler-specific options
     *
     * @throws \InvalidArgumentException
     */
    public static function create(string $format, ExportLayout $layout, array $options = []): ExportHandler
    {
        $format = strtolower($format);

        if (! isset(self::$handlers[$format])) {
            throw new \InvalidArgumentException("Unsupported export format: {$format}. Supported formats: ".implode(', ', array_keys(self::$handlers)));
        }

        $handlerClass = self::$handlers[$format];

        return new $handlerClass($layout, $options);
    }

    /**
     * Register a custom export handler
     *
     * @param  string  $format  The format name
     * @param  string  $handlerClass  The handler class name
     */
    public static function register(string $format, string $handlerClass): void
    {
        if (! is_subclass_of($handlerClass, ExportHandler::class)) {
            throw new \InvalidArgumentException('Handler class must extend '.ExportHandler::class);
        }

        self::$handlers[strtolower($format)] = $handlerClass;
    }

    /**
     * Get all supported formats
     */
    public static function getSupportedFormats(): array
    {
        return array_keys(self::$handlers);
    }

    /**
     * Check if a format is supported
     */
    public static function isSupported(string $format): bool
    {
        return isset(self::$handlers[strtolower($format)]);
    }
}
