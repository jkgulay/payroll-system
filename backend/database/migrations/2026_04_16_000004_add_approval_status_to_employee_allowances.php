<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_allowances', 'status')) {
                $table->string('status')->default('approved')->after('is_active');
                $table->index('status', 'idx_employee_allowances_status');
            }

            if (!Schema::hasColumn('employee_allowances', 'rejected_by')) {
                $table->foreignId('rejected_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('approved_at');
            }

            if (!Schema::hasColumn('employee_allowances', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }

            if (!Schema::hasColumn('employee_allowances', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_allowances', function (Blueprint $table) {
            if (Schema::hasColumn('employee_allowances', 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
            }

            if (Schema::hasColumn('employee_allowances', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }

            if (Schema::hasColumn('employee_allowances', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }

            if (Schema::hasColumn('employee_allowances', 'rejected_by')) {
                $table->dropColumn('rejected_by');
            }

            if (Schema::hasColumn('employee_allowances', 'status')) {
                $table->dropIndex('idx_employee_allowances_status');
                $table->dropColumn('status');
            }
        });
    }
};
