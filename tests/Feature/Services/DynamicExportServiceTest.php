<?php

use HexagonLabsLLC\LaravelExports\Models\ExportColumn;
use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportModelRelation;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Services\DynamicExportService;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Category;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Comment;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Post;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\Tag;
use HexagonLabsLLC\LaravelExports\Tests\TestModels\User;

beforeEach(function () {
    // Create test data
    $this->users = User::insert([
        ['name' => 'John Doe', 'email' => 'john@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Bob Johnson', 'email' => 'bob@example.com', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $userIds = User::orderBy('id')->pluck('id')->toArray();

    Post::insert([
        ['user_id' => $userIds[0], 'title' => 'First Post', 'content' => 'Content 1', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $userIds[0], 'title' => 'Second Post', 'content' => 'Content 2', 'published' => false, 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $userIds[1], 'title' => 'Third Post', 'content' => 'Content 3', 'published' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $postIds = Post::orderBy('id')->pluck('id')->toArray();

    Comment::insert([
        ['post_id' => $postIds[0], 'user_id' => $userIds[1], 'content' => 'Great post!', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[0], 'user_id' => $userIds[2], 'content' => 'Thanks for sharing', 'created_at' => now(), 'updated_at' => now()],
        ['post_id' => $postIds[2], 'user_id' => $userIds[0], 'content' => 'Interesting', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Create categories for testing column filters
    Category::insert([
        ['name' => 'Technology', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Lifestyle', 'created_at' => now(), 'updated_at' => now()],
        ['name' => 'Business', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $categoryIds = Category::orderBy('id')->pluck('id')->toArray();

    // Create tags with different categories
    Tag::insert([
        ['post_id' => $postIds[0], 'category_id' => $categoryIds[0], 'value' => '120', 'created_at' => now(), 'updated_at' => now()], // Technology tag for post 1
        ['post_id' => $postIds[0], 'category_id' => $categoryIds[1], 'value' => '50', 'created_at' => now(), 'updated_at' => now()],  // Lifestyle tag for post 1
        ['post_id' => $postIds[1], 'category_id' => $categoryIds[0], 'value' => '75', 'created_at' => now(), 'updated_at' => now()],  // Technology tag for post 2
        ['post_id' => $postIds[1], 'category_id' => $categoryIds[2], 'value' => '200', 'created_at' => now(), 'updated_at' => now()], // Business tag for post 2
        ['post_id' => $postIds[2], 'category_id' => $categoryIds[1], 'value' => '30', 'created_at' => now(), 'updated_at' => now()],  // Lifestyle tag for post 3
    ]);

    // Set up export model and relations
    $this->exportModel = ExportModel::create([
        'title' => 'User Export',
        'model' => User::class,
    ]);

    // Add columns
    ExportModelRelation::create([
        'export_model_id' => $this->exportModel->id,
        'title' => 'id',
        'relation' => 'id',
        'is_column' => true,
        'is_collection' => false,
    ]);

    ExportModelRelation::create([
        'export_model_id' => $this->exportModel->id,
        'title' => 'name',
        'relation' => 'name',
        'is_column' => true,
        'is_collection' => false,
    ]);

    ExportModelRelation::create([
        'export_model_id' => $this->exportModel->id,
        'title' => 'email',
        'relation' => 'email',
        'is_column' => true,
        'is_collection' => false,
    ]);

    // Add posts relation
    $postModel = ExportModel::create([
        'title' => 'Post Export',
        'model' => Post::class,
    ]);

    // Create Tag and Category models for column filter testing
    $tagModel = ExportModel::create([
        'title' => 'Tag Export',
        'model' => Tag::class,
    ]);

    $categoryModel = ExportModel::create([
        'title' => 'Category Export',
        'model' => Category::class,
    ]);

    ExportModelRelation::create([
        'export_model_id' => $this->exportModel->id,
        'title' => 'posts',
        'relation' => 'posts',
        'is_column' => false,
        'is_collection' => true,
        'related_model_id' => $postModel->id,
    ]);

    // Add tags relation to Post model
    ExportModelRelation::create([
        'export_model_id' => $postModel->id,
        'title' => 'tags',
        'relation' => 'tags',
        'is_column' => false,
        'is_collection' => true,
        'related_model_id' => $tagModel->id,
    ]);

    // Add category relation to Tag model
    ExportModelRelation::create([
        'export_model_id' => $tagModel->id,
        'title' => 'category',
        'relation' => 'category',
        'is_column' => false,
        'is_collection' => false,
        'related_model_id' => $categoryModel->id,
    ]);

    // Add name column to Category model
    ExportModelRelation::create([
        'export_model_id' => $categoryModel->id,
        'title' => 'name',
        'relation' => 'name',
        'is_column' => true,
        'is_collection' => false,
    ]);

    $this->service = app(DynamicExportService::class);
});

it('can export basic columns', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'Basic Export',
    ]);

    $nameRelation = ExportModelRelation::where('relation', 'name')->first();
    $emailRelation = ExportModelRelation::where('relation', 'email')->first();

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $emailRelation->id,
        'title' => 'Email',
        'value_path' => 'email',
        'position' => 2,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(3)
        ->and($result[0])->toHaveKeys(['Name', 'Email'])
        ->and($result[0]['Name'])->toBe('John Doe')
        ->and($result[0]['Email'])->toBe('john@example.com');
});

it('can apply filters', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'Filtered Export',
    ]);

    $nameRelation = ExportModelRelation::where('relation', 'name')->first();

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_id' => $this->exportModel->id,
        'export_model_relation_id' => $nameRelation->id,
        'operator' => 'like',
        'value' => '%John%',
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(2)
        ->and(collect($result)->pluck('Name')->toArray())->toContain('John Doe', 'Bob Johnson');
});

it('can apply sorting', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'Sorted Export',
    ]);

    $nameRelation = ExportModelRelation::where('relation', 'name')->first();

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportSort::create([
        'export_layout_id' => $layout->id,
        'export_model_id' => $this->exportModel->id,
        'export_model_relation_id' => $nameRelation->id,
        'direction' => 'desc',
        'priority' => 1,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0]['Name'])->toBe('John Doe')
        ->and($result[1]['Name'])->toBe('Jane Smith')
        ->and($result[2]['Name'])->toBe('Bob Johnson');
});

it('can handle aggregations', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'Aggregated Export',
    ]);

    $nameRelation = ExportModelRelation::where('relation', 'name')->first();
    $postsRelation = ExportModelRelation::where('relation', 'posts')->first();

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $postsRelation->id,
        'title' => 'Post Count',
        'value_path' => 'posts',
        'position' => 2,
        'aggregator' => 'count',
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result[0]['Post Count'])->toBe(2) // John has 2 posts
        ->and($result[1]['Post Count'])->toBe(1) // Jane has 1 post
        ->and($result[2]['Post Count'])->toBe(0); // Bob has 0 posts
});

