<?php

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Comment;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->inspector = new ModelRelationInspector;
});

it('validates nested paths segment by segment', function () {
    $valid = $this->inspector->validateNestedPath(User::class, 'posts.comments');

    expect($valid['valid'])->toBeTrue()
        ->and($valid['final_model'])->toBe(Comment::class)
        ->and($valid['final_columns'])->toContain('content')
        ->and($valid['segments'])->toHaveCount(2)
        ->and($valid['segments'][0])->toBe(['name' => 'posts', 'model' => User::class, 'type' => 'HasMany', 'is_collection' => true])
        ->and($valid['segments'][1]['model'])->toBe(Post::class)
        ->and($valid['segments'][1]['is_collection'])->toBeTrue();

    $invalid = $this->inspector->validateNestedPath(User::class, 'posts.nope.user');

    expect($invalid['valid'])->toBeFalse()
        ->and($invalid['error'])->toBe("Method 'nope' not found on ".Post::class.' at segment 2')
        ->and($invalid['segments'])->toHaveCount(1)
        ->and($invalid['final_model'])->toBeNull();
});

it('tracks collection flags through and out of collection segments', function () {
    $result = $this->inspector->validateNestedPath(Post::class, 'user.posts.user');

    expect($result['valid'])->toBeTrue()
        ->and($result['final_model'])->toBe(User::class)
        ->and($result['segments'][0]['is_collection'])->toBeFalse()
        ->and($result['segments'][1]['is_collection'])->toBeTrue()
        ->and($result['segments'][2]['is_collection'])->toBeFalse();
});

it('can get model columns', function () {
    $columns = $this->inspector->getModelColumns(User::class);

    expect($columns)->toBeArray()
        ->and($columns)->toContain('id', 'name', 'email', 'created_at', 'updated_at');
});

it('can get model relations', function () {
    $relations = $this->inspector->getModelRelations(User::class);

    expect($relations)->toBeArray()
        ->and($relations)->toHaveKeys(['posts', 'comments'])
        ->and($relations['posts'])->toHaveKeys(['type', 'related_model'])
        ->and($relations['posts']['type'])->toBe('HasMany')
        ->and($relations['posts']['related_model'])->toBe(Post::class);
});

it('returns correct relation types', function () {
    $userRelations = $this->inspector->getModelRelations(User::class);
    $postRelations = $this->inspector->getModelRelations(Post::class);

    expect($userRelations['posts']['type'])->toBe('HasMany')
        ->and($userRelations['comments']['type'])->toBe('HasMany')
        ->and($postRelations['user']['type'])->toBe('BelongsTo')
        ->and($postRelations['comments']['type'])->toBe('HasMany');
});

it('handles models with no relations', function () {
    // Create a model without relations
    $modelClass = new class extends Model
    {
        protected $table = 'test_table';
    };

    $relations = $this->inspector->getModelRelations(get_class($modelClass));

    expect($relations)->toBeArray()
        ->and($relations)->toBeEmpty();
});

it('ignores non-relation methods', function () {
    // Create a model with non-relation methods
    $modelClass = new class extends Model
    {
        protected $table = 'test_table';

        public function customMethod()
        {
            return 'not a relation';
        }

        public function scopeActive($query)
        {
            return $query->where('active', true);
        }
    };

    $relations = $this->inspector->getModelRelations(get_class($modelClass));

    expect($relations)->toBeArray()
        ->and($relations)->toBeEmpty();
});

it('handles BelongsTo relations correctly', function () {
    $relations = $this->inspector->getModelRelations(Post::class);

    expect($relations['user']['type'])->toBe('BelongsTo')
        ->and($relations['user']['related_model'])->toBe(User::class);
});

it('handles HasMany relations correctly', function () {
    $relations = $this->inspector->getModelRelations(User::class);

    expect($relations['posts']['type'])->toBe('HasMany')
        ->and($relations['posts']['related_model'])->toBe(Post::class);
});

it('works with deeply nested models', function () {
    $commentRelations = $this->inspector->getModelRelations(Comment::class);

    expect($commentRelations)->toHaveKeys(['post', 'user'])
        ->and($commentRelations['post']['type'])->toBe('BelongsTo')
        ->and($commentRelations['post']['related_model'])->toBe(Post::class)
        ->and($commentRelations['user']['type'])->toBe('BelongsTo')
        ->and($commentRelations['user']['related_model'])->toBe(User::class);
});
