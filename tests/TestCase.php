<?php

namespace HexagonLabsLLC\LaravelExports\Tests;

use HexagonLabsLLC\LaravelExports\LaravelExportsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'HexagonLabsLLC\\LaravelExports\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelExportsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        // Package tables - final schema consolidating all migrations
        Schema::create('export_models', function ($table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('model');
            $table->timestamps();

            $table->index('model');
        });

        Schema::create('export_model_relations', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('export_model_id');
            $table->string('title');
            $table->string('relation');
            $table->string('column')->nullable();
            $table->uuid('related_model_id')->nullable();
            $table->boolean('is_column')->default(false);
            $table->boolean('is_collection')->default(false);
            $table->boolean('has_pivot')->default(false);
            $table->json('pivot_columns')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['export_model_id', 'relation', 'related_model_id'], 'emr_model_relation_idx');

            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
            $table->foreign('related_model_id')->references('id')->on('export_models')->onDelete('cascade');
        });

        Schema::create('export_layouts', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('export_model_id');
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_pivot')->default(false);
            $table->json('pivot_config')->nullable();
            $table->timestamps();

            $table->index(['export_model_id', 'name']);

            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
        });

        Schema::create('export_functions', function ($table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('callable');
            $table->integer('parameter_count');
            $table->integer('value_parameter_index')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['callable']);
            $table->index(['name']);
        });

        Schema::create('export_filters', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('export_layout_id');
            $table->uuid('export_model_id')->nullable();
            $table->uuid('export_model_relation_id')->nullable();
            $table->string('logical_operator')->default('and');
            $table->string('operator');
            $table->text('value')->nullable();
            $table->string('value_type')->default('string');
            $table->boolean('is_request')->default(false);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['export_layout_id', 'export_model_id', 'export_model_relation_id'], 'ef_layout_model_relation_idx');

            $table->foreign('export_layout_id')->references('id')->on('export_layouts')->onDelete('cascade');
            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
            $table->foreign('export_model_relation_id')->references('id')->on('export_model_relations')->onDelete('cascade');
        });

        Schema::create('export_columns', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('export_layout_id');
            $table->uuid('export_function_id')->nullable();
            $table->json('export_function_values')->nullable();
            $table->uuid('export_filter_id')->nullable();
            $table->json('export_filter_values')->nullable();
            $table->uuid('export_model_relation_id')->nullable();
            $table->string('aggregator')->nullable();
            $table->string('title')->nullable();
            $table->string('value_path');
            $table->string('default')->nullable()->default(null);
            $table->integer('position');
            $table->boolean('is_expanded')->default(false);
            $table->json('expansion_data')->nullable()->default(null);
            $table->boolean('omit_on_empty')->default(false);
            $table->timestamps();

            $table->index(['export_layout_id', 'export_function_id', 'export_model_relation_id'], 'ec_layout_function_relation_idx');

            $table->foreign('export_layout_id')->references('id')->on('export_layouts')->onDelete('cascade');
            $table->foreign('export_function_id')->references('id')->on('export_functions')->onDelete('cascade');
            $table->foreign('export_filter_id')->references('id')->on('export_filters')->onDelete('cascade');
            $table->foreign('export_model_relation_id')->references('id')->on('export_model_relations')->onDelete('cascade');
        });

        Schema::create('export_sorts', function ($table) {
            $table->uuid('id')->primary();
            $table->uuid('export_layout_id');
            $table->uuid('export_model_id')->nullable();
            $table->uuid('export_model_relation_id')->nullable();
            $table->string('direction')->default('asc');
            $table->integer('priority');
            $table->timestamps();

            $table->index(['export_layout_id', 'export_model_id', 'export_model_relation_id', 'priority'], 'es_layout_model_relation_priority_idx');

            $table->foreign('export_layout_id')->references('id')->on('export_layouts')->onDelete('cascade');
            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
            $table->foreign('export_model_relation_id')->references('id')->on('export_model_relations')->onDelete('cascade');
        });

        // Test-only tables
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('posts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->text('content');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('comments', function ($table) {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->text('content');
            $table->timestamps();
        });

        Schema::create('test_categories', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_tags', function ($table) {
            $table->id();
            $table->foreignId('post_id')->constrained();
            $table->foreignId('category_id')->constrained('test_categories');
            $table->string('value');
            $table->timestamps();
        });
    }
}
