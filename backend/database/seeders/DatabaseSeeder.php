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
        // Create default admin user
        User::create([
            'username' => 'admin',
            'email' => 'admin@payroll.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create accountant user
        User::create([
            'username' => 'accountant',
            'email' => 'accountant@payroll.com',
            'password' => Hash::make('accountant123'),
            'role' => 'accountant',
            'is_active' => true,
        ]);

        // Create employee user
        User::create([
            'username' => 'employee',
            'email' => 'employee@payroll.com',
            'password' => Hash::make('employee123'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        $this->command->info('Default users created successfully!');
    }
}
