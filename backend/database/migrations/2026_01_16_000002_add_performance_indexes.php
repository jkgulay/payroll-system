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
     * Add performance improvements:
     * 1. Composite indexes for payroll queries
     * 2. Soft deletes for PayrollItem
     */
    public function up(): void
    {
        // Add composite indexes for better query performance using raw SQL
        // This allows us to use IF NOT EXISTS for PostgreSQL
        
        // Check and create index for payroll_items if it doesn't exist
        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'payroll_items_employee_payroll_index'");
        if (empty($indexExists)) {
            DB::statement('CREATE INDEX payroll_items_employee_payroll_index ON payroll_items (employee_id, payroll_id)');
        }

        // Add soft deletes to payroll_items if not present
        Schema::table('payroll_items', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Check and create index for attendance if it doesn't exist
        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'attendance_employee_date_index'");
        if (empty($indexExists)) {
            DB::statement('CREATE INDEX attendance_employee_date_index ON attendance (employee_id, attendance_date)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes if they exist
        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'payroll_items_employee_payroll_index'");
        if (!empty($indexExists)) {
            DB::statement('DROP INDEX IF EXISTS payroll_items_employee_payroll_index');
        }

        Schema::table('payroll_items', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = 'attendance_employee_date_index'");
        if (!empty($indexExists)) {
            DB::statement('DROP INDEX IF EXISTS attendance_employee_date_index');
        }
    }
};
