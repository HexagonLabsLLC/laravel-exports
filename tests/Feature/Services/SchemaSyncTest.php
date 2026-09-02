<?php

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Services\SchemaSync;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Tag;

it('syncs a model class into the catalog', function () {
    $sync = new SchemaSync(app(ModelRelationInspector::class));

    $exportModel = $sync->syncModel(Post::class);

    expect($exportModel->model)->toBe(Post::class)
        ->and($exportModel->schema_hash)->not->toBeNull();

    $rows = $exportModel->relations()->get();

    expect($rows->where('is_column', true)->pluck('relation')->all())->toContain('title', 'published')
        ->and($rows->where('is_column', false)->pluck('relation')->all())->toContain('user', 'comments', 'tags');

    $tags = $rows->firstWhere('relation', 'tags');
    $relatedTagModel = ExportModel::where('model', Tag::class)->first();

    expect($tags->is_collection)->toBeTrue()
        ->and($relatedTagModel)->not->toBeNull()
        ->and($tags->related_model_id)->toBe($relatedTagModel->id)
        ->and($rows->firstWhere('relation', 'user')->is_collection)->toBeFalse();

    $count = ExportModelRelation::count();
    $sync->syncModel(Post::class);

    expect(ExportModelRelation::count())->toBe($count);
});

it('lazy mode syncs on miss and trusts existing rows', function () {
    config()->set('laravel-exports.schema_sync', 'lazy');

    $first = (new SchemaSync(app(ModelRelationInspector::class)))->ensureFresh(Post::class);
    $rowCount = ExportModelRelation::count();

    $again = (new SchemaSync(app(ModelRelationInspector::class)))->ensureFresh(Post::class);

    expect($again->id)->toBe($first->id)
        ->and(ExportModelRelation::count())->toBe($rowCount);
});

it('verify mode re-syncs when the schema fingerprint drifts', function () {
    config()->set('laravel-exports.schema_sync', 'verify');

    $model = (new SchemaSync(app(ModelRelationInspector::class)))->syncModel(Post::class);
    $hash = $model->schema_hash;

    $model->update(['schema_hash' => 'stale']);
    ExportModelRelation::where('export_model_id', $model->id)->where('relation', 'tags')->delete();

    $refreshed = (new SchemaSync(app(ModelRelationInspector::class)))->ensureFresh(Post::class);

    expect($refreshed->schema_hash)->toBe($hash)
        ->and($refreshed->relations()->where('relation', 'tags')->exists())->toBeTrue();
});

it('manual mode never syncs and throws for unknown models', function () {
    config()->set('laravel-exports.schema_sync', 'manual');

    expect(fn () => (new SchemaSync(app(ModelRelationInspector::class)))->ensureFresh(Post::class))
        ->toThrow(RuntimeException::class, 'not registered in the export catalog');

    $existing = ExportModel::create(['title' => 'Post', 'model' => Post::class]);
    $found = (new SchemaSync(app(ModelRelationInspector::class)))->ensureFresh(Post::class);

    expect($found->id)->toBe($existing->id)
        ->and(ExportModelRelation::count())->toBe(0);
});

it('syncs referenced nested and dotted column paths', function () {
    $sync = new SchemaSync(app(ModelRelationInspector::class));
    $model = $sync->syncModel(Post::class);

    $nested = $sync->syncPath($model, 'tags.category');
    $columnPath = $sync->syncColumnPath($model, 'user.name');

    expect($nested->relation)->toBe('tags.category')
        ->and($nested->is_column)->toBeFalse()
        ->and($columnPath->relation)->toBe('user.name')
        ->and($columnPath->is_column)->toBeTrue()
        ->and($sync->syncPath($model, 'nope.nothing'))->toBeNull()
        ->and($sync->syncColumnPath($model, 'user.not_a_column'))->toBeNull();
});

it('describes a model for ui consumption', function () {
    $described = (new SchemaSync(app(ModelRelationInspector::class)))->describe(Post::class);

    expect($described['model'])->toBeInstanceOf(ExportModel::class)
        ->and($described['columns']->pluck('relation')->all())->toContain('title')
        ->and($described['relations']->pluck('relation')->all())->toContain('tags');
});
