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
        if (!Schema::hasTable('salary_adjustments')) {
            Schema::create('salary_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained()->onDelete('cascade');
                $table->decimal('amount', 12, 2);
                $table->enum('type', ['deduction', 'addition'])->default('deduction');
                $table->string('reason')->nullable();
                $table->string('reference_period')->nullable(); // e.g., "January 2026 - Cutoff 1"
                $table->date('effective_date')->nullable();
                $table->enum('status', ['pending', 'applied', 'cancelled'])->default('pending');
                $table->foreignId('applied_payroll_id')->nullable()->constrained('payrolls')->onDelete('set null');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->text('notes')->nullable();
                $table->timestamps();
                
                $table->index(['employee_id', 'status']);
                $table->index(['effective_date', 'status']);
            });
        }
        
        // Add salary_adjustment column to payroll_items table if it doesn't exist
        if (!Schema::hasColumn('payroll_items', 'salary_adjustment')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->decimal('salary_adjustment', 12, 2)->default(0)->after('other_allowances');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn('salary_adjustment');
        });
        
        Schema::dropIfExists('salary_adjustments');
    }
};
