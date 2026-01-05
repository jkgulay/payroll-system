<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        // Add composite indexes for better query performance
        Schema::table('payroll_items', function (Blueprint $table) {
            // Composite index for employee payroll queries
            $table->index(['employee_id', 'payroll_id'], 'payroll_items_employee_payroll_index');
            
            // Add soft deletes for consistency with Payroll table
            if (!Schema::hasColumn('payroll_items', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('attendance', function (Blueprint $table) {
            // Composite index for attendance date range queries
            $table->index(['employee_id', 'attendance_date'], 'attendance_employee_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropIndex('payroll_items_employee_payroll_index');
            if (Schema::hasColumn('payroll_items', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('attendance_employee_date_index');
        });
    }
};
