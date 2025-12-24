<?php

namespace App\Services\Government;

/**
 * Tax Computation Service (TRAIN Law 2025)
 * Bureau of Internal Revenue Withholding Tax
 */
class TaxComputationService
{
    /**
     * Compute withholding tax based on TRAIN Law
     * Supports annual, semi-monthly, monthly, weekly, daily
     */
    public function computeTax(float $taxableIncome, string $periodType = 'semi-monthly'): float
    {
        // Get tax bracket for the period type
        $bracket = $this->getTaxBracket($taxableIncome, $periodType);

        if (!$bracket) {
            return 0; // Below tax threshold
        }

        // Calculate tax: base_tax + (taxable_income - excess_over) * tax_rate
        $excessIncome = $taxableIncome - $bracket['excess_over'];
        $tax = $bracket['base_tax'] + ($excessIncome * $bracket['tax_rate']);

        return round(max(0, $tax), 2);
    }

    /**
     * Get tax bracket for given taxable income and period
     */
    protected function getTaxBracket(float $taxableIncome, string $periodType): ?array
    {
        $table = $this->getTaxTable($periodType);

        foreach ($table as $bracket) {
            if ($taxableIncome >= $bracket['from'] && 
                ($bracket['to'] === null || $taxableIncome <= $bracket['to'])) {
                return $bracket;
            }
        }

        return null;
    }

