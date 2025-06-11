<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_filters', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['export_relation_id']);
            
            // Rename the column
            $table->renameColumn('export_relation_id', 'export_model_relation_id');
            
            // Re-add the foreign key constraint with the new column name
            $table->foreign('export_model_relation_id')
                ->references('id')
                ->on('export_model_relations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('export_filters', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['export_model_relation_id']);
            
            // Rename the column back
            $table->renameColumn('export_model_relation_id', 'export_relation_id');
            
            // Re-add the foreign key constraint with the old column name
            $table->foreign('export_relation_id')
                ->references('id')
                ->on('export_model_relations')
                ->onDelete('cascade');
        });
    }
};