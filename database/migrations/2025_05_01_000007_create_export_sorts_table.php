<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_sorts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('export_layout_id');
            $table->uuid('export_model_id')->nullable();
            $table->uuid('export_model_relation_id')->nullable();
            $table->enum('direction', ['asc', 'desc']);
            $table->integer('priority');
            $table->timestamps();

            $table->index(['export_layout_id', 'export_model_id', 'export_model_relation_id', 'priority'], 'export_sorts_layout_model_relation_priority_idx');

            $table->foreign('export_layout_id')->references('id')->on('export_layouts')->onDelete('cascade');
            $table->foreign('export_model_id')->references('id')->on('export_models')->onDelete('cascade');
            $table->foreign('export_model_relation_id')->references('id')->on('export_model_relations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_sorts');
    }
};
