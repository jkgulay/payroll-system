<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\PositionRate;
use Illuminate\Support\Facades\DB;

class DummyEmployeesSeeder extends Seeder
{
    public function run(): void
    {
        // First, create some position rates if they don't exist
        $positions = [
            ['position_name' => 'LEADMAN', 'code' => 'C&M', 'daily_rate' => 500.00],
            ['position_name' => 'H.E. OPERATOR/DRIVER', 'code' => 'Mq', 'daily_rate' => 450.00],
            ['position_name' => 'ELECTRICIAN', 'code' => 'Elec', 'daily_rate' => 480.00],
            ['position_name' => 'CARPENTER', 'code' => 'Carp', 'daily_rate' => 420.00],
            ['position_name' => 'MASON', 'code' => 'Mas', 'daily_rate' => 420.00],
        ];

        foreach ($positions as $pos) {
            PositionRate::firstOrCreate(
                ['position_name' => $pos['position_name']],
                [
                    'code' => $pos['code'],
                    'daily_rate' => $pos['daily_rate'],
                    'category' => 'construction',
                    'is_active' => true
                ]
            );
        }

        // Get position IDs
        $leadmanId = PositionRate::where('position_name', 'LEADMAN')->first()->id;
        $operatorId = PositionRate::where('position_name', 'H.E. OPERATOR/DRIVER')->first()->id;
        $electricianId = PositionRate::where('position_name', 'ELECTRICIAN')->first()->id;
        $carpenterId = PositionRate::where('position_name', 'CARPENTER')->first()->id;
        $masonId = PositionRate::where('position_name', 'MASON')->first()->id;

        // Create dummy employees
        $employees = [
            // LEADMAN
            [
                'employee_number' => 'EMP001',
                'first_name' => 'Kinglee',
                'last_name' => 'Sajor',
                'position_id' => $leadmanId,
                'basic_salary' => 500.00,
                'date_hired' => '2024-11-25',
            ],
            [
                'employee_number' => 'EMP002',
                'first_name' => 'Raul',
                'last_name' => 'Curaza',
                'position_id' => $leadmanId,
                'basic_salary' => 500.00,
                'date_hired' => '2024-10-25',
            ],
            // H.E. OPERATOR/DRIVER
            [
                'employee_number' => 'EMP003',
                'first_name' => 'Carlos Eugene',
                'last_name' => 'Echavez III',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-09-20',
            ],
            [
                'employee_number' => 'EMP004',
                'first_name' => 'Isabelo',
                'last_name' => 'Clavesillas',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-05',
            ],
            [
                'employee_number' => 'EMP005',
                'first_name' => 'Rey',
                'last_name' => 'Oraye',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-25',
            ],
            [
                'employee_number' => 'EMP006',
                'first_name' => 'William',
                'last_name' => 'Marasigan',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-09-27',
            ],
            [
                'employee_number' => 'EMP007',
                'first_name' => 'Antonio',
                'last_name' => 'Lacorte',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-09-24',
            ],
            [
                'employee_number' => 'EMP008',
                'first_name' => 'Guiliano',
                'last_name' => 'Nazareno',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-25',
            ],
            [
                'employee_number' => 'EMP009',
                'first_name' => 'Wilbert',
                'last_name' => 'Quimno',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-25',
            ],
            [
                'employee_number' => 'EMP010',
                'first_name' => 'Rey',
                'last_name' => 'Balbao',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-26',
            ],
            [
                'employee_number' => 'EMP011',
                'first_name' => 'William',
                'last_name' => 'Laurel Jr.',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-29',
            ],
            [
                'employee_number' => 'EMP012',
                'first_name' => 'Daniel',
                'last_name' => 'Basan',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-11-01',
            ],
            [
                'employee_number' => 'EMP013',
                'first_name' => 'Jhonny Boy',
                'last_name' => 'Basan',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-31',
            ],
            [
                'employee_number' => 'EMP014',
                'first_name' => 'Narciso',
                'last_name' => 'Quebuen',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-11-02',
            ],
            [
                'employee_number' => 'EMP015',
                'first_name' => 'Marlon',
                'last_name' => 'Salazar',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-28',
            ],
            [
                'employee_number' => 'EMP016',
                'first_name' => 'Crislan',
                'last_name' => 'Sempesaw',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-26',
            ],
            [
                'employee_number' => 'EMP017',
                'first_name' => 'Roland',
                'last_name' => 'Mahubay',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-26',
            ],
            [
                'employee_number' => 'EMP018',
                'first_name' => 'Rolly',
                'last_name' => 'Boyboy',
                'position_id' => $operatorId,
                'basic_salary' => 450.00,
                'date_hired' => '2024-10-27',
            ],
            // ELECTRICIAN
            [
                'employee_number' => 'EMP019',
                'first_name' => 'John',
                'last_name' => 'Santos',
                'position_id' => $electricianId,
                'basic_salary' => 480.00,
                'date_hired' => '2024-09-15',
            ],
            [
                'employee_number' => 'EMP020',
                'first_name' => 'Michael',
                'last_name' => 'Cruz',
                'position_id' => $electricianId,
                'basic_salary' => 480.00,
                'date_hired' => '2024-10-10',
            ],
        ];

        foreach ($employees as $emp) {
            Employee::firstOrCreate(
                ['employee_number' => $emp['employee_number']],
                [
                    'first_name' => $emp['first_name'],
                    'last_name' => $emp['last_name'],
                    'position_id' => $emp['position_id'],
                    'basic_salary' => $emp['basic_salary'],
                    'salary_type' => 'daily',
                    'date_hired' => $emp['date_hired'],
                    'activity_status' => 'active',
                    'contract_type' => 'regular',
                    'gender' => 'male',
                    'civil_status' => 'single',
                    'nationality' => 'Filipino',
                ]
            );
        }

        $this->command->info('Dummy employees created successfully!');
        $this->command->info('Total employees: ' . Employee::count());
    }
}
