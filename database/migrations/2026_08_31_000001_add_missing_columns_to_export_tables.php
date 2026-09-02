<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('export_layouts', 'title')) {
            Schema::table('export_layouts', function (Blueprint $table) {
                $table->string('title')->nullable()->after('name');
            });
        }

        Schema::table('export_model_relations', function (Blueprint $table) {
            if (!Schema::hasColumn('export_model_relations', 'column')) {
                $table->string('column')->nullable()->after('relation');
            }
            if (!Schema::hasColumn('export_model_relations', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('export_layouts', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('export_model_relations', function (Blueprint $table) {
            $table->dropColumn(['column', 'metadata']);
        });
    }
};
