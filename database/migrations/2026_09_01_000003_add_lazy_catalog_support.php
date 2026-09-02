<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De-duplicate before adding the unique index; historic double-syncs can
        // have left duplicate (export_model_id, relation, is_column) rows
        $seen = [];
        foreach (DB::table('export_model_relations')->orderBy('created_at')->orderBy('id')->get(['id', 'export_model_id', 'relation', 'is_column']) as $row) {
            $key = $row->export_model_id.'|'.$row->relation.'|'.(int)$row->is_column;
            if (isset($seen[$key])) {
                DB::table('export_model_relations')->where('id', $row->id)->delete();
            } else {
                $seen[$key] = true;
            }
        }

        Schema::table('export_model_relations', function (Blueprint $table) {
            $table->unique(['export_model_id', 'relation', 'is_column'], 'emr_model_relation_unique');
        });

        Schema::table('export_layouts', function (Blueprint $table) {
            if (!Schema::hasColumn('export_layouts', 'model')) {
                $table->string('model')->nullable()->after('export_model_id');
            }
            if (!Schema::hasColumn('export_layouts', 'filter_definitions')) {
                $table->json('filter_definitions')->nullable()->after('column_definitions');
            }
            if (!Schema::hasColumn('export_layouts', 'sort_definitions')) {
                $table->json('sort_definitions')->nullable()->after('filter_definitions');
            }
            $table->uuid('export_model_id')->nullable()->change();
        });

        if (!Schema::hasColumn('export_models', 'schema_hash')) {
            Schema::table('export_models', function (Blueprint $table) {
                $table->string('schema_hash')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('export_model_relations', function (Blueprint $table) {
            $table->dropUnique('emr_model_relation_unique');
        });

        // export_model_id stays nullable: rows created with only a model class
        // would violate a restored NOT NULL constraint
        Schema::table('export_layouts', function (Blueprint $table) {
            $table->dropColumn(['model', 'filter_definitions', 'sort_definitions']);
        });

        Schema::table('export_models', function (Blueprint $table) {
            $table->dropColumn('schema_hash');
        });
    }
};
