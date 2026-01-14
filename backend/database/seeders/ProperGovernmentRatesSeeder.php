<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProperGovernmentRatesSeeder extends Seeder
{
    /**
     * Seed proper 2024 Philippine Government Contribution Rates
     * Based on official SSS, PhilHealth, and Pag-IBIG tables
     */
    public function run(): void
    {
        // Clear existing rates (optional)
        DB::table('government_rates')->truncate();

        $effectiveDate = Carbon::parse('2024-01-01');

        // SSS Contribution Table 2024 (53 brackets)
        $sssRates = [
            ['min' => 4000.00, 'max' => 4249.99, 'ee' => 180.00, 'er' => 730.00, 'total' => 910.00],
            ['min' => 4250.00, 'max' => 4749.99, 'ee' => 191.25, 'er' => 776.25, 'total' => 967.50],
            ['min' => 4750.00, 'max' => 5249.99, 'ee' => 202.50, 'er' => 822.50, 'total' => 1025.00],
            ['min' => 5250.00, 'max' => 5749.99, 'ee' => 213.75, 'er' => 868.75, 'total' => 1082.50],
            ['min' => 5750.00, 'max' => 6249.99, 'ee' => 225.00, 'er' => 915.00, 'total' => 1140.00],
            ['min' => 6250.00, 'max' => 6749.99, 'ee' => 236.25, 'er' => 961.25, 'total' => 1197.50],
            ['min' => 6750.00, 'max' => 7249.99, 'ee' => 247.50, 'er' => 1007.50, 'total' => 1255.00],
            ['min' => 7250.00, 'max' => 7749.99, 'ee' => 258.75, 'er' => 1053.75, 'total' => 1312.50],
            ['min' => 7750.00, 'max' => 8249.99, 'ee' => 270.00, 'er' => 1100.00, 'total' => 1370.00],
            ['min' => 8250.00, 'max' => 8749.99, 'ee' => 281.25, 'er' => 1146.25, 'total' => 1427.50],
            ['min' => 8750.00, 'max' => 9249.99, 'ee' => 292.50, 'er' => 1192.50, 'total' => 1485.00],
            ['min' => 9250.00, 'max' => 9749.99, 'ee' => 303.75, 'er' => 1238.75, 'total' => 1542.50],
            ['min' => 9750.00, 'max' => 10249.99, 'ee' => 315.00, 'er' => 1285.00, 'total' => 1600.00],
            ['min' => 10250.00, 'max' => 10749.99, 'ee' => 326.25, 'er' => 1331.25, 'total' => 1657.50],
            ['min' => 10750.00, 'max' => 11249.99, 'ee' => 337.50, 'er' => 1377.50, 'total' => 1715.00],
            ['min' => 11250.00, 'max' => 11749.99, 'ee' => 348.75, 'er' => 1423.75, 'total' => 1772.50],
            ['min' => 11750.00, 'max' => 12249.99, 'ee' => 360.00, 'er' => 1470.00, 'total' => 1830.00],
            ['min' => 12250.00, 'max' => 12749.99, 'ee' => 371.25, 'er' => 1516.25, 'total' => 1887.50],
            ['min' => 12750.00, 'max' => 13249.99, 'ee' => 382.50, 'er' => 1562.50, 'total' => 1945.00],
            ['min' => 13250.00, 'max' => 13749.99, 'ee' => 393.75, 'er' => 1608.75, 'total' => 2002.50],
            ['min' => 13750.00, 'max' => 14249.99, 'ee' => 405.00, 'er' => 1655.00, 'total' => 2060.00],
            ['min' => 14250.00, 'max' => 14749.99, 'ee' => 416.25, 'er' => 1701.25, 'total' => 2117.50],
            ['min' => 14750.00, 'max' => 15249.99, 'ee' => 427.50, 'er' => 1747.50, 'total' => 2175.00],
            ['min' => 15250.00, 'max' => 15749.99, 'ee' => 438.75, 'er' => 1793.75, 'total' => 2232.50],
            ['min' => 15750.00, 'max' => 16249.99, 'ee' => 450.00, 'er' => 1840.00, 'total' => 2290.00],
            ['min' => 16250.00, 'max' => 16749.99, 'ee' => 461.25, 'er' => 1886.25, 'total' => 2347.50],
            ['min' => 16750.00, 'max' => 17249.99, 'ee' => 472.50, 'er' => 1932.50, 'total' => 2405.00],
            ['min' => 17250.00, 'max' => 17749.99, 'ee' => 483.75, 'er' => 1978.75, 'total' => 2462.50],
            ['min' => 17750.00, 'max' => 18249.99, 'ee' => 495.00, 'er' => 2025.00, 'total' => 2520.00],
            ['min' => 18250.00, 'max' => 18749.99, 'ee' => 506.25, 'er' => 2071.25, 'total' => 2577.50],
            ['min' => 18750.00, 'max' => 19249.99, 'ee' => 517.50, 'er' => 2117.50, 'total' => 2635.00],
            ['min' => 19250.00, 'max' => 19749.99, 'ee' => 528.75, 'er' => 2163.75, 'total' => 2692.50],
            ['min' => 19750.00, 'max' => 20249.99, 'ee' => 540.00, 'er' => 2210.00, 'total' => 2750.00],
            ['min' => 20250.00, 'max' => 20749.99, 'ee' => 551.25, 'er' => 2256.25, 'total' => 2807.50],
            ['min' => 20750.00, 'max' => 21249.99, 'ee' => 562.50, 'er' => 2302.50, 'total' => 2865.00],
            ['min' => 21250.00, 'max' => 21749.99, 'ee' => 573.75, 'er' => 2348.75, 'total' => 2922.50],
            ['min' => 21750.00, 'max' => 22249.99, 'ee' => 585.00, 'er' => 2395.00, 'total' => 2980.00],
            ['min' => 22250.00, 'max' => 22749.99, 'ee' => 596.25, 'er' => 2441.25, 'total' => 3037.50],
            ['min' => 22750.00, 'max' => 23249.99, 'ee' => 607.50, 'er' => 2487.50, 'total' => 3095.00],
            ['min' => 23250.00, 'max' => 23749.99, 'ee' => 618.75, 'er' => 2533.75, 'total' => 3152.50],
            ['min' => 23750.00, 'max' => 24249.99, 'ee' => 630.00, 'er' => 2580.00, 'total' => 3210.00],
            ['min' => 24250.00, 'max' => 24749.99, 'ee' => 641.25, 'er' => 2626.25, 'total' => 3267.50],
            ['min' => 24750.00, 'max' => 25249.99, 'ee' => 652.50, 'er' => 2672.50, 'total' => 3325.00],
            ['min' => 25250.00, 'max' => 25749.99, 'ee' => 663.75, 'er' => 2718.75, 'total' => 3382.50],
            ['min' => 25750.00, 'max' => 26249.99, 'ee' => 675.00, 'er' => 2765.00, 'total' => 3440.00],
            ['min' => 26250.00, 'max' => 26749.99, 'ee' => 686.25, 'er' => 2811.25, 'total' => 3497.50],
            ['min' => 26750.00, 'max' => 27249.99, 'ee' => 697.50, 'er' => 2857.50, 'total' => 3555.00],
            ['min' => 27250.00, 'max' => 27749.99, 'ee' => 708.75, 'er' => 2903.75, 'total' => 3612.50],
            ['min' => 27750.00, 'max' => 28249.99, 'ee' => 720.00, 'er' => 2950.00, 'total' => 3670.00],
            ['min' => 28250.00, 'max' => 28749.99, 'ee' => 731.25, 'er' => 2996.25, 'total' => 3727.50],
            ['min' => 28750.00, 'max' => 29249.99, 'ee' => 742.50, 'er' => 3042.50, 'total' => 3785.00],
            ['min' => 29250.00, 'max' => 29749.99, 'ee' => 753.75, 'er' => 3088.75, 'total' => 3842.50],
            ['min' => 29750.00, 'max' => null, 'ee' => 765.00, 'er' => 3135.00, 'total' => 3900.00], // No upper limit
        ];

        foreach ($sssRates as $index => $rate) {
            DB::table('government_rates')->insert([
                'type' => 'sss',
                'name' => 'SSS ' . number_format($rate['min'], 2) . ($rate['max'] ? ' - ' . number_format($rate['max'], 2) : '+'),
                'min_salary' => $rate['min'],
                'max_salary' => $rate['max'],
                'employee_rate' => null,
                'employee_fixed' => $rate['ee'],
                'employer_rate' => null,
                'employer_fixed' => $rate['er'],
                'total_contribution' => $rate['total'],
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'sort_order' => $index + 1,
                'notes' => 'Official SSS contribution table 2024',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // PhilHealth Contribution Table 2024
        $philhealthRates = [
            ['min' => 0.00, 'max' => 10000.00, 'premium_rate' => 5.00, 'ee' => 225.00, 'er' => 225.00],
            ['min' => 10000.01, 'max' => 99999.99, 'premium_rate' => 5.00, 'ee_percent' => 2.50, 'er_percent' => 2.50],
            ['min' => 100000.00, 'max' => null, 'premium_rate' => 5.00, 'ee' => 5000.00, 'er' => 5000.00],
        ];

        foreach ($philhealthRates as $index => $rate) {
            DB::table('government_rates')->insert([
                'type' => 'philhealth',
                'name' => 'PhilHealth ' . number_format($rate['min'], 2) . ($rate['max'] ? ' - ' . number_format($rate['max'], 2) : '+'),
                'min_salary' => $rate['min'],
                'max_salary' => $rate['max'],
                'employee_rate' => $rate['ee_percent'] ?? null,
                'employee_fixed' => $rate['ee'] ?? null,
                'employer_rate' => $rate['er_percent'] ?? null,
                'employer_fixed' => $rate['er'] ?? null,
                'total_contribution' => null,
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'sort_order' => $index + 1,
                'notes' => 'PhilHealth 5% premium rate (2.5% employee, 2.5% employer)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Pag-IBIG Contribution Table 2024
        $pagibigRates = [
            ['min' => 0.00, 'max' => 1500.00, 'ee_percent' => 1.00, 'er_percent' => 2.00],
            ['min' => 1500.01, 'max' => 4999.99, 'ee_percent' => 2.00, 'er_percent' => 2.00],
            ['min' => 5000.00, 'max' => null, 'ee_fixed' => 100.00, 'er_fixed' => 100.00],
        ];

        foreach ($pagibigRates as $index => $rate) {
            DB::table('government_rates')->insert([
                'type' => 'pagibig',
                'name' => 'Pag-IBIG ' . number_format($rate['min'], 2) . ($rate['max'] ? ' - ' . number_format($rate['max'], 2) : '+'),
                'min_salary' => $rate['min'],
                'max_salary' => $rate['max'],
                'employee_rate' => $rate['ee_percent'] ?? null,
                'employee_fixed' => $rate['ee_fixed'] ?? null,
                'employer_rate' => $rate['er_percent'] ?? null,
                'employer_fixed' => $rate['er_fixed'] ?? null,
                'total_contribution' => null,
                'effective_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'sort_order' => $index + 1,
                'notes' => 'Pag-IBIG HDMF contribution rates 2024',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Seeded 53 SSS brackets');
        $this->command->info('✓ Seeded 3 PhilHealth brackets');
        $this->command->info('✓ Seeded 3 Pag-IBIG brackets');
        $this->command->info('Total: 59 government contribution rates');
    }
}
