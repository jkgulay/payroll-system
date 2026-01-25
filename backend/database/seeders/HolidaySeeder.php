<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Holiday;
use Carbon\Carbon;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, update any existing holidays with 'special_non_working' to 'special'
        DB::table('holidays')
            ->where('type', 'special_non_working')
            ->update(['type' => 'special']);
        
        // Delete any existing 2026 holidays to avoid duplicates
        Holiday::whereYear('date', 2026)->delete();
        
        // 2026 Philippine Holidays (based on typical Philippine calendar)
        $holidays = [
            // Regular Holidays
            [
                'name' => 'New Year\'s Day',
                'date' => '2026-01-01',
                'type' => 'regular',
                'description' => 'New Year\'s Day celebration',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Maundy Thursday',
                'date' => '2026-04-02',
                'type' => 'regular',
                'description' => 'Holy Week - Maundy Thursday',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Good Friday',
                'date' => '2026-04-03',
                'type' => 'regular',
                'description' => 'Holy Week - Good Friday',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Araw ng Kagitingan (Day of Valor)',
                'date' => '2026-04-09',
                'type' => 'regular',
                'description' => 'Commemoration of the Fall of Bataan',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Labor Day',
                'date' => '2026-05-01',
                'type' => 'regular',
                'description' => 'International Workers\' Day',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Independence Day',
                'date' => '2026-06-12',
                'type' => 'regular',
                'description' => 'Philippine Independence Day',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Eid al-Adha',
                'date' => '2026-06-17',
                'type' => 'regular',
                'description' => 'Feast of Sacrifice (Islamic holiday, date may vary)',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Ninoy Aquino Day',
                'date' => '2026-08-21',
                'type' => 'regular',
                'description' => 'Commemoration of Ninoy Aquino\'s assassination',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'National Heroes Day',
                'date' => '2026-08-31',
                'type' => 'regular',
                'description' => 'Last Monday of August - National Heroes Day',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Bonifacio Day',
                'date' => '2026-11-30',
                'type' => 'regular',
                'description' => 'Andres Bonifacio\'s birthday',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Christmas Day',
                'date' => '2026-12-25',
                'type' => 'regular',
                'description' => 'Christmas Day celebration',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Rizal Day',
                'date' => '2026-12-30',
                'type' => 'regular',
                'description' => 'Commemoration of Dr. Jose Rizal\'s martyrdom',
                'is_recurring' => true,
                'is_active' => true,
            ],

            // Special Non-Working Holidays
            [
                'name' => 'Chinese New Year',
                'date' => '2026-02-17',
                'type' => 'special',
                'description' => 'Chinese New Year celebration',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'EDSA People Power Revolution Anniversary',
                'date' => '2026-02-25',
                'type' => 'special',
                'description' => 'Commemoration of EDSA Revolution',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Black Saturday',
                'date' => '2026-04-04',
                'type' => 'special',
                'description' => 'Holy Week - Black Saturday',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Eid al-Fitr',
                'date' => '2026-03-21',
                'type' => 'special',
                'description' => 'End of Ramadan (Islamic holiday, date may vary)',
                'is_recurring' => false,
                'is_active' => true,
            ],
            [
                'name' => 'All Saints\' Day',
                'date' => '2026-11-01',
                'type' => 'special',
                'description' => 'All Saints\' Day',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'All Souls\' Day',
                'date' => '2026-11-02',
                'type' => 'special',
                'description' => 'All Souls\' Day',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Immaculate Conception of Mary',
                'date' => '2026-12-08',
                'type' => 'special',
                'description' => 'Feast of the Immaculate Conception',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Christmas Eve',
                'date' => '2026-12-24',
                'type' => 'special',
                'description' => 'Christmas Eve (usually half-day)',
                'is_recurring' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Last Day of the Year',
                'date' => '2026-12-31',
                'type' => 'special',
                'description' => 'New Year\'s Eve',
                'is_recurring' => true,
                'is_active' => true,
            ],
        ];

        foreach ($holidays as $holiday) {
            Holiday::create($holiday);
        }

        $this->command->info('Philippine holidays for 2026 seeded successfully!');
    }
}
