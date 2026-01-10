<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        // Link existing employees to users
        $employees = DB::table('employees')
            ->whereNull('user_id')
            ->whereNull('deleted_at')
            ->get();

        foreach ($employees as $employee) {
            // Try to find matching user by email first
            $user = null;

            if (!empty($employee->email)) {
                $user = DB::table('users')
                    ->where('email', $employee->email)
                    ->whereNull('deleted_at')
                    ->first();
            }

            // If no match by email, try by username (firstname.lastname pattern)
            if (!$user && !empty($employee->first_name) && !empty($employee->last_name)) {
                $possibleUsername = strtolower($employee->first_name . '.' . $employee->last_name);
                $possibleUsername = preg_replace('/[^a-z0-9.]/', '', $possibleUsername);

                $user = DB::table('users')
                    ->where('username', 'like', $possibleUsername . '%')
                    ->whereNull('deleted_at')
                    ->first();
            }

            if ($user) {
                // Update employee with user_id
                DB::table('employees')
                    ->where('id', $employee->id)
                    ->update(['user_id' => $user->id]);

                // Update user with employee_id if not already set
                if (empty($user->employee_id)) {
                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['employee_id' => $employee->id]);
                }

                Log::info("Linked employee #{$employee->id} ({$employee->first_name} {$employee->last_name}) to user #{$user->id} ({$user->username})");
            } else {
                Log::warning("Could not find matching user for employee #{$employee->id} ({$employee->first_name} {$employee->last_name})");
            }
        }

        // Link existing users (with role=employee) to employee records if not already linked
        $users = DB::table('users')
            ->where('role', 'employee')
            ->whereNull('employee_id')
            ->whereNull('deleted_at')
            ->get();

        foreach ($users as $user) {
            // Try to find matching employee by email
            $employee = null;

            if (!empty($user->email)) {
                $employee = DB::table('employees')
                    ->where('email', $user->email)
                    ->whereNull('deleted_at')
                    ->first();
            }

            // Try by username pattern (extract firstname.lastname)
            if (!$employee && strpos($user->username, '.') !== false) {
                $parts = explode('.', $user->username);
                if (count($parts) >= 2) {
                    $firstName = $parts[0];
                    $lastName = $parts[1];

                    $employee = DB::table('employees')
                        ->whereRaw('LOWER(first_name) = ?', [strtolower($firstName)])
                        ->whereRaw('LOWER(last_name) LIKE ?', [strtolower($lastName) . '%'])
                        ->whereNull('deleted_at')
                        ->first();
                }
            }

            if ($employee) {
                // Update user with employee_id
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['employee_id' => $employee->id]);

                // Update employee with user_id if not already set
                if (empty($employee->user_id)) {
                    DB::table('employees')
                        ->where('id', $employee->id)
                        ->update(['user_id' => $user->id]);
                }

                Log::info("Linked user #{$user->id} ({$user->username}) to employee #{$employee->id} ({$employee->first_name} {$employee->last_name})");
            } else {
                Log::warning("Could not find matching employee for user #{$user->id} ({$user->username})");
            }
        }
    }

    public function down(): void
    {
        // Don't remove links on rollback - they might be intentional
        Log::info('Rollback: Not removing user/employee links as they might be intentional');
    }
};
