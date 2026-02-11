# Directory Structure

This document explains the Laravel Exports package structure.

```
laravel-exports/
├── config/
│   └── laravel-exports.php         # Package configuration
│
├── database/
│   └── migrations/                 # Database migrations
│       ├── 2025_05_01_000001_create_export_models_table.php
│       ├── 2025_05_01_000002_create_export_model_relations_table.php
│       ├── 2025_05_01_000003_create_export_layouts_table.php
│       ├── 2025_05_01_000004_create_export_functions_table.php
│       ├── 2025_05_01_000005_create_export_columns_table.php
│       ├── 2025_05_01_000006_create_export_filters_table.php
│       └── 2025_05_01_000007_create_export_sorts_table.php
│
├── docs/
│   ├── api-reference.md           # Complete API documentation
│   ├── directory-structure.md     # This file
│   └── usage-examples.md          # Usage examples and tutorials
│
├── src/
│   ├── Casts/
│   │   └── OperatorTypeCast.php   # Custom casting for operator enum
│   │
│   ├── Console/
│   │   └── Commands/
│   │       ├── ImportModelsCommand.php           # Model discovery command
│   │       └── SeedTransformationFunctionsCommand.php  # Function seeding command
│   │
│   ├── Enums/
│   │   └── OperatorType.php       # Filter operator enum
│   │
│   ├── Exports/
│   │   ├── ExportFactory.php      # Factory for creating export handlers
│   │   └── Handlers/
│   │       ├── CsvExportHandler.php    # CSV format handler
│   │       ├── ExportHandler.php       # Abstract base handler
│   │       └── JsonExportHandler.php   # JSON format handler
│   │
│   ├── Facades/
│   │   └── LaravelExports.php     # Laravel facade
│   │
│   ├── Helpers/
│   │   └── ModelRelationInspector.php  # Model introspection utilities
│   │
│   ├── Models/                    # Eloquent models
│   │   ├── ExportColumn.php       # Export column configuration
│   │   ├── ExportFilter.php       # Filter configuration
│   │   ├── ExportFunction.php     # Transformation functions
│   │   ├── ExportLayout.php       # Export layout configuration
│   │   ├── ExportModel.php        # Registered exportable models
│   │   ├── ExportModelRelation.php # Model columns and relations
│   │   └── ExportSort.php         # Sort configuration
│   │
│   ├── Services/
│   │   ├── DynamicExportService.php    # Main export engine
│   │   ├── ExportInspector.php         # Configuration validator
│   │   └── TransformationFunctions.php # Built-in functions
│   │
│   └── LaravelExportsServiceProvider.php  # Service provider
│
├── tests/
│   ├── Feature/
│   │   ├── Commands/
│   │   │   ├── ImportModelsCommandTest.php
│   │   │   └── SeedTransformationFunctionsCommandTest.php
│   │   └── Services/
│   │       └── DynamicExportServiceTest.php
│   │
│   ├── Unit/
│   │   ├── Exports/
│   │   │   └── Handlers/
│   │   │       ├── CsvExportHandlerTest.php
│   │   │       └── JsonExportHandlerTest.php
│   │   ├── Helpers/
│   │   │   └── ModelRelationInspectorTest.php
│   │   ├── Models/
│   │   │   └── ExportModelTest.php
│   │   ├── Services/
│   │   │   └── TransformationFunctionsTest.php
│   │   ├── NoDatabaseTest.php
│   │   ├── RelationSortingTest.php
│   │   └── ValidationTest.php
│   │
│   ├── TestModels/               # Models used in tests
│   │   ├── Category.php
│   │   ├── Comment.php
│   │   ├── Post.php
│   │   ├── Tag.php
│   │   └── User.php
│   │
│   ├── Pest.php                  # Pest configuration
│   └── TestCase.php              # Base test case
│
├── .gitignore
├── CLAUDE.md                     # Development guidelines for Claude AI
├── composer.json
├── database.md                   # Database schema documentation
├── phpunit.xml
├── README.md
└── todos.md                      # Development task tracking
```

## Key Components

### Models (`src/Models/`)
The Eloquent models that represent the database tables. These define the structure of export configurations.

### Services (`src/Services/`)
- **DynamicExportService**: The main service that executes exports based on configurations
- **ExportInspector**: Validates export configurations and syncs model relationships
- **TransformationFunctions**: Provides built-in data transformation functions

### Export Handlers (`src/Exports/Handlers/`)
Format-specific handlers that convert data into different output formats. Easy to extend for custom formats.

### Console Commands (`src/Console/Commands/`)
Artisan commands for managing the export system:
- Import models and discover relationships
- Seed transformation functions

### Helpers (`src/Helpers/`)
Utility classes for model introspection and relationship discovery.

### Database Migrations (`database/migrations/`)
Migration files that create the required database tables when published to your application.

## Extension Points

1. **Custom Export Formats**: Extend `ExportHandler` and register in config
2. **Custom Transformation Functions**: Add to `TransformationFunctions` or create your own
3. **Custom Models**: Override default models in config to add custom behavior
4. **Custom Validation**: Extend `ExportInspector` for additional validation rules