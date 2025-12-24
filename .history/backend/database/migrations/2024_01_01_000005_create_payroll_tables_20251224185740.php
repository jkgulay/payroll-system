<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number', 50)->unique();

            // Period Information
            $table->enum('period_type', ['semi_monthly', 'monthly'])->default('semi_monthly');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');

            // Period Identifier
            $table->integer('pay_period_number'); // 1 or 2
            $table->integer('month');
            $table->integer('year');

            // Processing Status
            $table->enum('status', ['draft', 'processing', 'checked', 'recommended', 'approved', 'paid', 'cancelled'])->default('draft');

            // Totals
            $table->decimal('total_gross_pay', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net_pay', 15, 2)->default(0);

            // Workflow - Construction Company Specific
            $table->foreignId('prepared_by')->nullable()->constrained('users');
            $table->timestamp('prepared_at')->nullable();

            $table->foreignId('checked_by')->nullable()->constrained('users');
            $table->timestamp('checked_at')->nullable();

            $table->foreignId('recommended_by')->nullable()->constrained('users');
            $table->timestamp('recommended_at')->nullable();

            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('paid_by')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();

            // Notes
            $table->text('notes')->nullable();

            // System Fields
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'month', 'pay_period_number']);
            $table->index('payroll_number');
            $table->index('status');
            $table->index('payment_date');
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');

            // Basic Pay
            $table->decimal('basic_rate', 12, 2);
            $table->decimal('days_worked', 5, 2)->default(0);
            $table->decimal('basic_pay', 12, 2)->default(0);

            // Additional Pay
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('night_differential', 12, 2)->default(0);

            // Adjustments
            $table->decimal('adjustments', 12, 2)->default(0);
            $table->text('adjustment_notes')->nullable();

            // Allowances
            $table->decimal('water_allowance', 12, 2)->default(0);
            $table->decimal('cola', 12, 2)->default(0);
            $table->decimal('other_allowances', 12, 2)->default(0);
            $table->decimal('total_allowances', 12, 2)->default(0);

            // Bonuses and Incentives
            $table->decimal('total_bonuses', 12, 2)->default(0);

            // Gross Pay
            $table->decimal('gross_pay', 12, 2);

            // Government Deductions
            $table->decimal('sss_contribution', 12, 2)->default(0);
            $table->decimal('philhealth_contribution', 12, 2)->default(0);
            $table->decimal('pagibig_contribution', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);

            // Other Deductions
            $table->decimal('total_other_deductions', 12, 2)->default(0);

            // Loan Deductions
            $table->decimal('total_loan_deductions', 12, 2)->default(0);

            // Total Deductions
            $table->decimal('total_deductions', 12, 2);

            // Net Pay
            $table->decimal('net_pay', 12, 2);

            // Payslip
            $table->boolean('payslip_generated')->default(false);
            $table->string('payslip_file_path')->nullable();

            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
            $table->index('payroll_id');
            $table->index('employee_id');
        });

        Schema::create('payroll_item_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained()->onDelete('cascade');

            $table->enum('type', ['earning', 'deduction']);
            $table->string('category', 50);
            $table->string('description');
            $table->decimal('amount', 12, 2);

            // Reference to source record
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type', 50)->nullable();

            $table->timestamps();

            $table->index('payroll_item_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_details');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll');
    }
};
