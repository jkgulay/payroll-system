<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration fixes the employment_status ENUM confusion by:
     * 1. Renaming current employment_status to contract_type
     * 2. Creating new employment_status for activity status
     * 3. Migrating data appropriately
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Step 1: Add new columns
            $table->enum('contract_type', ['regular', 'probationary', 'contractual'])->default('regular')->after('employment_type');
            $table->enum('activity_status', ['active', 'on_leave', 'resigned', 'terminated', 'retired'])->default('active')->after('contract_type');
        });

        // Step 2: Migrate data from old employment_status
        // If it's a contract type, put in contract_type; if activity type, put in activity_status
        DB::statement("
            UPDATE employees 
            SET contract_type = CASE 
                WHEN employment_status IN ('regular', 'probationary', 'contractual') THEN employment_status
                ELSE 'regular'
            END,
            activity_status = CASE 
                WHEN employment_status IN ('active', 'resigned', 'terminated', 'retired') THEN employment_status
                WHEN employment_status IN ('regular', 'probationary', 'contractual') THEN 'active'
                ELSE 'active'
            END
        ");

        // Step 3: Drop the old confusing employment_status column
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['employment_status']); // Drop the index first
            $table->dropColumn('employment_status');
        });

        // Step 4: Add indexes for the new columns
        Schema::table('employees', function (Blueprint $table) {
            $table->index('contract_type');
            $table->index('activity_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Add back the old column
            $table->enum('employment_status', ['regular', 'probationary', 'contractual', 'active', 'resigned', 'terminated', 'retired'])->default('regular')->after('employment_type');
        });

        // Migrate data back (prioritize contract_type)
        DB::statement("
            UPDATE employees 
            SET employment_status = CASE 
                WHEN activity_status IN ('resigned', 'terminated', 'retired') THEN activity_status
                ELSE contract_type
            END
        ");

        // Drop new columns
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['contract_type']);
            $table->dropIndex(['activity_status']);
            $table->dropColumn(['contract_type', 'activity_status']);
            $table->index('employment_status');
        });
    }
};
