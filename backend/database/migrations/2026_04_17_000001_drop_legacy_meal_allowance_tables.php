<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            if (Schema::hasTable('meal_allowance_items')) {
                Schema::drop('meal_allowance_items');
            }

            if (Schema::hasTable('meal_allowances')) {
                Schema::drop('meal_allowances');
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('meal_allowances')) {
            Schema::create('meal_allowances', function (Blueprint $table) {
                $table->id();
                $table->string('reference_number')->unique();
                $table->string('title');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('location')->nullable();
                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
                $table->foreignId('position_id')->nullable()->constrained('position_rates')->nullOnDelete();
                $table->string('status')->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_notes')->nullable();
                $table->string('prepared_by_name')->nullable();
                $table->string('checked_by_name')->nullable();
                $table->string('verified_by_name')->nullable();
                $table->string('recommended_by_name')->nullable();
                $table->string('approved_by_name')->nullable();
                $table->string('pdf_path')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('meal_allowance_items')) {
            Schema::create('meal_allowance_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meal_allowance_id')->constrained('meal_allowances')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('employee_name');
                $table->string('position_code')->nullable();
                $table->integer('no_of_days');
                $table->decimal('amount_per_day', 10, 2);
                $table->decimal('total_amount', 10, 2);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }
};