    /**
     * Get tax table based on period type (TRAIN Law 2025)
     */
    protected function getTaxTable(string $periodType): array
    {
        switch ($periodType) {
            case 'annual':
                return [
                    ['from' => 0, 'to' => 250000, 'base_tax' => 0, 'tax_rate' => 0, 'excess_over' => 0],
                    ['from' => 250001, 'to' => 400000, 'base_tax' => 0, 'tax_rate' => 0.15, 'excess_over' => 250000],
                    ['from' => 400001, 'to' => 800000, 'base_tax' => 22500, 'tax_rate' => 0.20, 'excess_over' => 400000],
                    ['from' => 800001, 'to' => 2000000, 'base_tax' => 102500, 'tax_rate' => 0.25, 'excess_over' => 800000],
                    ['from' => 2000001, 'to' => 8000000, 'base_tax' => 402500, 'tax_rate' => 0.30, 'excess_over' => 2000000],
                    ['from' => 8000001, 'to' => null, 'base_tax' => 2202500, 'tax_rate' => 0.35, 'excess_over' => 8000000],
                ];

            case 'semi-monthly':
                return [
                    ['from' => 0, 'to' => 10416.67, 'base_tax' => 0, 'tax_rate' => 0, 'excess_over' => 0],
                    ['from' => 10416.68, 'to' => 16666.67, 'base_tax' => 0, 'tax_rate' => 0.15, 'excess_over' => 10416.67],
                    ['from' => 16666.68, 'to' => 33333.33, 'base_tax' => 937.50, 'tax_rate' => 0.20, 'excess_over' => 16666.67],
                    ['from' => 33333.34, 'to' => 83333.33, 'base_tax' => 4270.83, 'tax_rate' => 0.25, 'excess_over' => 33333.33],
                    ['from' => 83333.34, 'to' => 333333.33, 'base_tax' => 16770.83, 'tax_rate' => 0.30, 'excess_over' => 83333.33],
                    ['from' => 333333.34, 'to' => null, 'base_tax' => 91770.83, 'tax_rate' => 0.35, 'excess_over' => 333333.33],
                ];

            case 'monthly':
                return [
                    ['from' => 0, 'to' => 20833, 'base_tax' => 0, 'tax_rate' => 0, 'excess_over' => 0],
                    ['from' => 20834, 'to' => 33333, 'base_tax' => 0, 'tax_rate' => 0.15, 'excess_over' => 20833],
                    ['from' => 33334, 'to' => 66667, 'base_tax' => 1875.00, 'tax_rate' => 0.20, 'excess_over' => 33333],
                    ['from' => 66668, 'to' => 166667, 'base_tax' => 8541.80, 'tax_rate' => 0.25, 'excess_over' => 66667],
                    ['from' => 166668, 'to' => 666667, 'base_tax' => 33541.80, 'tax_rate' => 0.30, 'excess_over' => 166667],
                    ['from' => 666668, 'to' => null, 'base_tax' => 183541.80, 'tax_rate' => 0.35, 'excess_over' => 666667],
                ];

            case 'weekly':
                return [
                    ['from' => 0, 'to' => 4807.69, 'base_tax' => 0, 'tax_rate' => 0, 'excess_over' => 0],
                    ['from' => 4807.70, 'to' => 7692.31, 'base_tax' => 0, 'tax_rate' => 0.15, 'excess_over' => 4807.69],
                    ['from' => 7692.32, 'to' => 15384.62, 'base_tax' => 432.69, 'tax_rate' => 0.20, 'excess_over' => 7692.31],
                    ['from' => 15384.63, 'to' => 38461.54, 'base_tax' => 1971.15, 'tax_rate' => 0.25, 'excess_over' => 15384.62],
                    ['from' => 38461.55, 'to' => 153846.15, 'base_tax' => 7740.38, 'tax_rate' => 0.30, 'excess_over' => 38461.54],
                    ['from' => 153846.16, 'to' => null, 'base_tax' => 42355.77, 'tax_rate' => 0.35, 'excess_over' => 153846.15],
                ];

            case 'daily':
                return [
                    ['from' => 0, 'to' => 685.71, 'base_tax' => 0, 'tax_rate' => 0, 'excess_over' => 0],
                    ['from' => 685.72, 'to' => 1095.89, 'base_tax' => 0, 'tax_rate' => 0.15, 'excess_over' => 685.71],
                    ['from' => 1095.90, 'to' => 2191.78, 'base_tax' => 61.53, 'tax_rate' => 0.20, 'excess_over' => 1095.89],
                    ['from' => 2191.79, 'to' => 5479.45, 'base_tax' => 280.71, 'tax_rate' => 0.25, 'excess_over' => 2191.78],
                    ['from' => 5479.46, 'to' => 21917.81, 'base_tax' => 1102.63, 'tax_rate' => 0.30, 'excess_over' => 5479.45],
                    ['from' => 21917.82, 'to' => null, 'base_tax' => 6034.14, 'tax_rate' => 0.35, 'excess_over' => 21917.81],
                ];

            default:
                return $this->getTaxTable('semi-monthly');
        }
    }

    /**
     * Compute annual tax for reference
     */
    public function computeAnnualTax(float $annualTaxableIncome): float
    {
        return $this->computeTax($annualTaxableIncome, 'annual');
    }

    /**
     * Convert annual taxable income to semi-monthly tax
     */
    public function annualToSemiMonthly(float $annualTaxableIncome): float
    {
        $annualTax = $this->computeAnnualTax($annualTaxableIncome);
        return round($annualTax / 24, 2);
    }

    /**
     * Get tax bracket information for display
     */
    public function getTaxBracketInfo(float $taxableIncome, string $periodType = 'semi-monthly'): array
    {
        $bracket = $this->getTaxBracket($taxableIncome, $periodType);
        
        if (!$bracket) {
            return [
                'bracket' => 1,
                'rate' => 0,
                'description' => 'Tax-exempt (below ₱250,000/year)',
            ];
        }

        $rate = $bracket['tax_rate'] * 100;
        return [
            'bracket' => $this->determineBracketNumber($bracket, $periodType),
            'rate' => $rate,
            'description' => "Tax bracket: {$rate}%",
        ];
    }

    protected function determineBracketNumber(array $bracket, string $periodType): int
    {
        $table = $this->getTaxTable($periodType);
        return array_search($bracket, $table) + 1;
    }
}
