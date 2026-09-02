<?php

namespace Tests\Unit;

use HexagonLabsLLC\LaravelExports\Enums\OperatorType;
use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ValidationTest extends TestCase
{
    #[Test]
    public function it_has_json_contains_operator_in_enum()
    {
        $operators = OperatorType::cases();
        $operatorValues = array_map(fn ($case) => $case->value, $operators);

        $this->assertContains('json_contains', $operatorValues);
    }

    #[Test]
    public function it_has_relation_operator_in_enum()
    {
        $operators = OperatorType::cases();
        $operatorValues = array_map(fn ($case) => $case->value, $operators);

        $this->assertContains('relation', $operatorValues);
    }

    #[Test]
    public function export_column_has_filter_fields()
    {
        $reflection = new ReflectionClass(ExportColumn::class);

        // Check fillable array contains the new fields
        $fillableProperty = $reflection->getProperty('fillable');
        $fillableProperty->setAccessible(true);
        $fillable = $fillableProperty->getDefaultValue();

        $this->assertContains('export_filter_id', $fillable);
        $this->assertContains('export_filter_values', $fillable);
    }

    #[Test]
    public function export_column_has_casts_for_filter_values()
    {
        $reflection = new ReflectionClass(ExportColumn::class);

        // Check that casts() method exists (Laravel 12 style)
        $this->assertTrue($reflection->hasMethod('casts'), 'ExportColumn should have casts() method');

        $castsMethod = $reflection->getMethod('casts');
        $castsMethod->setAccessible(true);

        // Create a mock instance to call the method
        $column = new ExportColumn;
        $casts = $castsMethod->invoke($column);

        $this->assertArrayHasKey('export_filter_values', $casts);
        $this->assertEquals('array', $casts['export_filter_values']);
    }

    #[Test]
    public function export_model_has_title_field()
    {
        $reflection = new ReflectionClass(ExportModel::class);

        // Check fillable array contains title
        $fillableProperty = $reflection->getProperty('fillable');
        $fillableProperty->setAccessible(true);
        $fillable = $fillableProperty->getDefaultValue();

        $this->assertContains('title', $fillable);
    }

    #[Test]
    public function dynamic_export_service_has_apply_column_filters_method()
    {
        $reflection = new ReflectionClass(DynamicExportService::class);

        $this->assertTrue($reflection->hasMethod('applyColumnFilters'));

        $method = $reflection->getMethod('applyColumnFilters');
        $this->assertTrue($method->isProtected());
    }

    #[Test]
    public function operator_type_enum_has_correct_methods()
    {
        $reflection = new ReflectionClass(OperatorType::class);

        $this->assertTrue($reflection->hasMethod('getOperator'));
        $this->assertTrue($reflection->hasMethod('getCallable'));

        // Check for json_contains and relation support in getCallable
        $jsonContainsCallable = OperatorType::getCallable('json_contains', false);
        $this->assertEquals('whereJsonContains', $jsonContainsCallable);

        $relationCallable = OperatorType::getCallable('relation', false);
        $this->assertEquals('whereRelation', $relationCallable);

        // Check OR variants
        $jsonContainsOrCallable = OperatorType::getCallable('json_contains', true);
        $this->assertEquals('orWhereJsonContains', $jsonContainsOrCallable);

        $relationOrCallable = OperatorType::getCallable('relation', true);
        $this->assertEquals('orWhereRelation', $relationOrCallable);
    }

    #[Test]
    public function all_operator_types_are_defined()
    {
        $expectedOperators = [
            '=',
            '!=',
            '>',
            '<',
            '>=',
            '<=',
            'in',
            'not_in',
            'between',
            'like',
            'null',
            'not_null',
            'json_contains',
            'relation',
        ];

        $actualOperators = array_map(fn ($case) => $case->value, OperatorType::cases());

        foreach ($expectedOperators as $operator) {
            $this->assertContains($operator, $actualOperators, "Operator '$operator' is missing from OperatorType enum");
        }
    }
}
