# Directory Structure

This document explains the Laravel Exports package structure.

```
laravel-exports/
|-- config/
|   `-- laravel-exports.php         # Package configuration
|
|-- database/
|   `-- migrations/                 # Database migrations
|       |-- 2025_05_01_000001_create_export_models_table.php
|       |-- 2025_05_01_000002_create_export_model_relations_table.php
|       |-- 2025_05_01_000003_create_export_layouts_table.php
|       |-- 2025_05_01_000004_create_export_functions_table.php
|       |-- 2025_05_01_000005_create_export_filters_table.php
|       |-- 2025_05_01_000006_create_export_columns_table.php
|       |-- 2025_05_01_000007_create_export_sorts_table.php
|       |-- 2025_05_28_000001_add_collection_aggregators_to_export_columns.php
|       |-- 2025_06_10_000001_rename_export_relation_id_to_export_model_relation_id.php
|       |-- 2026_02_04_000001_add_pivot_columns_to_export_model_relations.php
|       |-- 2026_02_05_000001_add_pivot_support_to_export_layouts.php
|       |-- 2026_08_31_000001_add_missing_columns_to_export_tables.php
|       |-- 2026_09_01_000001_add_column_definitions_to_export_layouts.php
|       |-- 2026_09_01_000002_add_format_to_export_columns.php
|       `-- 2026_09_01_000003_add_lazy_catalog_support.php
|
|-- lang/
|   `-- en/
|       `-- validation.php           # Layout validation messages (publishable)
|
|-- docs/
|   |-- concepts/                  # Architecture and lifecycle concepts
|   |-- examples/                  # Practical examples (basic to advanced)
|   |-- guides/                    # In-depth feature guides
|   |-- reference/                 # API, operators, functions, commands
|   |-- configuration.md           # Configuration options
|   |-- directory-structure.md     # This file
|   |-- getting-started.md         # Installation and setup
|   |-- index.md                   # Documentation index
|   `-- troubleshooting.md         # Common issues and solutions
|
|-- src/
|   |-- Builders/
|   |   `-- ExportLayoutBuilder.php  # Fluent layout construction
|   |
|   |-- Console/
|   |   `-- Commands/
|   |       |-- ImportModelsCommand.php           # Model discovery command
|   |       |-- SeedTransformationFunctionsCommand.php  # Function seeding command
|   |       `-- ValidateLayoutsCommand.php        # export:validate
|   |
|   |-- Enums/
|   |   `-- OperatorType.php       # Filter operator enum
|   |
|   |-- Exports/
|   |   |-- ExportFactory.php      # Factory for creating export handlers
|   |   `-- Handlers/
|   |       |-- CsvExportHandler.php    # CSV format handler
|   |       |-- ExportHandler.php       # Abstract base handler
|   |       |-- JsonExportHandler.php   # JSON format handler
|   |       `-- XlsxExportHandler.php   # XLSX handler (optional phpspreadsheet)
|   |
|   |-- Facades/
|   |   `-- LaravelExports.php     # Laravel facade
|   |
|   |-- Helpers/
|   |   `-- ModelRelationInspector.php  # Model introspection utilities
|   |
|   |-- Jobs/
|   |   `-- ProcessExportJob.php   # Background export job
|   |
|   |-- Models/                    # Eloquent models
|   |   |-- ExportColumn.php       # Export column configuration
|   |   |-- ExportFilter.php       # Filter configuration
|   |   |-- ExportFunction.php     # Transformation functions
|   |   |-- ExportLayout.php       # Export layout configuration
|   |   |-- ExportModel.php        # Registered exportable models
|   |   |-- ExportModelRelation.php # Model columns and relations
|   |   `-- ExportSort.php         # Sort configuration
|   |
|   |-- Services/
|   |   |-- DynamicExportService.php    # Main export engine
|   |   |-- LayoutValidator.php         # Read-only layout spot checker
|   |   |-- SchemaSync.php              # Lazy catalog sync
|   |   `-- TransformationFunctions.php # Built-in functions
|   |
|   `-- LaravelExportsServiceProvider.php  # Service provider
|
|-- tests/
|   |-- Feature/
|   |   |-- Commands/
|   |   |   |-- ImportModelsCommandTest.php
|   |   |   `-- SeedTransformationFunctionsCommandTest.php
|   |   |-- Jobs/
|   |   |   `-- ProcessExportJobTest.php
|   |   `-- Services/
|   |       |-- DynamicExportServiceTest.php
|   |       |-- ExportRegressionTest.php
|   |       |-- LayoutValidatorTest.php
|   |       |-- SchemaSyncTest.php
|   |       `-- StreamingTest.php
|   |
|   |-- Unit/
|   |   |-- Exports/
|   |   |   `-- Handlers/
|   |   |       |-- CsvExportHandlerTest.php
|   |   |       |-- JsonExportHandlerTest.php
|   |   |       `-- XlsxExportHandlerTest.php
|   |   |-- Helpers/
|   |   |   `-- ModelRelationInspectorTest.php
|   |   |-- Models/
|   |   |   `-- ExportModelTest.php
|   |   |-- Services/
|   |   |   `-- TransformationFunctionsTest.php
|   |   |-- NoDatabaseTest.php
|   |   |-- RelationSortingTest.php
|   |   `-- ValidationTest.php
|   |
|   |-- TestModels/               # Models used in tests
|   |   |-- Category.php
|   |   |-- Comment.php
|   |   |-- Post.php
|   |   |-- Tag.php
|   |   `-- User.php
|   |
|   |-- Pest.php                  # Pest configuration
|   |-- TestCase.php              # Base test case
|   `-- phpstan-bootstrap.php     # PHPStan bootstrap for test analysis
|
|-- .gitignore
|-- CLAUDE.md                     # Development guidelines for Claude AI
|-- composer.json
|-- composer.lock
|-- database.md                   # Database schema documentation
|-- LICENSE.md
|-- phpstan.neon.dist             # PHPStan/Larastan configuration
|-- phpunit.xml
|-- phpunit.xml.dist              # PHPUnit configuration for fresh clones
|-- pint.json                     # Laravel Pint code style configuration
|-- README.md
|-- RELEASE_NOTES.md              # Release notes
`-- todos.md                      # Development task tracking
```

## Key Components

### Models (`src/Models/`)
The Eloquent models that represent the database tables. These define the structure of export configurations.

### Services (`src/Services/`)
- **DynamicExportService**: The main service that executes exports based on configurations
- **LayoutValidator**: Read-only spot checker behind `export:validate` and the builder
- **SchemaSync**: Keeps the export catalog in sync with your models (`schema_sync` modes)
- **TransformationFunctions**: Provides built-in data transformation functions

### Builders (`src/Builders/`)
`ExportLayoutBuilder` composes a validated layout - columns, filters, and sorts - and saves it in one transaction.

### Export Handlers (`src/Exports/Handlers/`)
Format-specific handlers that convert data into different output formats. Easy to extend for custom formats.

### Console Commands (`src/Console/Commands/`)
Artisan commands for managing the export system:
- Import models and discover relationships
- Seed transformation functions
- Validate layout configurations (`export:validate`)

### Jobs (`src/Jobs/`)
`ProcessExportJob` runs queued exports in the background with cache-based status tracking. Any format registered with `ExportFactory` can be queued: `csv` and `json` are written chunk by chunk to a temp file, every other format (xlsx, custom handlers) buffers the result set in memory and calls the handler's `export()`.

### Helpers (`src/Helpers/`)
Utility classes for model introspection and relationship discovery.

### Database Migrations (`database/migrations/`)
Migration files that create the required database tables when published to your application.

## Extension Points

1. **Custom Export Formats**: Extend `ExportHandler` and register with `ExportFactory`
2. **Custom Transformation Functions**: Add to `TransformationFunctions` or create your own
