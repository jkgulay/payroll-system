<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add foreign key constraints to loan_payments now that payroll tables exist
     */
    public function up(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            // Add foreign key constraints for payroll tables
            $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('set null');
            $table->foreign('payroll_item_id')->references('id')->on('payroll_items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropForeign(['payroll_id']);
            $table->dropForeign(['payroll_item_id']);
        });
    }
};
