<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Migrates allowances from employees table to employee_allowances table
     */
    public function up(): void
    {
        // Step 1: Migrate existing allowance data to employee_allowances table
        $this->migrateAllowanceData();

        // Step 2: Drop allowance columns from employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'has_water_allowance',
                'water_allowance',
                'has_cola',
                'cola',
                'has_incentives',
                'incentives',
                'has_ppe',
                'ppe',
            ]);
        });

        echo "✅ Allowances migrated to employee_allowances table\n";
        echo "✅ Deprecated allowance columns removed from employees table\n";
    }

    /**
     * Migrate allowance data from employees to employee_allowances
     */
    private function migrateAllowanceData(): void
    {
        $employees = DB::table('employees')->get();
        $migratedCount = 0;

        foreach ($employees as $employee) {
            $allowances = [];

            // Water allowance
            if ($employee->has_water_allowance && $employee->water_allowance > 0) {
                $allowances[] = [
                    'employee_id' => $employee->id,
                    'allowance_type' => 'water',
                    'amount' => $employee->water_allowance,
                    'is_taxable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // COLA (Cost of Living Allowance)
            if ($employee->has_cola && $employee->cola > 0) {
                $allowances[] = [
                    'employee_id' => $employee->id,
                    'allowance_type' => 'cola',
                    'amount' => $employee->cola,
                    'is_taxable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Incentives
            if ($employee->has_incentives && $employee->incentives > 0) {
                $allowances[] = [
                    'employee_id' => $employee->id,
                    'allowance_type' => 'incentive',
                    'amount' => $employee->incentives,
                    'is_taxable' => true, // Incentives are typically taxable
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // PPE (Personal Protective Equipment)
            if ($employee->has_ppe && $employee->ppe > 0) {
                $allowances[] = [
                    'employee_id' => $employee->id,
                    'allowance_type' => 'ppe',
                    'amount' => $employee->ppe,
                    'is_taxable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert allowances if any
            if (!empty($allowances)) {
                // Check if allowances already exist to avoid duplicates
                foreach ($allowances as $allowance) {
                    $exists = DB::table('employee_allowances')
                        ->where('employee_id', $allowance['employee_id'])
                        ->where('allowance_type', $allowance['allowance_type'])
                        ->exists();

                    if (!$exists) {
                        DB::table('employee_allowances')->insert($allowance);
                        $migratedCount++;
                    }
                }
            }
        }

        echo "✅ Migrated {$migratedCount} allowances from {$employees->count()} employees\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Restore allowance columns to employees table
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('has_water_allowance')->default(false);
            $table->decimal('water_allowance', 10, 2)->default(0);
            $table->boolean('has_cola')->default(false);
            $table->decimal('cola', 10, 2)->default(0);
            $table->boolean('has_incentives')->default(false);
            $table->decimal('incentives', 10, 2)->default(0);
            $table->boolean('has_ppe')->default(false);
            $table->decimal('ppe', 10, 2)->default(0);
        });

        // Step 2: Restore data from employee_allowances table
        $allowances = DB::table('employee_allowances')->get();

        foreach ($allowances as $allowance) {
            $updates = [];

            switch ($allowance->allowance_type) {
                case 'water':
                    $updates = [
                        'has_water_allowance' => true,
                        'water_allowance' => $allowance->amount,
                    ];
                    break;
                case 'cola':
                    $updates = [
                        'has_cola' => true,
                        'cola' => $allowance->amount,
                    ];
                    break;
                case 'incentive':
                    $updates = [
                        'has_incentives' => true,
                        'incentives' => $allowance->amount,
                    ];
                    break;
                case 'ppe':
                    $updates = [
                        'has_ppe' => true,
                        'ppe' => $allowance->amount,
                    ];
                    break;
            }

            if (!empty($updates)) {
                DB::table('employees')
                    ->where('id', $allowance->employee_id)
                    ->update($updates);
            }
        }

        echo "✅ Allowances restored to employees table\n";
    }
};
