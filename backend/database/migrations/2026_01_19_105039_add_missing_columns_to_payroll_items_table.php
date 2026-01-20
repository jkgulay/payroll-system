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
        Schema::table('payroll_items', function (Blueprint $table) {
            // Add missing columns with checks to prevent duplicates
            if (!Schema::hasColumn('payroll_items', 'days_worked')) {
                $table->decimal('days_worked', 5, 2)->default(0)->after('rate');
            }
            if (!Schema::hasColumn('payroll_items', 'basic_pay')) {
                $table->decimal('basic_pay', 12, 2)->default(0)->after('days_worked');
            }
            
            // Overtime columns
            if (!Schema::hasColumn('payroll_items', 'regular_ot_hours')) {
                $table->decimal('regular_ot_hours', 5, 2)->default(0)->after('basic_pay');
            }
            if (!Schema::hasColumn('payroll_items', 'regular_ot_pay')) {
                $table->decimal('regular_ot_pay', 12, 2)->default(0)->after('regular_ot_hours');
            }
            if (!Schema::hasColumn('payroll_items', 'special_ot_hours')) {
                $table->decimal('special_ot_hours', 5, 2)->default(0)->after('regular_ot_pay');
            }
            if (!Schema::hasColumn('payroll_items', 'special_ot_pay')) {
                $table->decimal('special_ot_pay', 12, 2)->default(0)->after('special_ot_hours');
            }
            
            // Allowances & Earnings
            if (!Schema::hasColumn('payroll_items', 'cola')) {
                $table->decimal('cola', 12, 2)->default(0)->after('special_ot_pay');
            }
            if (!Schema::hasColumn('payroll_items', 'other_allowances')) {
                $table->decimal('other_allowances', 12, 2)->default(0)->after('cola');
            }
            if (!Schema::hasColumn('payroll_items', 'gross_pay')) {
                $table->decimal('gross_pay', 12, 2)->default(0)->after('allowances_breakdown');
            }
            
            // Government Deductions
            if (!Schema::hasColumn('payroll_items', 'sss')) {
                $table->decimal('sss', 12, 2)->default(0)->after('gross_pay');
            }
            if (!Schema::hasColumn('payroll_items', 'philhealth')) {
                $table->decimal('philhealth', 12, 2)->default(0)->after('sss');
            }
            if (!Schema::hasColumn('payroll_items', 'pagibig')) {
                $table->decimal('pagibig', 12, 2)->default(0)->after('philhealth');
            }
            if (!Schema::hasColumn('payroll_items', 'withholding_tax')) {
                $table->decimal('withholding_tax', 12, 2)->default(0)->after('pagibig');
            }
            
            // Other Deductions
            if (!Schema::hasColumn('payroll_items', 'employee_savings')) {
                $table->decimal('employee_savings', 12, 2)->default(0)->after('withholding_tax');
            }
            if (!Schema::hasColumn('payroll_items', 'cash_advance')) {
                $table->decimal('cash_advance', 12, 2)->default(0)->after('employee_savings');
            }
            if (!Schema::hasColumn('payroll_items', 'loans')) {
                $table->decimal('loans', 12, 2)->default(0)->after('cash_advance');
            }
            if (!Schema::hasColumn('payroll_items', 'other_deductions')) {
                $table->decimal('other_deductions', 12, 2)->default(0)->after('employee_deductions');
            }
            if (!Schema::hasColumn('payroll_items', 'total_deductions')) {
                $table->decimal('total_deductions', 12, 2)->default(0)->after('other_deductions');
            }
            
            // Net Pay
            if (!Schema::hasColumn('payroll_items', 'net_pay')) {
                $table->decimal('net_pay', 12, 2)->default(0)->after('total_deductions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $columns = [
                'days_worked', 'basic_pay', 'regular_ot_hours', 'regular_ot_pay',
                'special_ot_hours', 'special_ot_pay', 'cola', 'other_allowances',
                'gross_pay', 'sss', 'philhealth', 'pagibig', 'withholding_tax',
                'employee_savings', 'cash_advance', 'loans', 'other_deductions',
                'total_deductions', 'net_pay'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('payroll_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
