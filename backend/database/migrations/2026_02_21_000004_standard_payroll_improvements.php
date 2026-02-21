<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Safe payroll system improvements — no existing functionality is removed or altered.
     *
     * 1. Drop payroll_items.cola
     *    Hardcoded to 0 in PayrollService since COLA was removed from the allowance engine.
     *    Already removed from $fillable. Safe to drop the DB column now.
     *
     * 2. Add employee_government_info.updated_by
     *    EmployeeDocumentController already passes this value on every update but it was
     *    silently dropped because the column didn't exist. Adds proper audit trail.
     *
     * 3. Add payrolls.approved_by + payrolls.approved_at
     *    Standard payroll workflow: draft → approved → finalized → paid.
     *    Currently the system only tracks finalization. Adding approval step audit trail.
     *
     * 4. Add employees.payment_mode
     *    Standard field for how the employee receives their salary.
     *    Defaults to 'cash' for backward compatibility with existing employees.
     *
     * 5. Add UNIQUE constraint on payroll_items(payroll_id, employee_id)
     *    Prevents accidental duplicate payroll entries for the same employee.
     *    Verified: zero existing duplicates in the database.
     */
    public function up(): void
    {
        // 1. Drop the dead cola column
        if (Schema::hasColumn('payroll_items', 'cola')) {
            Schema::table('payroll_items', function (Blueprint $table) {
                $table->dropColumn('cola');
            });
        }

        // 2. Add updated_by to employee_government_info
        if (!Schema::hasColumn('employee_government_info', 'updated_by')) {
            Schema::table('employee_government_info', function (Blueprint $table) {
                $table->foreignId('updated_by')
                    ->nullable()
                    ->after('is_minimum_wage_earner')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        // 3. Add approval tracking to payrolls
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'approved_by')) {
                $table->foreignId('approved_by')
                    ->nullable()
                    ->after('finalized_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('payrolls', 'approved_at')) {
                $table->timestamp('approved_at')
                    ->nullable()
                    ->after('approved_by');
            }
        });

        // 4. Add payment_mode to employees
        if (!Schema::hasColumn('employees', 'payment_mode')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->enum('payment_mode', ['cash', 'bank_transfer', 'check'])
                    ->default('cash')
                    ->after('bank_account_number')
                    ->comment('How the employee receives their salary');
            });
        }

        // 5. Add unique constraint to prevent duplicate payroll items
        Schema::table('payroll_items', function (Blueprint $table) {
            // Check if unique index already exists before adding
            $existingIndexes = collect(
                \Illuminate\Support\Facades\DB::select(
                    "SELECT indexname FROM pg_indexes WHERE tablename = 'payroll_items' AND indexname = 'payroll_items_payroll_employee_unique'"
                )
            );
            if ($existingIndexes->isEmpty()) {
                $table->unique(['payroll_id', 'employee_id'], 'payroll_items_payroll_employee_unique');
            }
        });
    }

    public function down(): void
    {
        // Reverse in opposite order

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropUnique('payroll_items_payroll_employee_unique');
            $table->decimal('cola', 12, 2)->default(0)->after('special_ot_pay');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('payment_mode');
        });

        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });

        Schema::table('employee_government_info', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });
    }
};
