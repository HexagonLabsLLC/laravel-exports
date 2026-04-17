<?php

use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Services\TransformationFunctions;

test('seeds transformation functions', function () {
    $this->artisan('export:seed-functions')
        ->expectsOutputToContain('Seeding transformation functions...')
        ->expectsOutputToContain('Transformation functions seeding complete!')
        ->assertSuccessful();

    // Verify functions were created
    $functions = TransformationFunctions::getAvailableFunctions();
    expect(ExportFunction::count())->toBe(count($functions));

    // Check a specific function
    $formatDate = ExportFunction::where('name', 'Format Date')->first();
    expect($formatDate)->not->toBeNull();
    expect($formatDate->callable)->toBe('HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::formatDate');
    expect($formatDate->parameter_count)->toBe(2);
    expect($formatDate->value_parameter_index)->toBe(0);
});

test('skips existing functions by default', function () {
    // First seed
    $this->artisan('export:seed-functions')->assertSuccessful();
    $initialCount = ExportFunction::count();

    // Second seed without force
    $this->artisan('export:seed-functions')
        ->expectsOutputToContain('Skipped:')
        ->assertSuccessful();

    // Count should remain the same
    expect(ExportFunction::count())->toBe($initialCount);
});

test('updates existing functions with force option', function () {
    // First seed
    $this->artisan('export:seed-functions')->assertSuccessful();

    // Modify a function
    $function = ExportFunction::first();
    $originalName = $function->name;
    $function->update(['name' => 'Modified Name']);

    // Second seed with force
    $this->artisan('export:seed-functions --force')
        ->expectsOutputToContain('Updated:')
        ->assertSuccessful();

    // Name should be restored
    $function->refresh();
    expect($function->name)->toBe($originalName);
});

test('all seeded functions have valid callables', function () {
    $this->artisan('export:seed-functions')->assertSuccessful();

    ExportFunction::all()->each(function ($function) {
        expect(is_callable($function->callable))->toBeTrue();
    });
});
