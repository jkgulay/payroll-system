<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            if (!Schema::hasColumn('company_info', 'sig_payslip_prepared_by')) {
                $table->string('sig_payslip_prepared_by')->nullable()->after('sig_approved_by_2');
            }

            if (!Schema::hasColumn('company_info', 'sig_payslip_checked_by')) {
                $table->string('sig_payslip_checked_by')->nullable()->after('sig_payslip_prepared_by');
            }

            if (!Schema::hasColumn('company_info', 'sig_payslip_recommended_by')) {
                $table->string('sig_payslip_recommended_by')->nullable()->after('sig_payslip_checked_by');
            }

            if (!Schema::hasColumn('company_info', 'sig_payslip_approved_by')) {
                $table->string('sig_payslip_approved_by')->nullable()->after('sig_payslip_recommended_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_info', function (Blueprint $table) {
            $columns = [
                'sig_payslip_prepared_by',
                'sig_payslip_checked_by',
                'sig_payslip_recommended_by',
                'sig_payslip_approved_by',
            ];

            $existingColumns = array_filter(
                $columns,
                fn(string $column): bool => Schema::hasColumn('company_info', $column)
            );

            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
