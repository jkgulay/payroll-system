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
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'name' => 'Administrator',
                'is_active' => true,
            ]);
            $this->command->info('Default admin user created successfully!');
            $this->command->info('Username: admin | Password: Admin123!');
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

        $this->command->info('Database seeding completed! You can now add employees through the application.');
    }
}
