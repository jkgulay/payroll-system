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
            // Check and add foreign key constraints for payroll tables if they don't exist
            $connection = Schema::getConnection();
            
            // Check if payroll_id foreign key exists
            $payrollFkExists = $connection->select("
                SELECT constraint_name 
                FROM information_schema.table_constraints 
                WHERE table_name = 'loan_payments' 
                AND constraint_name = 'loan_payments_payroll_id_foreign'
                AND constraint_type = 'FOREIGN KEY'
            ");
            
            if (empty($payrollFkExists)) {
                $table->foreign('payroll_id')->references('id')->on('payrolls')->onDelete('set null');
            }
            
            // Check if payroll_item_id foreign key exists
            $payrollItemFkExists = $connection->select("
                SELECT constraint_name 
                FROM information_schema.table_constraints 
                WHERE table_name = 'loan_payments' 
                AND constraint_name = 'loan_payments_payroll_item_id_foreign'
                AND constraint_type = 'FOREIGN KEY'
            ");
            
            if (empty($payrollItemFkExists)) {
                $table->foreign('payroll_item_id')->references('id')->on('payroll_items')->onDelete('set null');
            }
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
