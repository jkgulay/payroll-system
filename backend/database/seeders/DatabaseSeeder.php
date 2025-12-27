<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Project;
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

        // Create projects if they don't exist
        if (Project::count() === 0) {
            $projects = [
                ['name' => 'Main Office Building', 'code' => 'MOB', 'description' => 'Main Office Building Construction'],
                ['name' => 'Highway Expansion', 'code' => 'HWY', 'description' => 'Highway Expansion Project'],
                ['name' => 'Residential Complex', 'code' => 'RES', 'description' => 'Residential Complex Development'],
                ['name' => 'Bridge Construction', 'code' => 'BRG', 'description' => 'Bridge Construction Project'],
                ['name' => 'Mall Renovation', 'code' => 'MALL', 'description' => 'Shopping Mall Renovation'],
            ];

            foreach ($projects as $proj) {
                Project::create($proj);
            }
            $this->command->info('Projects created successfully!');
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
            $project1 = Project::where('code', 'MOB')->first();
            $project2 = Project::where('code', 'HWY')->first();
            $project3 = Project::where('code', 'RES')->first();

            $employees = [
                [
                    'employee_number' => 'EMP001',
                    'first_name' => 'Juan',
                    'last_name' => 'Dela Cruz',
                    'date_of_birth' => '1990-05-15',
                    'email' => 'juan.delacruz@company.com',
                    'mobile_number' => '09171234567',
                    'position' => 'Site Engineer',
                    'project_id' => $project1->id,
                    'worker_address' => '123 Rizal St, Makati City, Metro Manila',
                    'employment_status' => 'regular',
                    'employment_type' => 'regular',
                    'basic_salary' => 800,
                    'salary_type' => 'daily',
                    'date_hired' => '2023-01-15',
                ],
                [
                    'employee_number' => 'EMP002',
                    'first_name' => 'Maria',
                    'last_name' => 'Santos',
                    'date_of_birth' => '1992-08-20',
                    'email' => 'maria.santos@company.com',
                    'mobile_number' => '09181234567',
                    'position' => 'Construction Worker',
                    'project_id' => $project2->id,
                    'worker_address' => '456 Bonifacio Ave, Quezon City, Metro Manila',
                    'employment_status' => 'regular',
                    'employment_type' => 'regular',
                    'basic_salary' => 600,
                    'salary_type' => 'daily',
                    'date_hired' => '2023-03-20',
                ],
                [
                    'employee_number' => 'EMP003',
                    'first_name' => 'Pedro',
                    'last_name' => 'Reyes',
                    'date_of_birth' => '1995-12-10',
                    'email' => 'pedro.reyes@company.com',
                    'mobile_number' => '09191234567',
                    'position' => 'Mason',
                    'project_id' => $project3->id,
                    'worker_address' => '789 Del Pilar St, Pasig City, Metro Manila',
                    'employment_status' => 'probationary',
                    'employment_type' => 'regular',
                    'basic_salary' => 550,
                    'salary_type' => 'daily',
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

