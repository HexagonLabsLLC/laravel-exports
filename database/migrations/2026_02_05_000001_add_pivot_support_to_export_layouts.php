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
        Schema::table('export_layouts', function (Blueprint $table) {
            $table->boolean('is_pivot')->default(false)->after('description');
            $table->json('pivot_config')->nullable()->after('is_pivot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('export_layouts', function (Blueprint $table) {
            $table->dropColumn(['is_pivot', 'pivot_config']);
        });
    }
};
