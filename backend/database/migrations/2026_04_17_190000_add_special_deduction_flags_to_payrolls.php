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
            $table->boolean('deduct_cash_advance')
                ->default(true)
                ->after('deduct_employee_deductions');

            $table->boolean('deduct_cash_bond')
                ->default(true)
                ->after('deduct_cash_advance');

            $table->boolean('deduct_employee_savings')
                ->default(true)
                ->after('deduct_cash_bond');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'deduct_cash_advance',
                'deduct_cash_bond',
                'deduct_employee_savings',
            ]);
        });
    }
};
