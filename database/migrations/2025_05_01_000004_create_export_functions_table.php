<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_functions', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('export_functions');
    }
};
