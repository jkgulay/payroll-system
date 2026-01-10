<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaveType;

class LeaveTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            [
                'code' => 'VL',
                'name' => 'Vacation Leave',
                'description' => 'Paid vacation leave for rest and recreation',
                'default_credits' => 15,
                'is_paid' => true,
                'requires_approval' => true,
                'is_convertible_to_cash' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SL',
                'name' => 'Sick Leave',
                'description' => 'Paid leave for illness or medical appointments',
                'default_credits' => 15,
                'is_paid' => true,
                'requires_approval' => true,
                'is_convertible_to_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => 'EL',
                'name' => 'Emergency Leave',
                'description' => 'Leave for unforeseen emergencies',
                'default_credits' => 5,
                'is_paid' => true,
                'requires_approval' => true,
                'is_convertible_to_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => 'ML',
                'name' => 'Maternity Leave',
                'description' => 'Maternity leave for female employees',
                'default_credits' => 105, // 105 days as per Philippine law
                'is_paid' => true,
                'requires_approval' => true,
                'is_convertible_to_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => 'PL',
                'name' => 'Paternity Leave',
                'description' => 'Paternity leave for male employees',
                'default_credits' => 7, // 7 days as per Philippine law
                'is_paid' => true,
                'requires_approval' => true,
                'is_convertible_to_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => 'BL',
                'name' => 'Bereavement Leave',
                'description' => 'Leave for death of immediate family member',
                'default_credits' => 3,
                'is_paid' => true,
                'requires_approval' => true,
                'is_convertible_to_cash' => false,
                'is_active' => true,
            ],
            [
                'code' => 'LWOP',
                'name' => 'Leave Without Pay',
                'description' => 'Unpaid leave for personal reasons',
                'default_credits' => 0, // No limit, but unpaid
                'is_paid' => false,
                'requires_approval' => true,
                'is_convertible_to_cash' => false,
                'is_active' => true,
            ],
        ];

        foreach ($leaveTypes as $leaveType) {
            LeaveType::firstOrCreate(
                ['code' => $leaveType['code']],
                $leaveType
            );
        }

        $this->command->info('Leave types created successfully!');
    }
}
