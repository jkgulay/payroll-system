<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed leave types (required for employees)
        $this->call(LeaveTypesSeeder::class);
        
        // Create default admin user only if it doesn't exist
        if (!User::where('username', 'admin')->exists()) {
            User::create([
                'username' => 'admin',
                'email' => 'admin@payroll.com',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'name' => 'System Administrator',
                'is_active' => true,
            ]);
            $this->command->info('Default admin user created successfully!');
            $this->command->info('Username: admin | Password: Admin123!');
            $this->command->warn('Please change the admin password after first login!');
        }

        $this->command->info('Database seeding completed!');
    }
}
