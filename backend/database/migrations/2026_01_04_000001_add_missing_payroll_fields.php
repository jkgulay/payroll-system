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
        // Add missing fields to employee_deductions table
        if (!Schema::hasColumn('employee_deductions', 'paid_installments')) {
            Schema::table('employee_deductions', function (Blueprint $table) {
                $table->integer('paid_installments')->default(0)->after('total_installments');
            });
        }

        // Add missing fields to employee_allowances table if they don't exist
        if (!Schema::hasColumn('employee_allowances', 'frequency')) {
            Schema::table('employee_allowances', function (Blueprint $table) {
                $table->enum('frequency', ['daily', 'weekly', 'semi_monthly', 'monthly'])->default('semi_monthly')->after('amount');
            });
        }

        if (!Schema::hasColumn('employee_allowances', 'effective_date')) {
            Schema::table('employee_allowances', function (Blueprint $table) {
                $table->date('effective_date')->nullable()->after('is_active');
            });
        }

        if (!Schema::hasColumn('employee_allowances', 'created_by')) {
            Schema::table('employee_allowances', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->after('effective_date');
            });
        }

        // Add missing fields to employee_deductions table if they don't exist
        if (!Schema::hasColumn('employee_deductions', 'effective_date')) {
            Schema::table('employee_deductions', function (Blueprint $table) {
                $table->date('effective_date')->nullable()->after('status');
            });
        }

        if (!Schema::hasColumn('employee_deductions', 'created_by')) {
            Schema::table('employee_deductions', function (Blueprint $table) {
                $table->foreignId('created_by')->nullable()->constrained('users')->after('effective_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employee_deductions', 'paid_installments')) {
            Schema::table('employee_deductions', function (Blueprint $table) {
                $table->dropColumn('paid_installments');
            });
        }

        if (Schema::hasColumn('employee_allowances', 'frequency')) {
            Schema::table('employee_allowances', function (Blueprint $table) {
                $table->dropColumn(['frequency', 'effective_date', 'created_by']);
            });
        }

        if (Schema::hasColumn('employee_deductions', 'effective_date')) {
            Schema::table('employee_deductions', function (Blueprint $table) {
                $table->dropColumn(['effective_date', 'created_by']);
            });
        }
    }
};
