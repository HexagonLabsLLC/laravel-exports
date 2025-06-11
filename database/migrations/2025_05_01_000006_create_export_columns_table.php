<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_columns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('export_layout_id');
            $table->uuid('export_function_id')->nullable();
            $table->json('export_function_values')->nullable();
            $table->uuid('export_filter_id')->nullable();
            $table->json('export_filter_values')->nullable();
            $table->uuid('export_model_relation_id')->nullable();
            $table->enum('aggregator', ['sum', 'count', 'avg', 'min', 'max'])->nullable();
            $table->string('title')->nullable();
            $table->string('value_path');
            $table->string('default')->nullable()->default(null);
            $table->integer('position');
            $table->boolean('is_expanded')->default(false);
            $table->json('expansion_data')->nullable()->default(null);
            $table->boolean('omit_on_empty')->default(false);
            $table->timestamps();

            $table->index(['export_layout_id', 'export_function_id', 'export_model_relation_id'], 'export_columns_layout_function_relation_idx');

            $table->foreign('export_layout_id')->references('id')->on('export_layouts')->onDelete('cascade');
            $table->foreign('export_function_id')->references('id')->on('export_functions')->onDelete('cascade');
            $table->foreign('export_filter_id')->references('id')->on('export_filters')->onDelete('cascade');
            $table->foreign('export_model_relation_id')->references('id')->on('export_model_relations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_columns');
    }
};
