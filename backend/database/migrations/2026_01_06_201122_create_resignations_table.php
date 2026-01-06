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
        Schema::create('resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('resignation_date'); // Date when resignation was filed
            $table->date('last_working_day'); // Expected last day
            $table->date('effective_date')->nullable(); // Actual last day (can be modified by HR)
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('remarks')->nullable(); // HR remarks
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null'); // HR who processed
            $table->timestamp('processed_at')->nullable();
            
            // 13th month pay calculation fields
            $table->decimal('thirteenth_month_amount', 15, 2)->nullable();
            $table->decimal('final_pay_amount', 15, 2)->nullable(); // Total final pay including unused leaves, etc.
            $table->boolean('final_pay_released')->default(false);
            $table->date('final_pay_release_date')->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('employee_id');
            $table->index('status');
            $table->index('resignation_date');
            $table->index('effective_date');
        });

        // Add resignation_id to employees table for quick reference
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('resignation_id')->nullable()->after('updated_by')->constrained('resignations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['resignation_id']);
            $table->dropColumn('resignation_id');
        });
        
        Schema::dropIfExists('resignations');
    }
};
