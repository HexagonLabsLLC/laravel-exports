<?php

use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use Illuminate\Database\Eloquent\Builder;

test('applies sorting for direct BelongsTo relationships', function () {
    $relation = Mockery::mock(ExportModelRelation::class);
    $relation->shouldReceive('getAttribute')->with('relation')->andReturn('author');
    $relation->shouldReceive('getAttribute')->with('is_collection')->andReturn(false);
    $relation->shouldReceive('getAttribute')->with('is_column')->andReturn(false);
    $relation->shouldReceive('getAttribute')->with('metadata')->andReturn(['sort_column' => 'name']);
    $relation->shouldReceive('offsetExists')->andReturn(true);

    $sort = Mockery::mock(ExportSort::class);
    $sort->shouldReceive('getAttribute')->with('export_model_relation_id')->andReturn('test-relation');
    $sort->shouldReceive('getAttribute')->with('modelRelation')->andReturn($relation);
    $sort->shouldReceive('getAttribute')->with('direction')->andReturn('asc');
    $sort->shouldReceive('offsetExists')->andReturn(true);

    // Create a mock query builder to verify the join is applied
    $query = Mockery::mock(Builder::class);
    $mockModel = Mockery::mock('Post');
    $mockRelation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
    $relatedModel = Mockery::mock('User');

    $query->shouldReceive('getModel')->andReturn($mockModel);
    $mockModel->shouldReceive('getTable')->andReturn('posts');
    $mockModel->shouldReceive('author')->andReturn($mockRelation);

    $mockRelation->shouldReceive('getRelated')->andReturn($relatedModel);
    $relatedModel->shouldReceive('getTable')->andReturn('users');
    $mockRelation->shouldReceive('getForeignKeyName')->andReturn('author_id');
    $mockRelation->shouldReceive('getOwnerKeyName')->andReturn('id');

    $query->shouldReceive('leftJoin')
        ->with('users', 'posts.author_id', '=', 'users.id')
        ->once()
        ->andReturn($query);

    $query->shouldReceive('orderBy')
        ->with('users.name', 'asc')
        ->once()
        ->andReturn($query);

    $query->shouldReceive('select')
        ->with('posts.*')
        ->once()
        ->andReturn($query);

    $service = new DynamicExportService;
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('applySortForRelation');
    $method->setAccessible(true);

    $method->invoke($service, $query, $sort);

    expect(true)->toBeTrue();
});

test('applies sorting for HasMany relationships using count', function () {
    $relation = Mockery::mock(ExportModelRelation::class);
    $relation->shouldReceive('getAttribute')->with('relation')->andReturn('posts');
    $relation->shouldReceive('getAttribute')->with('is_collection')->andReturn(true);
    $relation->shouldReceive('getAttribute')->with('metadata')->andReturn(null);

    $sort = Mockery::mock(ExportSort::class);
    $sort->shouldReceive('getAttribute')->with('export_model_relation_id')->andReturn('test-relation');
    $sort->shouldReceive('getAttribute')->with('modelRelation')->andReturn($relation);
    $sort->shouldReceive('getAttribute')->with('direction')->andReturn('desc');

    $query = Mockery::mock(Builder::class);

    $query->shouldReceive('withCount')
        ->once()
        ->andReturnUsing(function ($relations) use ($query) {
            expect(array_keys($relations))->toBe(['posts']);

            return $query;
        });

    $query->shouldReceive('orderBy')
        ->with('posts_count', 'desc')
        ->once()
        ->andReturn($query);

    $service = new DynamicExportService;

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('applyRelationSubquerySort');
    $method->setAccessible(true);

    $method->invoke($service, $query, $sort, 'posts');

    expect(true)->toBeTrue();
});
