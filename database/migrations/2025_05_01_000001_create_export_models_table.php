<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('model');
            $table->timestamps();

            $table->index('model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_models');
    }
};