it('can export as CSV', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'CSV Export',
    ]);

    $nameRelation = ExportModelRelation::where('relation', 'name')->first();
    $emailRelation = ExportModelRelation::where('relation', 'email')->first();

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $emailRelation->id,
        'title' => 'Email',
        'value_path' => 'email',
        'position' => 2,
    ]);

    $result = $this->service->exportTo($layout->id, 'csv');

    expect($result)->toBeString()
        ->and($result)->toContain('Name,Email')
        ->and($result)->toContain('John Doe')
        ->and($result)->toContain('john@example.com');
});

it('can export as JSON', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'JSON Export',
    ]);

    $nameRelation = ExportModelRelation::where('relation', 'name')->first();

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $nameRelation->id,
        'title' => 'Name',
        'value_path' => 'name',
        'position' => 1,
    ]);

    $result = $this->service->exportTo($layout->id, 'json');
    $decoded = json_decode($result, true);

    // JSON handler wraps data by default: {"meta": {...}, "data": [...]}
    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('data')
        ->and($decoded['data'])->toHaveCount(3)
        ->and($decoded['data'][0]['Name'])->toBe('John Doe');
});

it('throws exception for invalid layout', function () {
    $this->service->executeExport('invalid-uuid');
})->throws(Exception::class, 'Layout not found');

