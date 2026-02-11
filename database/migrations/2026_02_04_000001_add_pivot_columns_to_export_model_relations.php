<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('export_model_relations', function (Blueprint $table) {
            $table->boolean('has_pivot')->default(false)->after('is_collection');
            $table->json('pivot_columns')->nullable()->after('has_pivot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('export_model_relations', function (Blueprint $table) {
            $table->dropColumn(['has_pivot', 'pivot_columns']);
        });
    }
};
