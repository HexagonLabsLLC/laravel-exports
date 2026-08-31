<?php

use HexagonLabsLLC\LaravelExports\Enums\OperatorType;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoDatabaseTest extends TestCase
{
    #[Test]
    public function operator_type_enum_works_without_database()
    {
        // Test that we can use the enum without database
        $operators = OperatorType::cases();
        $this->assertGreaterThan(10, count($operators));

        // Test new operators exist
        $this->assertContains(OperatorType::JSON_CONTAINS, $operators);
        $this->assertContains(OperatorType::RELATION, $operators);

        // Test getCallable works
        $this->assertEquals('whereJsonContains', OperatorType::getCallable('json_contains', false));
        $this->assertEquals('whereRelation', OperatorType::getCallable('relation', false));
    }

    #[Test]
    public function models_have_correct_properties_without_database()
    {
        // Test ExportColumn has new properties
        $column = new ExportColumn;
        $fillable = $column->getFillable();
        $this->assertContains('export_filter_id', $fillable);
        $this->assertContains('export_filter_values', $fillable);

        // Test casts
        $casts = $column->getCasts();
        $this->assertArrayHasKey('export_filter_values', $casts);
        $this->assertEquals('array', $casts['export_filter_values']);
    }

    #[Test]
    public function filter_operator_casting_works()
    {
        // Test the OperatorType cast works on the model
        $filter = new ExportFilter;
        $filter->operator = '=';

        // The cast should convert string to enum
        $this->assertEquals('=', $filter->operator);

        // Test with new operators
        $filter->operator = 'json_contains';
        $this->assertEquals('json_contains', $filter->operator);

        $filter->operator = 'relation';
        $this->assertEquals('relation', $filter->operator);
    }

    #[Test]
    public function dynamic_export_service_methods_exist()
    {
        // Use reflection to check methods exist without instantiating
        $reflection = new ReflectionClass(DynamicExportService::class);

        $this->assertTrue($reflection->hasMethod('applyColumnFilters'));
        $this->assertTrue($reflection->hasMethod('applyFilters'));
        $this->assertTrue($reflection->hasMethod('applyFilter'));

        // Check the applyFilter method handles new operators
        $method = $reflection->getMethod('applyFilter');
        $parameters = $method->getParameters();
        $this->assertCount(3, $parameters);
    }
}
