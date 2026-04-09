<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            if (!Schema::hasColumn('payrolls', 'payroll_scope')) {
                $table->string('payroll_scope')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('payrolls', 'individual_target')) {
                $table->string('individual_target')->nullable()->after('payroll_scope');
            }

            if (!Schema::hasColumn('payrolls', 'included_position')) {
                $table->string('included_position')->nullable()->after('individual_target');
            }

            if (!Schema::hasColumn('payrolls', 'included_employee_id')) {
                $table->unsignedBigInteger('included_employee_id')->nullable()->after('included_position');
                $table->index('included_employee_id');
            }

            if (!Schema::hasColumn('payrolls', 'has_attendance')) {
                $table->boolean('has_attendance')->nullable()->after('included_employee_id');
            }

            if (!Schema::hasColumn('payrolls', 'excluded_positions')) {
                $table->json('excluded_positions')->nullable()->after('has_attendance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $columns = [
                'payroll_scope',
                'individual_target',
                'included_position',
                'included_employee_id',
                'has_attendance',
                'excluded_positions',
            ];

            $existingColumns = array_filter($columns, fn (string $column): bool => Schema::hasColumn('payrolls', $column));

            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
