<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('export_layouts', 'column_definitions')) {
            Schema::table('export_layouts', function (Blueprint $table) {
                $table->json('column_definitions')->nullable()->after('pivot_config');
            });
        }
    }

    public function down(): void
    {
        Schema::table('export_layouts', function (Blueprint $table) {
            $table->dropColumn('column_definitions');
        });
    }
};
