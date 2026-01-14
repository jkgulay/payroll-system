<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_rates', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'sss', 'philhealth', 'pagibig', 'tax'
            $table->string('name'); // Display name
            $table->decimal('min_salary', 10, 2)->nullable(); // Minimum salary range
            $table->decimal('max_salary', 10, 2)->nullable(); // Maximum salary range
            $table->decimal('employee_rate', 10, 4)->nullable(); // Employee contribution rate (percentage)
            $table->decimal('employer_rate', 10, 4)->nullable(); // Employer contribution rate (percentage)
            $table->decimal('employee_fixed', 10, 2)->nullable(); // Fixed employee contribution
            $table->decimal('employer_fixed', 10, 2)->nullable(); // Fixed employer contribution
            $table->decimal('total_contribution', 10, 2)->nullable(); // Total contribution for bracket
            $table->date('effective_date'); // When this rate becomes effective
            $table->date('end_date')->nullable(); // When this rate expires
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'effective_date', 'is_active']);
            $table->index(['min_salary', 'max_salary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_rates');
    }
};
