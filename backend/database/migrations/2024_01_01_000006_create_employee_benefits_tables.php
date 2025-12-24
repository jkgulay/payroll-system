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
        // Employee Allowances
        Schema::create('employee_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('allowance_type'); // water, cola, transportation, meal, etc.
            $table->string('allowance_name');
            $table->decimal('amount', 10, 2);
            $table->string('frequency')->default('semi-monthly'); // daily, weekly, semi-monthly, monthly
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'is_active']);
            $table->index('allowance_type');
            $table->index(['effective_date', 'end_date']);
        });

        // Employee Bonuses
        Schema::create('employee_bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('bonus_type'); // performance, project_completion, referral, etc.
            $table->string('bonus_name');
            $table->decimal('amount', 10, 2);
            $table->date('grant_date');
            $table->date('payment_date')->nullable();
            $table->enum('payment_status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->boolean('is_taxable')->default(true);
            $table->text('reason')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'payment_status']);
            $table->index('bonus_type');
            $table->index('payment_date');
        });

        // Employee Deductions
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('deduction_type'); // ppe, tools, uniform, damage, etc.
            $table->string('deduction_name');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('amount_per_cutoff', 10, 2);
            $table->integer('installments')->default(1);
            $table->integer('installments_paid')->default(0);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('description')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index('deduction_type');
            $table->index(['start_date', 'end_date']);
        });

        // Employee Loans
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_number')->unique();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('loan_type'); // sss, pagibig, company, emergency
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->decimal('total_amount', 10, 2); // principal + interest
            $table->decimal('monthly_amortization', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->integer('loan_term_months');
            $table->date('loan_date');
            $table->date('first_payment_date');
            $table->date('maturity_date');
            $table->enum('status', ['pending', 'approved', 'active', 'paid', 'cancelled'])->default('pending');
            $table->text('purpose')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index('loan_type');
            $table->index('loan_number');
        });

        // Loan Payments
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_loan_id')->constrained('employee_loans')->onDelete('cascade');
            $table->foreignId('payroll_id')->nullable()->constrained('payroll')->onDelete('set null');
            $table->foreignId('payroll_item_id')->nullable()->constrained('payroll_items')->onDelete('set null');
            $table->date('payment_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('principal_payment', 10, 2);
            $table->decimal('interest_payment', 10, 2);
            $table->decimal('balance_after_payment', 10, 2);
            $table->integer('payment_number');
            $table->enum('payment_method', ['payroll_deduction', 'cash', 'check', 'bank_transfer'])->default('payroll_deduction');
            $table->string('reference_number')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_loan_id', 'payment_date']);
            $table->index('payroll_id');
        });

        // 13th Month Pay
        Schema::create('thirteenth_month_pay', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->integer('year');
            $table->decimal('total_basic_salary', 10, 2); // Sum of basic salary for the year
            $table->decimal('thirteenth_month_amount', 10, 2); // total_basic_salary / 12
            $table->decimal('taxable_amount', 10, 2)->default(0); // Amount over ₱90,000
            $table->decimal('tax_withheld', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->date('payment_date')->nullable();
            $table->enum('status', ['computed', 'approved', 'paid'])->default('computed');
            $table->foreignId('computed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('computed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'year']);
            $table->index(['year', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
        Schema::dropIfExists('employee_loans');
        Schema::dropIfExists('employee_deductions');
        Schema::dropIfExists('employee_bonuses');
        Schema::dropIfExists('employee_allowances');
        Schema::dropIfExists('thirteenth_month_pay');
    }
};
