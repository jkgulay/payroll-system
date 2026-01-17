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
        Schema::table('employees', function (Blueprint $table) {
            // Drop the old foreign key constraint
            $table->dropForeign(['project_id']);
            
            // Modify project_id to be nullable
            $table->foreignId('project_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop the nullable foreign key
            $table->dropForeign(['project_id']);
            
            // Make project_id non-nullable again
            $table->foreignId('project_id')->nullable(false)->change();
            
            // Re-add the non-nullable foreign key constraint
            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->onDelete('restrict');
        });
    }
};