it('throws exception for unsupported format', function () {
    $layout = ExportLayout::create([
        'export_model_id' => $this->exportModel->id,
        'name' => 'Test Export',
    ]);

    $this->service->exportTo($layout->id, 'xml');
})->throws(InvalidArgumentException::class, 'Unsupported export format: xml');

it('can filter collection relations with column filters', function () {
    // Create a layout that exports posts with specific tag values filtered by category
    $postModel = ExportModel::where('model', Post::class)->first();
    $tagModel = ExportModel::where('model', Tag::class)->first();

    $layout = ExportLayout::create([
        'export_model_id' => $postModel->id,
        'name' => 'Post Tags Export',
    ]);

    // Get the relations
    $tagsRelation = ExportModelRelation::where('export_model_id', $postModel->id)
        ->where('relation', 'tags')->first();
    $categoryRelation = ExportModelRelation::where('export_model_id', $tagModel->id)
        ->where('relation', 'category')->first();

    // Create a filter that selects tags where category.name = 'Technology'
    $techFilter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_id' => $tagModel->id,
        'export_model_relation_id' => $categoryRelation->id,
        'operator' => 'relation',
        'value' => 'Technology',
        'logical_operator' => 'and',
    ]);

    // Create a filter that selects tags where category.name = 'Lifestyle'
    $lifestyleFilter = ExportFilter::create([
        'export_layout_id' => $layout->id,
        'export_model_id' => $tagModel->id,
        'export_model_relation_id' => $categoryRelation->id,
        'operator' => 'relation',
        'value' => 'Lifestyle',
        'logical_operator' => 'and',
    ]);

    // Add post title column
    $titleRelation = ExportModelRelation::where('export_model_id', $postModel->id)
        ->where('relation', 'title')->first();

    if (!$titleRelation) {
        $titleRelation = ExportModelRelation::create([
            'export_model_id' => $postModel->id,
            'title' => 'title',
            'relation' => 'title',
            'is_column' => true,
            'is_collection' => false,
        ]);
    }

    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $titleRelation->id,
        'title' => 'Post Title',
        'value_path' => 'title',
        'position' => 1,
    ]);

    // Add column for Technology tag value with filter
    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $tagsRelation->id,
        'export_filter_id' => $techFilter->id,
        'export_filter_values' => 'Technology',
        'title' => 'Tech Value',
        'value_path' => 'tags.value',
        'default' => '0',
        'position' => 2,
    ]);

    // Add column for Lifestyle tag value with filter
    ExportColumn::create([
        'export_layout_id' => $layout->id,
        'export_model_relation_id' => $tagsRelation->id,
        'export_filter_id' => $lifestyleFilter->id,
        'export_filter_values' => 'Lifestyle',
        'title' => 'Lifestyle Value',
        'value_path' => 'tags.value',
        'default' => '0',
        'position' => 3,
    ]);

    $result = $this->service->executeExport($layout->id)->toArray();

    expect($result)->toHaveCount(3)
        ->and($result[0]['Post Title'])->toBe('First Post')
        ->and($result[0]['Tech Value'])->toBe('120') // Post 1 has Technology tag with value 120
        ->and($result[0]['Lifestyle Value'])->toBe('50') // Post 1 has Lifestyle tag with value 50
        ->and($result[1]['Post Title'])->toBe('Second Post')
        ->and($result[1]['Tech Value'])->toBe('75') // Post 2 has Technology tag with value 75
        ->and($result[1]['Lifestyle Value'])->toBe('0') // Post 2 has no Lifestyle tag, should use default
        ->and($result[2]['Post Title'])->toBe('Third Post')
        ->and($result[2]['Tech Value'])->toBe('0') // Post 3 has no Technology tag, should use default
        ->and($result[2]['Lifestyle Value'])->toBe('30'); // Post 3 has Lifestyle tag with value 30
});
