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
        if (Schema::hasTable('employee_leaves')) {
            Schema::table('employee_leaves', function (Blueprint $table) {
                if (!Schema::hasColumn('employee_leaves', 'is_with_pay')) {
                    $table->boolean('is_with_pay')->nullable()->after('number_of_days');
                }

                if (!Schema::hasColumn('employee_leaves', 'duration_type')) {
                    $table->string('duration_type', 20)->default('full_day')->after('leave_date_to');
                }

                if (!Schema::hasColumn('employee_leaves', 'duration_hours')) {
                    $table->decimal('duration_hours', 6, 2)->nullable()->after('duration_type');
                }

                if (!Schema::hasColumn('employee_leaves', 'is_locked')) {
                    $table->boolean('is_locked')->default(false)->after('status');
                }

                if (!Schema::hasColumn('employee_leaves', 'locked_by_payroll_id')) {
                    $table->foreignId('locked_by_payroll_id')
                        ->nullable()
                        ->after('is_locked')
                        ->constrained('payrolls')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('employee_leaves', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable()->after('locked_by_payroll_id');
                }
            });
        }

        if (!Schema::hasTable('employee_leave_outs')) {
            Schema::create('employee_leave_outs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_leave_id')->constrained('employee_leaves')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
                $table->date('leave_date_from');
                $table->date('leave_date_to');
                $table->string('duration_type', 20)->default('full_day');
                $table->decimal('quantity_days', 6, 2)->default(0);
                $table->decimal('quantity_hours', 6, 2)->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique('employee_leave_id');
                $table->index(['employee_id', 'leave_date_from', 'leave_date_to'], 'idx_leave_out_employee_dates');
            });
        }

        if (!Schema::hasTable('payroll_item_leave_outs')) {
            Schema::create('payroll_item_leave_outs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_leave_out_id')->constrained('employee_leave_outs')->cascadeOnDelete();
                $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
                $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
                $table->decimal('deduction_amount', 12, 2)->default(0);
                $table->decimal('applied_days', 6, 2)->default(0);
                $table->decimal('applied_hours', 6, 2)->default(0);
                $table->timestamps();

                $table->unique(['employee_leave_out_id', 'payroll_item_id'], 'uq_leave_out_payroll_item');
                $table->unique('employee_leave_out_id', 'uq_leave_out_single_usage');
                $table->index(['payroll_id', 'payroll_item_id'], 'idx_leave_out_payroll_lookup');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payroll_item_leave_outs')) {
            Schema::drop('payroll_item_leave_outs');
        }

        if (Schema::hasTable('employee_leave_outs')) {
            Schema::drop('employee_leave_outs');
        }

        if (Schema::hasTable('employee_leaves')) {
            Schema::table('employee_leaves', function (Blueprint $table) {
                if (Schema::hasColumn('employee_leaves', 'locked_by_payroll_id')) {
                    $table->dropConstrainedForeignId('locked_by_payroll_id');
                }

                if (Schema::hasColumn('employee_leaves', 'locked_at')) {
                    $table->dropColumn('locked_at');
                }

                if (Schema::hasColumn('employee_leaves', 'is_locked')) {
                    $table->dropColumn('is_locked');
                }

                if (Schema::hasColumn('employee_leaves', 'duration_hours')) {
                    $table->dropColumn('duration_hours');
                }

                if (Schema::hasColumn('employee_leaves', 'duration_type')) {
                    $table->dropColumn('duration_type');
                }

                if (Schema::hasColumn('employee_leaves', 'is_with_pay')) {
                    $table->dropColumn('is_with_pay');
                }
            });
        }
    }
};
