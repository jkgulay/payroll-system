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
            // Make last_name nullable to support single-word names
            $table->string('last_name', 100)->nullable()->change();
            
            // Make first_name nullable as well for consistency
            $table->string('first_name', 100)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Revert back to not nullable
            // Note: This may fail if there are NULL values in the database
            $table->string('last_name', 100)->nullable(false)->change();
            $table->string('first_name', 100)->nullable(false)->change();
        });
    }
};
