<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate government IDs from employees table into employee_government_info
     * (canonical store), then drop the duplicate columns from employees.
     */
    public function up(): void
    {
        // Step 1: Migrate data — preserve values already in employee_government_info,
        // only back-fill from employees where the gov info record is missing or null.
        $employees = DB::table('employees')
            ->where(function ($q) {
                $q->whereNotNull('sss_number')
                    ->orWhereNotNull('philhealth_number')
                    ->orWhereNotNull('pagibig_number')
                    ->orWhereNotNull('tin_number');
            })
            ->select('id', 'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number')
            ->get();

        foreach ($employees as $emp) {
            $existing = DB::table('employee_government_info')
                ->where('employee_id', $emp->id)
                ->first();

            if ($existing) {
                // Only fill in fields that are currently null in the canonical store
                $updates = [];
                if (!$existing->sss_number        && $emp->sss_number)        $updates['sss_number']        = $emp->sss_number;
                if (!$existing->philhealth_number  && $emp->philhealth_number)  $updates['philhealth_number']  = $emp->philhealth_number;
                if (!$existing->pagibig_number     && $emp->pagibig_number)     $updates['pagibig_number']     = $emp->pagibig_number;
                if (!$existing->tin_number         && $emp->tin_number)         $updates['tin_number']         = $emp->tin_number;
                if (!empty($updates)) {
                    $updates['updated_at'] = now();
                    DB::table('employee_government_info')
                        ->where('employee_id', $emp->id)
                        ->update($updates);
                }
            } else {
                // Create a new gov info record for this employee
                DB::table('employee_government_info')->insert([
                    'employee_id'       => $emp->id,
                    'sss_number'        => $emp->sss_number,
                    'philhealth_number' => $emp->philhealth_number,
                    'pagibig_number'    => $emp->pagibig_number,
                    'tin_number'        => $emp->tin_number,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        }

        // Step 2: Drop the duplicate columns from employees
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['sss_number', 'philhealth_number', 'pagibig_number', 'tin_number']);
        });
    }

    public function down(): void
    {
        // Restore the columns (data will NOT be copied back — use employee_government_info)
        Schema::table('employees', function (Blueprint $table) {
            $table->string('sss_number', 20)->nullable()->after('tin_number');
            $table->string('philhealth_number', 20)->nullable()->after('sss_number');
            $table->string('pagibig_number', 20)->nullable()->after('philhealth_number');
            $table->string('tin_number', 20)->nullable()->after('pagibig_number');
        });
    }
};
