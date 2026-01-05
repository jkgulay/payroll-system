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
     * Split employment_status into contract_type and activity_status in employee_applications table
     * to maintain consistency with employees table structure
     */
    public function up(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            // Add new columns
            $table->enum('contract_type', ['regular', 'probationary', 'contractual'])
                ->default('probationary')
                ->after('employment_status');
            $table->enum('activity_status', ['active', 'on_leave', 'resigned', 'terminated', 'retired'])
                ->default('active')
                ->after('contract_type');
        });

        // Migrate existing data
        DB::statement("
            UPDATE employee_applications
            SET contract_type = CASE 
                WHEN employment_status IN ('regular', 'probationary', 'contractual') THEN employment_status
                ELSE 'probationary'
            END,
            activity_status = CASE 
                WHEN employment_status = 'active' THEN 'active'
                ELSE 'active'
            END
        ");

        // Drop old column and its index
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->dropColumn('employment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_applications', function (Blueprint $table) {
            // Add back old column
            $table->enum('employment_status', ['regular', 'probationary', 'contractual', 'active'])
                ->default('probationary')
                ->after('salary_type');
        });

        // Migrate data back
        DB::statement("
            UPDATE employee_applications
            SET employment_status = contract_type
        ");

        // Drop new columns
        Schema::table('employee_applications', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'activity_status']);
        });
    }
};
