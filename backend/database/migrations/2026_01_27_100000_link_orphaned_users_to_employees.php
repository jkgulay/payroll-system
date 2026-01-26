<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Employee;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration automatically links user accounts to their corresponding
     * employee records by matching email or username pattern (firstname.lastname).
     */
    public function up(): void
    {
        $users = User::where('role', 'employee')
            ->where(function ($query) {
                $query->whereNull('employee_id')
                    ->orWhere('employee_id', 0);
            })
            ->get();

        $linked = 0;

        foreach ($users as $user) {
            // Try to match by email first
            $employee = Employee::where('email', $user->email)->first();

            // If no match by email, try matching by username pattern (firstname.lastname)
            if (!$employee && $user->username) {
                $employee = Employee::whereRaw(
                    "LOWER(CONCAT(first_name, '.', last_name)) = ?",
                    [strtolower($user->username)]
                )->first();
            }

            // If still no match, try matching just by last name from username
            if (!$employee && $user->username && str_contains($user->username, '.')) {
                $parts = explode('.', $user->username);
                $lastName = end($parts);
                $firstName = $parts[0] ?? '';

                $employee = Employee::whereRaw('LOWER(last_name) = ?', [strtolower($lastName)])
                    ->whereRaw('LOWER(first_name) LIKE ?', [strtolower($firstName) . '%'])
                    ->first();
            }

            if ($employee) {
                $user->employee_id = $employee->id;
                $user->save();
                $linked++;
            }
        }

        if ($linked > 0) {
            \Log::info("Migration linked {$linked} orphaned user accounts to employees.");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't unlink users on rollback as that could break the system
        // This is a data fix migration, not a schema change
    }
};
