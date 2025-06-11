<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_filters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('export_layout_id');
            $table->uuid('export_model_id')->nullable();
            $table->uuid('export_model_relation_id')->nullable();
            $table->enum('logical_operator', ['and', 'or'])->default('and');
            $table->enum('operator', ['=', '!=', '>', '<', '>=', '<=', 'in', 'not_in', 'between', 'like', 'null', 'not_null', 'json_contains', 'relation']);
            $table->text('value')->nullable();
            $table->enum('value_type', ['array', 'string', 'integer', 'boolean', 'float'])->default('string');
            $table->boolean('is_request')->default(false);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->index(['export_layout_id', 'export_model_id', 'export_model_relation_id'], 'export_filters_layout_model_relation_idx');

            $table->foreign('export_layout_id')->references('id')->on('export_layouts')->onDelete('cascade');
            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
            $table->foreign('export_model_relation_id')->references('id')->on('export_model_relations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_filters');
    }
};
