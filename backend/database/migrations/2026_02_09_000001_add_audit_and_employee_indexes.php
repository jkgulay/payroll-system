<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing indexes for frequently queried columns.
     */
    public function up(): void
    {
        // Audit logs - queried by module, action, user_id, and created_at
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['module', 'created_at'], 'idx_audit_logs_module_created');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_created');
        });

        // Employees - activity_status is heavily filtered in payroll and attendance queries
        Schema::table('employees', function (Blueprint $table) {
            $table->index('activity_status', 'idx_employees_activity_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_module_created');
            $table->dropIndex('idx_audit_logs_user_created');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex('idx_employees_activity_status');
        });
    }
};
