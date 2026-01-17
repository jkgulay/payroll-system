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
        Schema::table('employee_deductions', function (Blueprint $table) {
            // Add balance column if it doesn't exist
            if (!Schema::hasColumn('employee_deductions', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0)->after('installments_paid');
            }
            
            // Add notes column if it doesn't exist
            if (!Schema::hasColumn('employee_deductions', 'notes')) {
                $table->text('notes')->nullable()->after('reference_number');
            }
            
            // Add created_by column if it doesn't exist
            if (!Schema::hasColumn('employee_deductions', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_deductions', function (Blueprint $table) {
            if (Schema::hasColumn('employee_deductions', 'balance')) {
                $table->dropColumn('balance');
            }
            
            if (Schema::hasColumn('employee_deductions', 'notes')) {
                $table->dropColumn('notes');
            }
            
            if (Schema::hasColumn('employee_deductions', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
        });
    }
};
