<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrate data from old separate government contribution tables to the unified government_rates table
     * and drop the old tables.
     */
    public function up(): void
    {
        // Migrate SSS data
        $sssRecords = DB::table('sss_contribution_table')->where('is_active', true)->get();
        foreach ($sssRecords as $record) {
            $salaryRange = number_format($record->range_compensation_from, 2) .
                ($record->range_compensation_to ? ' - ' . number_format($record->range_compensation_to, 2) : '+');
            DB::table('government_rates')->insert([
                'type' => 'sss',
                'name' => "SSS {$salaryRange}",
                'min_salary' => $record->range_compensation_from,
                'max_salary' => $record->range_compensation_to,
                'employee_rate' => null,
                'employee_fixed' => $record->employee_contribution,
                'employer_rate' => null,
                'employer_fixed' => $record->employer_contribution,
                'total_contribution' => $record->total_contribution,
                'effective_date' => $record->effective_date,
                'end_date' => $record->end_date,
                'is_active' => $record->is_active,
                'notes' => "Migrated from old SSS table. MSC: {$record->monthly_salary_credit}, EC: {$record->employer_ec}, WISP: {$record->employer_wisp}",
                'created_at' => $record->created_at ?? now(),
                'updated_at' => $record->updated_at ?? now(),
            ]);
        }

        // Migrate PhilHealth data
        $philhealthRecords = DB::table('philhealth_contribution_table')->where('is_active', true)->get();
        foreach ($philhealthRecords as $record) {
            $salaryRange = number_format($record->monthly_basic_salary_from, 2) .
                ($record->monthly_basic_salary_to ? ' - ' . number_format($record->monthly_basic_salary_to, 2) : '+');
            DB::table('government_rates')->insert([
                'type' => 'philhealth',
                'name' => "PhilHealth {$salaryRange}",
                'min_salary' => $record->monthly_basic_salary_from,
                'max_salary' => $record->monthly_basic_salary_to,
                'employee_rate' => $record->premium_rate / 2, // Split 4% into 2% each
                'employee_fixed' => null,
                'employer_rate' => $record->premium_rate / 2,
                'employer_fixed' => null,
                'total_contribution' => null,
                'effective_date' => $record->effective_date,
                'end_date' => $record->end_date,
                'is_active' => $record->is_active,
                'notes' => $record->notes ?? "Migrated from old PhilHealth table. Min: {$record->minimum_monthly_premium}, Max: {$record->maximum_monthly_premium}",
                'created_at' => $record->created_at ?? now(),
                'updated_at' => $record->updated_at ?? now(),
            ]);
        }

        // Migrate Pag-IBIG data
        $pagibigRecords = DB::table('pagibig_contribution_table')->where('is_active', true)->get();
        foreach ($pagibigRecords as $record) {
            $salaryRange = number_format($record->monthly_compensation_from, 2) .
                ($record->monthly_compensation_to ? ' - ' . number_format($record->monthly_compensation_to, 2) : '+');
            DB::table('government_rates')->insert([
                'type' => 'pagibig',
                'name' => "Pag-IBIG {$salaryRange}",
                'min_salary' => $record->monthly_compensation_from,
                'max_salary' => $record->monthly_compensation_to,
                'employee_rate' => $record->employee_rate,
                'employee_fixed' => null,
                'employer_rate' => $record->employer_rate,
                'employer_fixed' => null,
                'total_contribution' => null,
                'effective_date' => $record->effective_date,
                'end_date' => $record->end_date,
                'is_active' => $record->is_active,
                'notes' => $record->notes ?? "Migrated from old Pag-IBIG table",
                'created_at' => $record->created_at ?? now(),
                'updated_at' => $record->updated_at ?? now(),
            ]);
        }

        echo "Migrated " . $sssRecords->count() . " SSS records\n";
        echo "Migrated " . $philhealthRecords->count() . " PhilHealth records\n";
        echo "Migrated " . $pagibigRecords->count() . " Pag-IBIG records\n";

        // Drop old tables
        Schema::dropIfExists('sss_contribution_table');
        Schema::dropIfExists('philhealth_contribution_table');
        Schema::dropIfExists('pagibig_contribution_table');
    }

    /**
     * Reverse the migrations.
     * Restore old tables from backup migration
     */
    public function down(): void
    {
        // Recreate old tables by running the original migration
        // SSS Contribution Table
        Schema::create('sss_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('range_compensation_from', 10, 2);
            $table->decimal('range_compensation_to', 10, 2)->nullable();
            $table->decimal('monthly_salary_credit', 10, 2);
            $table->decimal('employer_contribution', 10, 2);
            $table->decimal('employee_contribution', 10, 2);
            $table->decimal('total_contribution', 10, 2);
            $table->decimal('employer_ec', 10, 2)->default(10.00);
            $table->decimal('employer_wisp', 10, 2)->default(0);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // PhilHealth Contribution Table
        Schema::create('philhealth_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_basic_salary_from', 10, 2);
            $table->decimal('monthly_basic_salary_to', 10, 2)->nullable();
            $table->decimal('premium_rate', 5, 2);
            $table->decimal('minimum_monthly_premium', 10, 2)->nullable();
            $table->decimal('maximum_monthly_premium', 10, 2)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Pag-IBIG Contribution Table
        Schema::create('pagibig_contribution_table', function (Blueprint $table) {
            $table->id();
            $table->decimal('monthly_compensation_from', 10, 2);
            $table->decimal('monthly_compensation_to', 10, 2)->nullable();
            $table->decimal('employee_contribution_rate', 5, 2);
            $table->decimal('employer_contribution_rate', 5, 2);
            $table->decimal('maximum_compensation', 10, 2)->nullable();
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Migrate data back from government_rates
        $sssRates = DB::table('government_rates')->where('type', 'sss')->get();
        foreach ($sssRates as $rate) {
            DB::table('sss_contribution_table')->insert([
                'range_compensation_from' => $rate->min_salary,
                'range_compensation_to' => $rate->max_salary,
                'monthly_salary_credit' => $rate->max_salary ?? $rate->min_salary,
                'employer_contribution' => $rate->employer_fixed ?? 0,
                'employee_contribution' => $rate->employee_fixed ?? 0,
                'total_contribution' => $rate->total_contribution ?? 0,
                'employer_ec' => 10.00,
                'employer_wisp' => 0,
                'effective_date' => $rate->effective_date,
                'end_date' => $rate->end_date,
                'is_active' => $rate->is_active,
                'created_at' => $rate->created_at,
                'updated_at' => $rate->updated_at,
            ]);
        }

        $philhealthRates = DB::table('government_rates')->where('type', 'philhealth')->get();
        foreach ($philhealthRates as $rate) {
            DB::table('philhealth_contribution_table')->insert([
                'monthly_basic_salary_from' => $rate->min_salary,
                'monthly_basic_salary_to' => $rate->max_salary,
                'premium_rate' => ($rate->employee_rate ?? 0) * 2, // Combine back to total rate
                'minimum_monthly_premium' => null,
                'maximum_monthly_premium' => null,
                'effective_date' => $rate->effective_date,
                'end_date' => $rate->end_date,
                'is_active' => $rate->is_active,
                'notes' => $rate->notes,
                'created_at' => $rate->created_at,
                'updated_at' => $rate->updated_at,
            ]);
        }

        $pagibigRates = DB::table('government_rates')->where('type', 'pagibig')->get();
        foreach ($pagibigRates as $rate) {
            DB::table('pagibig_contribution_table')->insert([
                'monthly_compensation_from' => $rate->min_salary,
                'monthly_compensation_to' => $rate->max_salary,
                'employee_contribution_rate' => $rate->employee_rate ?? 0,
                'employer_contribution_rate' => $rate->employer_rate ?? 0,
                'maximum_compensation' => null,
                'effective_date' => $rate->effective_date,
                'end_date' => $rate->end_date,
                'is_active' => $rate->is_active,
                'notes' => $rate->notes,
                'created_at' => $rate->created_at,
                'updated_at' => $rate->updated_at,
            ]);
        }

        // Delete migrated records from government_rates
        DB::table('government_rates')->whereIn('type', ['sss', 'philhealth', 'pagibig'])->delete();
    }
};
