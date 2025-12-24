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
        // SSS Contribution Table
        Schema::create('sss_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('range_compensation_from', 10, 2);
            $table->decimal('range_compensation_to', 10, 2)->nullable();
            $table->decimal('monthly_salary_credit', 10, 2);
            $table->decimal('employer_contribution', 10, 2);
            $table->decimal('employee_contribution', 10, 2);
            $table->decimal('total_contribution', 10, 2);
            $table->decimal('employer_ec', 10, 2)->default(10.00); // Employee Compensation (fixed ₱10)
            $table->decimal('employer_wisp', 10, 2)->default(0); // Workers' Investment and Savings Program
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['range_compensation_from', 'range_compensation_to', 'is_active']);
            $table->index(['effective_date', 'end_date']);
        });

        // PhilHealth Contribution Table
        Schema::create('philhealth_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_basic_salary_from', 10, 2);
            $table->decimal('monthly_basic_salary_to', 10, 2)->nullable();
            $table->decimal('premium_rate', 5, 2); // Currently 4% (2% employer, 2% employee)
            $table->decimal('minimum_monthly_premium', 10, 2)->nullable();
            $table->decimal('maximum_monthly_premium', 10, 2)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['monthly_basic_salary_from', 'monthly_basic_salary_to', 'is_active']);
            $table->index(['effective_date', 'end_date']);
        });

        // Pag-IBIG Contribution Table
        Schema::create('pagibig_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_compensation_from', 10, 2);
            $table->decimal('monthly_compensation_to', 10, 2)->nullable();
            $table->decimal('employee_rate', 5, 2); // 1% for ≤₱1,500, 2% for >₱1,500
            $table->decimal('employer_rate', 5, 2); // 2%
            $table->decimal('employee_min_contribution', 10, 2)->nullable();
            $table->decimal('employee_max_contribution', 10, 2)->nullable(); // ₱200 cap
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['monthly_compensation_from', 'monthly_compensation_to', 'is_active']);
            $table->index(['effective_date', 'end_date']);
        });

        // Tax Withholding Table (TRAIN Law)
        Schema::create('tax_withholding_table', function (Blueprint $table) {
            $table->id();
            $table->enum('period_type', ['annual', 'semi-monthly', 'monthly', 'weekly', 'daily']);
            $table->decimal('taxable_income_from', 15, 2);
            $table->decimal('taxable_income_to', 15, 2)->nullable();
            $table->decimal('base_tax', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2); // Percentage (0%, 15%, 20%, 25%, 30%, 35%)
            $table->decimal('excess_over', 15, 2)->default(0);
            $table->integer('tax_bracket'); // 1, 2, 3, 4, 5, 6
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['period_type', 'taxable_income_from', 'taxable_income_to', 'is_active']);
            $table->index(['effective_date', 'end_date']);
        });

        // Employee Government IDs and Registration
        Schema::create('employee_government_info', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('sss_number', 20)->nullable();
            $table->date('sss_registration_date')->nullable();
            $table->string('philhealth_number', 20)->nullable();
            $table->date('philhealth_registration_date')->nullable();
            $table->string('pagibig_number', 20)->nullable();
            $table->date('pagibig_registration_date')->nullable();
            $table->string('tin_number', 20)->nullable();
            $table->string('rdo_code', 10)->nullable(); // Revenue District Office
            $table->date('tin_registration_date')->nullable();
            $table->enum('tax_status', ['S', 'ME', 'S1', 'ME1', 'S2', 'ME2', 'S3', 'ME3', 'S4', 'ME4'])->default('S'); // Single, Married Employee
            $table->boolean('is_minimum_wage_earner')->default(false);
            $table->boolean('is_govt_contribution_exempt')->default(false);
            $table->timestamps();

            $table->unique('employee_id');
            $table->index('sss_number');
            $table->index('philhealth_number');
            $table->index('pagibig_number');
            $table->index('tin_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_government_info');
        Schema::dropIfExists('tax_withholding_table');
        Schema::dropIfExists('pagibig_contribution_table');
        Schema::dropIfExists('philhealth_contribution_table');
        Schema::dropIfExists('sss_contribution_table');
    }
};
