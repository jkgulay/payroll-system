<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class DefaultProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if there are any projects already
        if (Project::count() === 0) {
            DB::table('projects')->insert([
                'code' => 'DEFAULT',
                'name' => 'Default Project',
                'description' => 'Default project for employees without assigned project',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $this->command->info('Default project created successfully!');
        } else {
            $this->command->info('Projects already exist, skipping default project creation.');
        }
    }
}
