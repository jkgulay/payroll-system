<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\PositionRate;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed leave types first (required for employees)
        $this->call(LeaveTypesSeeder::class);
        
        // Create default admin user only if it doesn't exist
        if (!User::where('username', 'admin')->exists()) {
            User::create([
                'username' => 'admin',
                'email' => 'admin@payroll.com',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'name' => 'Administrator',
                'is_active' => true,
            ]);
            $this->command->info('Default admin user created successfully!');
            $this->command->info('Username: admin | Password: Admin123!');
        }

        // Create a dummy employee with login credentials
        if (!User::where('username', 'employee')->exists()) {
            // First, ensure we have a position
            $position = PositionRate::firstOrCreate(
                ['position_name' => 'General Worker'],
                [
                    'code' => 'GW',
                    'daily_rate' => 400.00,
                    'category' => 'general',
                    'is_active' => true
                ]
            );

            // Get a project
            $project = \App\Models\Project::first();
            if (!$project) {
                $project = \App\Models\Project::create([
                    'code' => 'GEN',
                    'name' => 'General Project',
                    'description' => 'General project for employees',
                    'is_active' => true
                ]);
            }

            // Create employee record
            $employee = Employee::create([
                'employee_number' => 'EMP-TEST-001',
                'first_name' => 'John',
                'middle_name' => 'Doe',
                'last_name' => 'Employee',
                'date_of_birth' => '1995-01-15',
                'email' => 'employee@test.com',
                'mobile_number' => '09123456789',
                'project_id' => $project->id,
                'position_id' => $position->id,
                'basic_salary' => 400.00,
                'salary_type' => 'daily',
                'date_hired' => now()->subMonths(6)->format('Y-m-d'),
                'contract_type' => 'regular',
                'activity_status' => 'active',
                'gender' => 'male',
                'civil_status' => 'single',
                'nationality' => 'Filipino',
            ]);

            // Create user account for employee
            User::create([
                'employee_id' => $employee->id,
                'username' => 'employee',
                'email' => 'employee@test.com',
                'password' => Hash::make('Employee123!'),
                'role' => 'employee',
                'name' => 'John Doe Employee',
                'is_active' => true,
            ]);

            $this->command->info('Dummy employee user created successfully!');
            $this->command->info('Username: employee | Password: Employee123!');
        }

        $this->command->info('Database seeding completed! You can now login with employee credentials.');
    }
}
