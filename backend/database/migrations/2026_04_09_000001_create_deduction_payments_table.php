<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('deduction_payments')) {
            return;
        }

        Schema::create('deduction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_deduction_id')->constrained('employee_deductions')->cascadeOnDelete();
            $table->foreignId('payroll_id')->nullable()->constrained('payrolls')->nullOnDelete();
            $table->foreignId('payroll_item_id')->nullable()->constrained('payroll_items')->nullOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after_payment', 10, 2);
            $table->unsignedInteger('installment_number');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['employee_deduction_id', 'payment_date']);
            $table->index('payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deduction_payments');
    }
};
