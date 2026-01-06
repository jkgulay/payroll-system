<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename employment_type to work_schedule and update values
     * 
     * OLD (employment_type): regular, contractual, part_time
     * NEW (work_schedule): full_time, part_time
     * 
     * Mapping:
     * - 'regular' -> 'full_time'
     * - 'contractual' -> 'full_time' (contractual is contract_type, not schedule)
     * - 'part_time' -> 'part_time'
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Add new work_schedule column
            $table->enum('work_schedule', ['full_time', 'part_time'])
                ->default('full_time')
                ->after('position');
        });

        // Migrate data: map old values to new values
        DB::statement("
            UPDATE employees 
            SET work_schedule = CASE 
                WHEN employment_type = 'part_time' THEN 'part_time'
                ELSE 'full_time'
            END
        ");

        Schema::table('employees', function (Blueprint $table) {
            // Drop old column and its index
            $table->dropIndex(['employment_type']);
            $table->dropColumn('employment_type');

            // Add index for new column
            $table->index('work_schedule');
        });
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Add back employment_type column
            $table->enum('employment_type', ['regular', 'contractual', 'part_time'])
                ->default('regular')
                ->after('position');
        });

        // Migrate data back
        DB::statement("
            UPDATE employees 
            SET employment_type = CASE 
                WHEN work_schedule = 'part_time' THEN 'part_time'
                ELSE 'regular'
            END
        ");

        Schema::table('employees', function (Blueprint $table) {
            // Drop new column and its index
            $table->dropIndex(['work_schedule']);
            $table->dropColumn('work_schedule');

            // Add back old index
            $table->index('employment_type');
        });
    }
};
