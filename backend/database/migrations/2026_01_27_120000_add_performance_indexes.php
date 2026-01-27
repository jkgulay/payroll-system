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
        // Audit Logs indexes for better performance
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('action', 'idx_audit_action');
            $table->index('created_at', 'idx_audit_created_at');
            $table->index(['user_id', 'module'], 'idx_audit_user_module');
        });

        // Payroll Items indexes for better query performance
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->index(['payroll_id', 'employee_id'], 'idx_payroll_employee');
        });

        // Attendance indexes for better date range queries
        Schema::table('attendance', function (Blueprint $table) {
            $table->index(['employee_id', 'attendance_date'], 'idx_attendance_employee_date');
            $table->index(['attendance_date', 'status'], 'idx_attendance_date_status');
        });

        // Additional composite indexes for common queries
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['project_id', 'activity_status'], 'idx_employees_project_status');
            $table->index(['position_id', 'activity_status'], 'idx_employees_position_status');
        });

        // Payrolls indexes for status and date queries
        Schema::table('payrolls', function (Blueprint $table) {
            $table->index(['status', 'period_start', 'period_end'], 'idx_payrolls_status_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_action');
            $table->dropIndex('idx_audit_created_at');
            $table->dropIndex('idx_audit_user_module');
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_employee');
        });

        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_employee_date');
            $table->dropIndex('idx_attendance_date_status');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_employees_project_status');
            $table->dropIndex('idx_employees_position_status');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropIndex('idx_payrolls_status_date');
        });
    }
};
