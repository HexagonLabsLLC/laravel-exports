<?php

use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Comment;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // The package root, not testbench's base_path()
    $this->testModelsPath = realpath(__DIR__.'/../../TestModels');
    $this->testModelsNamespace = 'HexagonLabsLLC\\LaravelExports\\Tests\\TestModels';
});

it('discovers nested relation paths with deep discovery', function () {
    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
        '--deep' => true,
        '--deep-level' => 3,
    ]);

    $userModel = ExportModel::where('model', User::class)->first();
    $commentModel = ExportModel::where('model', Comment::class)->first();
    $rows = $userModel->relations()->get();

    $postsComments = $rows->firstWhere('relation', 'posts.comments');

    expect($postsComments)->not->toBeNull()
        ->and($postsComments->is_collection)->toBeTrue()
        ->and($postsComments->related_model_id)->toBe($commentModel->id)
        ->and($postsComments->title)->toBe('Posts > Comments')
        ->and($rows->firstWhere('relation', 'comments.post')->is_collection)->toBeFalse()
        ->and($rows->firstWhere('relation', 'posts.comments.user'))->not->toBeNull()
        ->and($rows->firstWhere('relation', 'posts.comments.post'))->toBeNull()
        ->and($rows->filter(fn ($row) => substr_count($row->relation, '.') >= 3))->toHaveCount(0);

    $count = ExportModelRelation::count();

    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
        '--deep' => true,
        '--deep-level' => 3,
    ]);

    expect(ExportModelRelation::count())->toBe($count);
});

it('can import models from default directory', function () {
    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
    ]);

    // 5 test models: User, Post, Comment, Tag, Category
    expect(ExportModel::count())->toBeGreaterThanOrEqual(3)
        ->and(ExportModel::where('model', User::class)->exists())->toBeTrue()
        ->and(ExportModel::where('model', Post::class)->exists())->toBeTrue()
        ->and(ExportModel::where('model', Comment::class)->exists())->toBeTrue();
});

it('can import with skip-relations flag', function () {
    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
        '--skip-relations' => true,
    ]);

    // Models should be imported
    expect(ExportModel::count())->toBeGreaterThan(0);

    // But no relations should be created
    $userModel = ExportModel::where('model', User::class)->first();
    expect($userModel->relations()->count())->toBe(0);
});

it('skips existing models without force flag', function () {
    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
    ]);

    $firstCount = ExportModel::count();

    // Import again without force
    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
    ]);

    expect(ExportModel::count())->toBe($firstCount);
});

it('updates existing models with force flag', function () {
    // Create a model with different title
    $exportModel = ExportModel::create([
        'title' => 'Old Title',
        'model' => User::class,
    ]);

    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
        '--force' => true,
    ]);

    $exportModel->refresh();
    expect($exportModel->title)->toBe('User');
});

it('syncs model relations by default', function () {
    Artisan::call('export:import-models', [
        '--path' => $this->testModelsPath,
        '--namespace' => $this->testModelsNamespace,
    ]);

    $userModel = ExportModel::where('model', User::class)->first();
    $postModel = ExportModel::where('model', Post::class)->first();

    // Check columns
    expect($userModel->relations()->where('is_column', true)->count())->toBeGreaterThan(0)
        ->and($userModel->relations()->where('relation', 'name')->exists())->toBeTrue()
        ->and($userModel->relations()->where('relation', 'email')->exists())->toBeTrue();

    // Check relations
    expect($userModel->relations()->where('is_column', false)->where('relation', 'posts')->exists())->toBeTrue()
        ->and($userModel->relations()->where('is_column', false)->where('relation', 'comments')->exists())->toBeTrue();

    // Check relation is linked to correct model
    $postsRelation = $userModel->relations()->where('relation', 'posts')->first();
    expect($postsRelation->related_model_id)->toBe($postModel->id)
        ->and($postsRelation->is_collection)->toBeTrue();
});

it('handles non-existent directory gracefully', function () {
    $result = Artisan::call('export:import-models', [
        '--path' => '/tmp/non_existent_path_'.uniqid(),
    ]);

    expect($result)->toBe(1);
});

it('handles empty directory gracefully', function () {
    $emptyDir = sys_get_temp_dir().'/laravel_exports_test_empty_'.uniqid();
    mkdir($emptyDir);

    Artisan::call('export:import-models', [
        '--path' => $emptyDir,
        '--namespace' => 'Tests\\EmptyDir',
    ]);

    rmdir($emptyDir);

    expect(ExportModel::count())->toBe(0);
});
