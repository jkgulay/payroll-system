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
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->dropUnique(['email']); // Remove unique constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->unique('email'); // Restore unique constraint
        });
    }
};
