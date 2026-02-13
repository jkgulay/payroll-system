<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix the loan_payments.payroll_id foreign key to reference 'payrolls' table correctly
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop the existing incorrect FK constraint(s)
            DB::statement('ALTER TABLE loan_payments DROP CONSTRAINT IF EXISTS loan_payments_payroll_id_foreign');
            
            // Also drop any FK that might reference 'payroll' (singular)
            // First, find all FK constraints on loan_payments.payroll_id
            $constraints = DB::select("
                SELECT con.conname as constraint_name
                FROM pg_constraint con
                JOIN pg_class rel ON rel.oid = con.conrelid
                JOIN pg_attribute att ON att.attrelid = con.conrelid AND att.attnum = ANY(con.conkey)
                WHERE rel.relname = 'loan_payments'
                AND att.attname = 'payroll_id'
                AND con.contype = 'f'
            ");

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE loan_payments DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }
            
            // Drop FK for payroll_item_id too (might also reference wrong table)
            DB::statement('ALTER TABLE loan_payments DROP CONSTRAINT IF EXISTS loan_payments_payroll_item_id_foreign');
            
            $itemConstraints = DB::select("
                SELECT con.conname as constraint_name
                FROM pg_constraint con
                JOIN pg_class rel ON rel.oid = con.conrelid
                JOIN pg_attribute att ON att.attrelid = con.conrelid AND att.attnum = ANY(con.conkey)
                WHERE rel.relname = 'loan_payments'
                AND att.attname = 'payroll_item_id'
                AND con.contype = 'f'
            ");

            foreach ($itemConstraints as $constraint) {
                DB::statement("ALTER TABLE loan_payments DROP CONSTRAINT IF EXISTS \"{$constraint->constraint_name}\"");
            }

            // Re-create FK constraints with correct table references
            // Allow NULL for payroll_id and payroll_item_id since manual payments may not have payroll reference
            Schema::table('loan_payments', function (Blueprint $table) {
                $table->foreign('payroll_id')
                    ->references('id')
                    ->on('payrolls')
                    ->onDelete('set null');
                    
                $table->foreign('payroll_item_id')
                    ->references('id')
                    ->on('payroll_items')
                    ->onDelete('set null');
            });
        } else {
            // For MySQL/SQLite
            Schema::table('loan_payments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['payroll_id']);
                } catch (\Throwable $e) {
                    // Ignore if doesn't exist
                }
                try {
                    $table->dropForeign(['payroll_item_id']);
                } catch (\Throwable $e) {
                    // Ignore if doesn't exist
                }
            });

            Schema::table('loan_payments', function (Blueprint $table) {
                $table->foreign('payroll_id')
                    ->references('id')
                    ->on('payrolls')
                    ->onDelete('set null');
                    
                $table->foreign('payroll_item_id')
                    ->references('id')
                    ->on('payroll_items')
                    ->onDelete('set null');
            });
        }
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
