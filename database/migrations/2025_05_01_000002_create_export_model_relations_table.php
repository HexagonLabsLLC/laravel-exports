<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_model_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('export_model_id');
            $table->string('title');
            $table->string('relation');
            $table->uuid('related_model_id')->nullable();
            $table->boolean('is_column')->default(false);
            $table->boolean('is_collection')->default(false);
            $table->timestamps();

            $table->index(['export_model_id', 'relation', 'related_model_id'], 'export_model_relations_model_relation_idx');

            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
            $table->foreign('related_model_id')->references('id')->on('export_models')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_model_relations');
    }
};
