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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->boolean('deduct_loans')
                ->default(true)
                ->after('deduct_pagibig');

            $table->boolean('deduct_employee_deductions')
                ->default(true)
                ->after('deduct_loans');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'deduct_loans',
                'deduct_employee_deductions',
            ]);
        });
    }
};
