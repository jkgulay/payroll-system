<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_allowance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_allowance_id')->constrained('meal_allowances')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('employee_name');
            $table->string('position_code')->nullable(); // C&M, Mq, etc.
            $table->integer('no_of_days');
            $table->decimal('amount_per_day', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->integer('sort_order')->default(0); // For grouping by position
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_allowance_items');
    }
};
