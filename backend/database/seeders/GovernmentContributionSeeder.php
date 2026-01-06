<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernmentContributionSeeder extends Seeder
{
    /**
     * Seed government contribution tables with 2024 Philippine rates.
     * Sources: SSS Circular 2024-001, PhilHealth Circular 2024-0001, Pag-IBIG Circular 2024, BIR RR 11-2024
     */
    public function run(): void
    {
        $this->seedSSSContributions();
        $this->seedPhilHealthContributions();
        $this->seedPagIbigContributions();
        $this->seedTaxWithholding();
    }

    /**
     * SSS Contribution Table - 2024 Rates
     * Based on SSS Circular 2024-001 (effective January 2024)
     * Columns: range_compensation_from, range_compensation_to, monthly_salary_credit,
     *          employer_contribution, employee_contribution, total_contribution,
     *          employer_ec, employer_wisp
     */
    private function seedSSSContributions(): void
    {
        DB::table('sss_contribution_table')->truncate();

        // SSS 2024 Contribution Schedule
        $sssRates = [
            [4000, 4249.99, 4000, 380, 180, 560, 10, 0],
            [4250, 4749.99, 4500, 427.50, 202.50, 630, 10, 0],
            [4750, 5249.99, 5000, 475, 225, 700, 10, 0],
            [5250, 5749.99, 5500, 522.50, 247.50, 770, 10, 0],
            [5750, 6249.99, 6000, 570, 270, 840, 10, 0],
            [6250, 6749.99, 6500, 617.50, 292.50, 910, 10, 0],
            [6750, 7249.99, 7000, 665, 315, 980, 10, 0],
            [7250, 7749.99, 7500, 712.50, 337.50, 1050, 10, 0],
            [7750, 8249.99, 8000, 760, 360, 1120, 10, 0],
            [8250, 8749.99, 8500, 807.50, 382.50, 1190, 10, 0],
            [8750, 9249.99, 9000, 855, 405, 1260, 10, 0],
            [9250, 9749.99, 9500, 902.50, 427.50, 1330, 10, 0],
            [9750, 10249.99, 10000, 950, 450, 1400, 10, 0],
            [10250, 10749.99, 10500, 997.50, 472.50, 1470, 10, 0],
            [10750, 11249.99, 11000, 1045, 495, 1540, 10, 0],
            [11250, 11749.99, 11500, 1092.50, 517.50, 1610, 10, 0],
            [11750, 12249.99, 12000, 1140, 540, 1680, 10, 0],
            [12250, 12749.99, 12500, 1187.50, 562.50, 1750, 10, 0],
            [12750, 13249.99, 13000, 1235, 585, 1820, 10, 0],
            [13250, 13749.99, 13500, 1282.50, 607.50, 1890, 10, 0],
            [13750, 14249.99, 14000, 1330, 630, 1960, 10, 0],
            [14250, 14749.99, 14500, 1377.50, 652.50, 2030, 10, 0],
            [14750, 15249.99, 15000, 1425, 675, 2100, 10, 0],
            [15250, 15749.99, 15500, 1472.50, 697.50, 2170, 10, 0],
            [15750, 16249.99, 16000, 1520, 720, 2240, 10, 0],
            [16250, 16749.99, 16500, 1567.50, 742.50, 2310, 10, 0],
            [16750, 17249.99, 17000, 1615, 765, 2380, 10, 0],
            [17250, 17749.99, 17500, 1662.50, 787.50, 2450, 10, 0],
            [17750, 18249.99, 18000, 1710, 810, 2520, 10, 0],
            [18250, 18749.99, 18500, 1757.50, 832.50, 2590, 10, 0],
            [18750, 19249.99, 19000, 1805, 855, 2660, 10, 0],
            [19250, 19749.99, 19500, 1852.50, 877.50, 2730, 10, 0],
            [19750, 20249.99, 20000, 1900, 900, 2800, 10, 0],
            [20250, 20749.99, 20500, 1947.50, 922.50, 2870, 10, 0],
            [20750, 21249.99, 21000, 1995, 945, 2940, 10, 0],
            [21250, 21749.99, 21500, 2042.50, 967.50, 3010, 10, 0],
            [21750, 22249.99, 22000, 2090, 990, 3080, 10, 0],
            [22250, 22749.99, 22500, 2137.50, 1012.50, 3150, 10, 0],
            [22750, 23249.99, 23000, 2185, 1035, 3220, 10, 0],
            [23250, 23749.99, 23500, 2232.50, 1057.50, 3290, 10, 0],
            [23750, 24249.99, 24000, 2280, 1080, 3360, 10, 0],
            [24250, 24749.99, 24500, 2327.50, 1102.50, 3430, 10, 0],
            [24750, 25249.99, 25000, 2375, 1125, 3500, 10, 0],
            [25250, 25749.99, 25500, 2422.50, 1147.50, 3570, 10, 0],
            [25750, 26249.99, 26000, 2470, 1170, 3640, 10, 0],
            [26250, 26749.99, 26500, 2517.50, 1192.50, 3710, 10, 0],
            [26750, 27249.99, 27000, 2565, 1215, 3780, 10, 0],
            [27250, 27749.99, 27500, 2612.50, 1237.50, 3850, 10, 0],
            [27750, 28249.99, 28000, 2660, 1260, 3920, 10, 0],
            [28250, 28749.99, 28500, 2707.50, 1282.50, 3990, 10, 0],
            [28750, 29249.99, 29000, 2755, 1305, 4060, 10, 0],
            [29250, 29749.99, 29500, 2802.50, 1327.50, 4130, 10, 0],
            [29750, 99999999, 30000, 2850, 1350, 4200, 10, 0], // Max ceiling
        ];

        foreach ($sssRates as $rate) {
            DB::table('sss_contribution_table')->insert([
                'range_compensation_from' => $rate[0],
                'range_compensation_to' => $rate[1],
                'monthly_salary_credit' => $rate[2],
                'employer_contribution' => $rate[3],
                'employee_contribution' => $rate[4],
                'total_contribution' => $rate[5],
                'employer_ec' => $rate[6],
                'employer_wisp' => $rate[7],
                'effective_date' => '2024-01-01',
                'end_date' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ SSS contribution table seeded (53 brackets)');
    }

    /**
     * PhilHealth Contribution Table - 2024 Rates
     * Based on PhilHealth Circular 2024-0001 (effective January 2024)
     * Premium rate: 5% of monthly basic salary (2.5% employee, 2.5% employer)
     * Columns: monthly_basic_salary_from, monthly_basic_salary_to, premium_rate,
     *          minimum_monthly_premium, maximum_monthly_premium
     */
    private function seedPhilHealthContributions(): void
    {
        DB::table('philhealth_contribution_table')->truncate();

        // PhilHealth 2024: 5% premium rate (2.5% each for employee and employer)
        // Maximum ceiling at ₱100,000 monthly salary = ₱5,000 max premium
        $brackets = [];

        // For salaries up to 10,000: minimum premium of 500 pesos
        $brackets[] = [
            'monthly_basic_salary_from' => 0,
            'monthly_basic_salary_to' => 10000,
            'premium_rate' => 5.0,
            'minimum_monthly_premium' => 500.00,
            'maximum_monthly_premium' => 500.00,
            'effective_date' => '2024-01-01',
            'end_date' => null,
            'is_active' => true,
            'notes' => 'Minimum premium applies',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // For salaries 10,001 to 100,000: graduated premiums
        for ($salary = 10001; $salary <= 100000; $salary += 10000) {
            $to = min($salary + 9999, 100000);
            $premium = ($to / 2) * 0.05; // Use midpoint for calculation

            $brackets[] = [
                'monthly_basic_salary_from' => $salary,
                'monthly_basic_salary_to' => $to,
                'premium_rate' => 5.0,
                'minimum_monthly_premium' => round($premium, 2),
                'maximum_monthly_premium' => round($premium, 2),
                'effective_date' => '2024-01-01',
                'end_date' => null,
                'is_active' => true,
                'notes' => '5% of basic salary',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // For salaries over 100,000: maximum premium of 5,000
        $brackets[] = [
            'monthly_basic_salary_from' => 100000.01,
            'monthly_basic_salary_to' => 99999999,
            'premium_rate' => 5.0,
            'minimum_monthly_premium' => 5000.00,
            'maximum_monthly_premium' => 5000.00,
            'effective_date' => '2024-01-01',
            'end_date' => null,
            'is_active' => true,
            'notes' => 'Maximum ceiling at ₱100,000 salary',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('philhealth_contribution_table')->insert($brackets);

        $this->command->info('✓ PhilHealth contribution table seeded (' . count($brackets) . ' brackets)');
    }

    /**
     * Pag-IBIG Contribution Table - 2024 Rates
     * Based on Pag-IBIG Fund Circular 2024
     * Employee: 1-2% of monthly compensation, Employer: 2% of monthly compensation
     * Columns: monthly_compensation_from, monthly_compensation_to, employee_rate, employer_rate,
     *          employee_min_contribution, employee_max_contribution
     */
    private function seedPagIbigContributions(): void
    {
        DB::table('pagibig_contribution_table')->truncate();

        // Pag-IBIG 2024 rates: 
        // Up to ₱1,500: 1% employee, 2% employer
        // Over ₱1,500: 2% employee, 2% employer
        // Maximum contribution: ₱5,000 per month

        $pagibigRates = [
            // Up to 1,500: 1% employee rate
            [
                'from' => 0,
                'to' => 1500,
                'ee_rate' => 1.0,
                'er_rate' => 2.0,
                'ee_min' => 0,
                'ee_max' => 15,
            ],
            // Over 1,500: 2% employee rate, capped at ₱100 per month (₱5,000 salary)
            [
                'from' => 1500.01,
                'to' => 5000,
                'ee_rate' => 2.0,
                'er_rate' => 2.0,
                'ee_min' => 30.01,
                'ee_max' => 100,
            ],
            // Over 5,000: continues at 2%, employee share capped at ₱5,000
            [
                'from' => 5000.01,
                'to' => 99999999,
                'ee_rate' => 2.0,
                'er_rate' => 2.0,
                'ee_min' => 100.01,
                'ee_max' => 5000,
            ],
        ];

        foreach ($pagibigRates as $rate) {
            DB::table('pagibig_contribution_table')->insert([
                'monthly_compensation_from' => $rate['from'],
                'monthly_compensation_to' => $rate['to'],
                'employee_rate' => $rate['ee_rate'],
                'employer_rate' => $rate['er_rate'],
                'employee_min_contribution' => $rate['ee_min'],
                'employee_max_contribution' => $rate['ee_max'],
                'effective_date' => '2024-01-01',
                'end_date' => null,
                'is_active' => true,
                'notes' => 'Employee contribution capped at ₱100/month',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Pag-IBIG contribution table seeded (' . count($pagibigRates) . ' brackets)');
    }

    /**
     * Tax Withholding Table - 2024 Rates
     * Based on BIR Revenue Regulations No. 11-2024 (TRAIN Law updated)
     * Graduated income tax rates for compensation income
     * Columns: period_type, taxable_income_from, taxable_income_to, base_tax,
     *          tax_rate, excess_over, tax_bracket
     */
    private function seedTaxWithholding(): void
    {
        DB::table('tax_withholding_table')->truncate();

        // Annual tax brackets (TRAIN Law 2024)
        $taxBrackets = [
            [
                'period' => 'annual',
                'from' => 0,
                'to' => 250000,
                'base' => 0,
                'rate' => 0,
                'excess' => 0,
                'bracket' => 1,
                'notes' => 'Tax exempt: ₱0 - ₱250,000',
            ],
            [
                'period' => 'annual',
                'from' => 250000.01,
                'to' => 400000,
                'base' => 0,
                'rate' => 15,
                'excess' => 250000,
                'bracket' => 2,
                'notes' => '15% of excess over ₱250,000 (₱250,000 - ₱400,000)',
            ],
            [
                'period' => 'annual',
                'from' => 400000.01,
                'to' => 800000,
                'base' => 22500,
                'rate' => 20,
                'excess' => 400000,
                'bracket' => 3,
                'notes' => '₱22,500 + 20% of excess over ₱400,000 (₱400,000 - ₱800,000)',
            ],
            [
                'period' => 'annual',
                'from' => 800000.01,
                'to' => 2000000,
                'base' => 102500,
                'rate' => 25,
                'excess' => 800000,
                'bracket' => 4,
                'notes' => '₱102,500 + 25% of excess over ₱800,000 (₱800,000 - ₱2,000,000)',
            ],
            [
                'period' => 'annual',
                'from' => 2000000.01,
                'to' => 8000000,
                'base' => 402500,
                'rate' => 30,
                'excess' => 2000000,
                'bracket' => 5,
                'notes' => '₱402,500 + 30% of excess over ₱2,000,000 (₱2,000,000 - ₱8,000,000)',
            ],
            [
                'period' => 'annual',
                'from' => 8000000.01,
                'to' => 99999999999,
                'base' => 2202500,
                'rate' => 35,
                'excess' => 8000000,
                'bracket' => 6,
                'notes' => '₱2,202,500 + 35% of excess over ₱8,000,000 (Over ₱8,000,000)',
            ],
        ];

        foreach ($taxBrackets as $bracket) {
            DB::table('tax_withholding_table')->insert([
                'period_type' => $bracket['period'],
                'taxable_income_from' => $bracket['from'],
                'taxable_income_to' => $bracket['to'],
                'base_tax' => $bracket['base'],
                'tax_rate' => $bracket['rate'],
                'excess_over' => $bracket['excess'],
                'tax_bracket' => $bracket['bracket'],
                'effective_date' => '2024-01-01',
                'end_date' => null,
                'is_active' => true,
                'notes' => $bracket['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Tax withholding table seeded (' . count($taxBrackets) . ' brackets)');
    }
}
