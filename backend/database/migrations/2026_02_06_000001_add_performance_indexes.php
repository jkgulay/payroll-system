<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds composite indexes to optimize payroll processing queries
     */
    public function up(): void
    {
        // Helper function to check if index exists (PostgreSQL specific)
        $indexExists = function ($table, $index) {
            $result = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $index]);
            return !empty($result);
        };

        // Attendance table - Critical for payroll period queries
        Schema::table('attendance', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('attendance', 'idx_attendance_employee_date')) {
                $table->index(['employee_id', 'attendance_date'], 'idx_attendance_employee_date');
            }
            if (!$indexExists('attendance', 'idx_attendance_employee_status')) {
                $table->index(['employee_id', 'status'], 'idx_attendance_employee_status');
            }
            if (!$indexExists('attendance', 'idx_attendance_date_status')) {
                $table->index(['attendance_date', 'status'], 'idx_attendance_date_status');
            }
        });

        // Employee Allowances - Active allowances lookup
        Schema::table('employee_allowances', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('employee_allowances', 'idx_allowance_active')) {
                $table->index(['employee_id', 'is_active', 'effective_date'], 'idx_allowance_active');
            }
        });

        // Employee Loans - Active loans with balance
        Schema::table('employee_loans', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('employee_loans', 'idx_loan_active')) {
                $table->index(['employee_id', 'status', 'balance'], 'idx_loan_active');
            }
        });

        // Employee Deductions - Active deductions lookup
        Schema::table('employee_deductions', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('employee_deductions', 'idx_deduction_active')) {
                $table->index(['employee_id', 'status', 'start_date'], 'idx_deduction_active');
            }
        });

        // Salary Adjustments - Pending adjustments
        Schema::table('salary_adjustments', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('salary_adjustments', 'idx_adjustment_pending')) {
                $table->index(['employee_id', 'status'], 'idx_adjustment_pending');
            }
        });

        // Payroll Items - Payroll + employee lookup
        Schema::table('payroll_items', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('payroll_items', 'idx_payroll_employee')) {
                $table->index(['payroll_id', 'employee_id'], 'idx_payroll_employee');
            }
        });

        // Holidays - Optimized holiday lookup
        Schema::table('holidays', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('holidays', 'idx_holiday_lookup')) {
                $table->index(['date', 'is_active', 'type'], 'idx_holiday_lookup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_employee_date');
            $table->dropIndex('idx_attendance_employee_status');
            $table->dropIndex('idx_attendance_date_status');
        });

        Schema::table('employee_allowances', function (Blueprint $table) {
            $table->dropIndex('idx_allowance_active');
        });

        Schema::table('employee_loans', function (Blueprint $table) {
            $table->dropIndex('idx_loan_active');
        });

        Schema::table('employee_deductions', function (Blueprint $table) {
            $table->dropIndex('idx_deduction_active');
        });

        Schema::table('salary_adjustments', function (Blueprint $table) {
            $table->dropIndex('idx_adjustment_pending');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_employee');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->dropIndex('idx_holiday_lookup');
        });
    }
};
