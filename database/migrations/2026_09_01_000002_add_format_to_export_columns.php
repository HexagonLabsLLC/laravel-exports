<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('export_columns', 'format')) {
            Schema::table('export_columns', function (Blueprint $table) {
                $table->string('format')->nullable()->after('default');
            });
        }
    }

    public function down(): void
    {
        Schema::table('export_columns', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
