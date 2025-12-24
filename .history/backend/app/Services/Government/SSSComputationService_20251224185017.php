<?php

namespace App\Services\Government;

/**
 * SSS Computation Service (2025 Rates)
 * Based on Philippine Social Security System contribution table
 */
class SSSComputationService
{
    /**
     * Compute SSS contribution based on monthly salary
     * Returns employee share, employer share, and total
     */
    public function computeContribution(float $monthlySalary): array
    {
        // 2025 SSS Contribution Table (simplified - should be loaded from database)
        $table = $this->getContributionTable();

        $bracket = null;
        foreach ($table as $row) {
            if ($monthlySalary >= $row['from'] && ($row['to'] === null || $monthlySalary <= $row['to'])) {
                $bracket = $row;
                break;
            }
        }

        if (!$bracket) {
            // Use highest bracket
            $bracket = end($table);
        }

        return [
            'monthly_salary_credit' => $bracket['msc'],
            'employee_share' => $bracket['employee'],
            'employer_share' => $bracket['employer'],
            'employer_ec' => 10.00, // Fixed ₱10 Employee Compensation
            'total' => $bracket['total'],
        ];
    }

    /**
     * Get SSS contribution table (2025 rates)
     * In production, this should be loaded from database
     */
    protected function getContributionTable(): array
    {
        return [
            ['from' => 0, 'to' => 4249.99, 'msc' => 4000, 'employee' => 180.00, 'employer' => 330.00, 'total' => 510.00],
            ['from' => 4250, 'to' => 4749.99, 'msc' => 4500, 'employee' => 202.50, 'employer' => 371.25, 'total' => 573.75],
            ['from' => 4750, 'to' => 5249.99, 'msc' => 5000, 'employee' => 225.00, 'employer' => 412.50, 'total' => 637.50],
            ['from' => 5250, 'to' => 5749.99, 'msc' => 5500, 'employee' => 247.50, 'employer' => 453.75, 'total' => 701.25],
            ['from' => 5750, 'to' => 6249.99, 'msc' => 6000, 'employee' => 270.00, 'employer' => 495.00, 'total' => 765.00],
            ['from' => 6250, 'to' => 6749.99, 'msc' => 6500, 'employee' => 292.50, 'employer' => 536.25, 'total' => 828.75],
            ['from' => 6750, 'to' => 7249.99, 'msc' => 7000, 'employee' => 315.00, 'employer' => 577.50, 'total' => 892.50],
            ['from' => 7250, 'to' => 7749.99, 'msc' => 7500, 'employee' => 337.50, 'employer' => 618.75, 'total' => 956.25],
            ['from' => 7750, 'to' => 8249.99, 'msc' => 8000, 'employee' => 360.00, 'employer' => 660.00, 'total' => 1020.00],
            ['from' => 8250, 'to' => 8749.99, 'msc' => 8500, 'employee' => 382.50, 'employer' => 701.25, 'total' => 1083.75],
            ['from' => 8750, 'to' => 9249.99, 'msc' => 9000, 'employee' => 405.00, 'employer' => 742.50, 'total' => 1147.50],
            ['from' => 9250, 'to' => 9749.99, 'msc' => 9500, 'employee' => 427.50, 'employer' => 783.75, 'total' => 1211.25],
            ['from' => 9750, 'to' => 10249.99, 'msc' => 10000, 'employee' => 450.00, 'employer' => 825.00, 'total' => 1275.00],
            ['from' => 10250, 'to' => 10749.99, 'msc' => 10500, 'employee' => 472.50, 'employer' => 866.25, 'total' => 1338.75],
            ['from' => 10750, 'to' => 11249.99, 'msc' => 11000, 'employee' => 495.00, 'employer' => 907.50, 'total' => 1402.50],
            ['from' => 11250, 'to' => 11749.99, 'msc' => 11500, 'employee' => 517.50, 'employer' => 948.75, 'total' => 1466.25],
            ['from' => 11750, 'to' => 12249.99, 'msc' => 12000, 'employee' => 540.00, 'employer' => 990.00, 'total' => 1530.00],
            ['from' => 12250, 'to' => 12749.99, 'msc' => 12500, 'employee' => 562.50, 'employer' => 1031.25, 'total' => 1593.75],
            ['from' => 12750, 'to' => 13249.99, 'msc' => 13000, 'employee' => 585.00, 'employer' => 1072.50, 'total' => 1657.50],
            ['from' => 13250, 'to' => 13749.99, 'msc' => 13500, 'employee' => 607.50, 'employer' => 1113.75, 'total' => 1721.25],
            ['from' => 13750, 'to' => 14249.99, 'msc' => 14000, 'employee' => 630.00, 'employer' => 1155.00, 'total' => 1785.00],
            ['from' => 14250, 'to' => 14749.99, 'msc' => 14500, 'employee' => 652.50, 'employer' => 1196.25, 'total' => 1848.75],
            ['from' => 14750, 'to' => 15249.99, 'msc' => 15000, 'employee' => 675.00, 'employer' => 1237.50, 'total' => 1912.50],
            ['from' => 15250, 'to' => 15749.99, 'msc' => 15500, 'employee' => 697.50, 'employer' => 1278.75, 'total' => 1976.25],
            ['from' => 15750, 'to' => 16249.99, 'msc' => 16000, 'employee' => 720.00, 'employer' => 1320.00, 'total' => 2040.00],
            ['from' => 16250, 'to' => 16749.99, 'msc' => 16500, 'employee' => 742.50, 'employer' => 1361.25, 'total' => 2103.75],
            ['from' => 16750, 'to' => 17249.99, 'msc' => 17000, 'employee' => 765.00, 'employer' => 1402.50, 'total' => 2167.50],
            ['from' => 17250, 'to' => 17749.99, 'msc' => 17500, 'employee' => 787.50, 'employer' => 1443.75, 'total' => 2231.25],
            ['from' => 17750, 'to' => 18249.99, 'msc' => 18000, 'employee' => 810.00, 'employer' => 1485.00, 'total' => 2295.00],
            ['from' => 18250, 'to' => 18749.99, 'msc' => 18500, 'employee' => 832.50, 'employer' => 1526.25, 'total' => 2358.75],
            ['from' => 18750, 'to' => 19249.99, 'msc' => 19000, 'employee' => 855.00, 'employer' => 1567.50, 'total' => 2422.50],
            ['from' => 19250, 'to' => 19749.99, 'msc' => 19500, 'employee' => 877.50, 'employer' => 1608.75, 'total' => 2486.25],
            ['from' => 19750, 'to' => null, 'msc' => 20000, 'employee' => 900.00, 'employer' => 1650.00, 'total' => 2550.00],
        ];
    }
}
