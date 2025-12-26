<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use App\Models\Location;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default admin user only if it doesn't exist
        if (!User::where('username', 'admin')->exists()) {
            User::create([
                'username' => 'admin',
                'email' => 'admin@payroll.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]);
            $this->command->info('Default admin user created successfully!');
        }

        // Create departments if they don't exist
        if (Department::count() === 0) {
            $departments = [
                ['name' => 'Human Resources', 'code' => 'HR', 'description' => 'Human Resources Department'],
                ['name' => 'Finance', 'code' => 'FIN', 'description' => 'Finance Department'],
                ['name' => 'IT', 'code' => 'IT', 'description' => 'Information Technology Department'],
                ['name' => 'Operations', 'code' => 'OPS', 'description' => 'Operations Department'],
                ['name' => 'Sales', 'code' => 'SALES', 'description' => 'Sales Department'],
            ];

            foreach ($departments as $dept) {
                Department::create($dept);
            }
            $this->command->info('Departments created successfully!');
        }

        // Create locations if they don't exist
        if (Location::count() === 0) {
            $locations = [
                ['code' => 'HO', 'name' => 'Head Office', 'address' => 'Manila', 'location_type' => 'head_office', 'is_active' => true],
                ['code' => 'BR1', 'name' => 'Branch Office', 'address' => 'Cebu', 'location_type' => 'site', 'is_active' => true],
            ];

            foreach ($locations as $loc) {
                Location::create($loc);
            }
            $this->command->info('Locations created successfully!');
        }

        // Create sample employees if they don't exist
        if (Employee::count() === 0) {
            $hrDept = Department::where('code', 'HR')->first();
            $itDept = Department::where('code', 'IT')->first();
            $finDept = Department::where('code', 'FIN')->first();
            $mainLocation = Location::where('location_type', 'head_office')->first();

            $employees = [
                [
                    'employee_number' => 'EMP001',
                    'first_name' => 'Juan',
                    'last_name' => 'Dela Cruz',
                    'date_of_birth' => '1990-05-15',
                    'email' => 'juan.delacruz@company.com',
                    'mobile_number' => '09171234567',
                    'position' => 'HR Manager',
                    'department_id' => $hrDept->id,
                    'location_id' => $mainLocation->id,
                    'employment_status' => 'regular',
                    'employment_type' => 'regular',
                    'basic_salary' => 35000,
                    'salary_type' => 'monthly',
                    'date_hired' => '2023-01-15',
                ],
                [
                    'employee_number' => 'EMP002',
                    'first_name' => 'Maria',
                    'last_name' => 'Santos',
                    'date_of_birth' => '1992-08-20',
                    'email' => 'maria.santos@company.com',
                    'mobile_number' => '09181234567',
                    'position' => 'Software Developer',
                    'department_id' => $itDept->id,
                    'location_id' => $mainLocation->id,
                    'employment_status' => 'regular',
                    'employment_type' => 'regular',
                    'basic_salary' => 45000,
                    'salary_type' => 'monthly',
                    'date_hired' => '2023-03-20',
                ],
                [
                    'employee_number' => 'EMP003',
                    'first_name' => 'Pedro',
                    'last_name' => 'Reyes',
                    'date_of_birth' => '1995-12-10',
                    'email' => 'pedro.reyes@company.com',
                    'mobile_number' => '09191234567',
                    'position' => 'Accountant',
                    'department_id' => $finDept->id,
                    'location_id' => $mainLocation->id,
                    'employment_status' => 'probationary',
                    'employment_type' => 'regular',
                    'basic_salary' => 30000,
                    'salary_type' => 'monthly',
                    'date_hired' => '2024-11-01',
                ],
            ];

            foreach ($employees as $emp) {
                Employee::create($emp);
            }
            $this->command->info('Sample employees created successfully!');
        }
    }
}

