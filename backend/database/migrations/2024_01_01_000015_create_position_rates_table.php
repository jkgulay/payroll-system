<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_rates', function (Blueprint $table) {
            $table->id();
            $table->string('position_name', 100)->unique();
            $table->decimal('daily_rate', 10, 2);
            $table->string('category', 50)->nullable(); // skilled, semi-skilled, technical, support
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('position_name');
            $table->index('is_active');
        });

        // Insert default position rates
        $defaultRates = [
            // Skilled Workers
            ['position_name' => 'Carpenter', 'daily_rate' => 550, 'category' => 'skilled'],
            ['position_name' => 'Mason', 'daily_rate' => 550, 'category' => 'skilled'],
            ['position_name' => 'Plumber', 'daily_rate' => 520, 'category' => 'skilled'],
            ['position_name' => 'Electrician', 'daily_rate' => 570, 'category' => 'skilled'],
            ['position_name' => 'Welder', 'daily_rate' => 560, 'category' => 'skilled'],
            ['position_name' => 'Painter', 'daily_rate' => 480, 'category' => 'skilled'],
            ['position_name' => 'Steel Worker', 'daily_rate' => 550, 'category' => 'skilled'],
            ['position_name' => 'Heavy Equipment Operator', 'daily_rate' => 650, 'category' => 'skilled'],
            // Semi-Skilled Workers
            ['position_name' => 'Construction Worker', 'daily_rate' => 450, 'category' => 'semi-skilled'],
            ['position_name' => 'Helper', 'daily_rate' => 420, 'category' => 'semi-skilled'],
            ['position_name' => 'Laborer', 'daily_rate' => 400, 'category' => 'semi-skilled'],
            ['position_name' => 'Rigger', 'daily_rate' => 480, 'category' => 'semi-skilled'],
            ['position_name' => 'Scaffolder', 'daily_rate' => 480, 'category' => 'semi-skilled'],
            // Technical/Supervisory
            ['position_name' => 'Foreman', 'daily_rate' => 750, 'category' => 'technical'],
            ['position_name' => 'Site Engineer', 'daily_rate' => 1200, 'category' => 'technical'],
            ['position_name' => 'Project Engineer', 'daily_rate' => 1500, 'category' => 'technical'],
            ['position_name' => 'Safety Officer', 'daily_rate' => 800, 'category' => 'technical'],
            ['position_name' => 'Quality Control Inspector', 'daily_rate' => 700, 'category' => 'technical'],
            ['position_name' => 'Surveyor', 'daily_rate' => 650, 'category' => 'technical'],
            // Support Staff
            ['position_name' => 'Warehouse Keeper', 'daily_rate' => 450, 'category' => 'support'],
            ['position_name' => 'Timekeeper', 'daily_rate' => 450, 'category' => 'support'],
            ['position_name' => 'Security Guard', 'daily_rate' => 400, 'category' => 'support'],
            ['position_name' => 'Driver', 'daily_rate' => 480, 'category' => 'support'],
        ];

        foreach ($defaultRates as $rate) {
            DB::table('position_rates')->insert([
                'position_name' => $rate['position_name'],
                'daily_rate' => $rate['daily_rate'],
                'category' => $rate['category'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('position_rates');
    }
};
