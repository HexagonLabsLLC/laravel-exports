# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

### Code Quality
```bash
# Run code formatting with Laravel Pint
./vendor/bin/pint

# Run static analysis with PHPStan/Larastan
./vendor/bin/phpstan analyse

# Run tests with Pest
./vendor/bin/pest
```

### Package Installation
```bash
# Install dependencies
composer install

# Update autoloader after changes
composer dump-autoload
```

## High-Level Architecture

This is a Laravel package that provides a comprehensive, database-driven export system. The architecture follows a configuration-as-data pattern where all export definitions are stored in database tables rather than code.

### Core Concepts

1. **Export Models**: Register Laravel Eloquent models that can be exported
2. **Export Layouts**: Named export configurations that define what data to export from a model
3. **Dynamic Configuration**: All aspects of an export (columns, filters, sorts, functions) are configured via database records

### Key Services

- **DynamicExportService** (`src/Services/DynamicExportService.php`): Main export execution engine with enhanced relation handling and validation
- **ModelRelationInspector** (`src/Helpers/ModelRelationInspector.php`): Discovers model columns and relationships using reflection with transaction safety
- **TransformationFunctions** (`src/Services/TransformationFunctions.php`): Provides 22 built-in transformation functions for data formatting
- **ExportFactory** (`src/Exports/ExportFactory.php`): Factory for creating export handlers (CSV, JSON, etc.)

### Database Schema

The package uses 7 tables that work together (all using UUIDs as primary keys):
- `export_models`: Registered exportable models
- `export_model_relations`: Model columns and relationships (supports dot notation like "order.customer.name")
- `export_layouts`: Export configurations
- `export_columns`: Output columns with transformations and aggregations
- `export_filters`: Query constraints with various operators
- `export_sorts`: Ordering configuration
- `export_functions`: Reusable transformation functions

### Important Patterns

1. **Nested Relationship Traversal**: Use dot notation to access deeply nested relationships (e.g., "workItem.workOrder.customer.contact.org_name")
2. **Collection Filtering**: Use relation operator filters to extract specific items from collections (e.g., identifiers by type)
3. **Filter Architecture**: Three types of filters:
   - Layout filters (main query WHERE conditions)
   - Column filters with regular operators (main query filters) 
   - Column filters with relation operator (eager loading constraints only)
4. **Operator Types**: All filter operators are defined in `src/Enums/OperatorType.php` with their query builder implementations
5. **Function Pipeline**: Columns can apply transformation functions stored in `export_functions` table
6. **Aggregations**: Built-in support for sum, count, avg, min, max on collection relationships
7. **Smart Eager Loading**: The service automatically determines required relations and loads intermediate paths
8. **Validation & Debugging**: Built-in validation catches configuration errors; `getQuery($layout, $requestData)` returns the built query for inspection, and errors and warnings are logged

### Package Structure

```
src/
|-- Models/          # Eloquent models for export tables
|-- Services/        # Core export logic (DynamicExportService, TransformationFunctions)
|-- Exports/         # Export handlers (CSV, JSON) and factory
|-- Enums/           # OperatorType enum with all filter operators
|-- Console/         # Artisan commands (export:import-models, export:seed-functions)
|-- Jobs/            # ProcessExportJob for queued background exports
|-- Helpers/         # Model introspection utilities
`-- Facades/         # Laravel facade for package

docs/                # Documentation for Claude's reference
|-- getting-started.md   # Install and first export
|-- configuration.md     # Config reference
|-- troubleshooting.md   # Common issues
|-- directory-structure.md   # Detailed file organization guide
|-- concepts/            # Architecture, schema, filter model
|-- guides/              # Task-oriented guides (layouts, filters, sorting, pivots)
|-- reference/           # api.md, commands.md, functions.md, operators.md
`-- examples/            # Basic through large-scale worked examples
```

### Development Notes

- All models use proper Laravel relationships and type casting
- All models use UUIDs as primary keys (via Laravel's HasUuids trait)
- The DynamicExportService is the entry point for executing exports with comprehensive validation
- Model introspection happens via reflection to discover available columns and relationships dynamically
- Enhanced architecture properly separates layout filters from column filters
- Collection filtering allows extracting specific items from related collections
- Smart eager loading automatically handles nested relationships and intermediate paths
- Use `getQuery($layout, $requestData)` on DynamicExportService to inspect the built query; errors and warnings are logged

### Recent Enhancements

1. **Model Attributes**: 
   - ExportModel now includes an `instance` attribute that returns the actual Eloquent model instance
   - Access via `$exportModel->instance` to avoid conflicts with the `model` property

2. **Relationship Validation**:
   - ExportModelRelation now has a `whereNested()` scope for validating nested relationships
   - Example: `ExportModelRelation::whereNested('workItem.workOrder')` traverses the relationship chain
   - Supports dot notation for deep relationship traversal

3. **Transformation Functions**:
   - 22 built-in functions available via `php artisan export:seed-functions`
   - Categories: Date/Time, String, Number, Boolean, Array/JSON, and Utility functions
   - Each function has configuration parameters for customization

4. **Export Handlers**:
   - Streaming support for large datasets in both CSV and JSON formats
   - Memory-efficient processing using Laravel's lazy collections
   - Extensible architecture for custom export formats

### Workflow Reminders

- Always add results to 'Recent Changes' on @todos.md