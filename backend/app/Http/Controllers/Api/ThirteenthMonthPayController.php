<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThirteenthMonthPay;
use App\Models\ThirteenthMonthPayItem;
use App\Models\Employee;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ThirteenthMonthPayController extends Controller
{
    /**
     * Calculate 13th month pay for all active employees
     * Philippine law: Total basic salary for the year / 12
     * First 90,000 is tax-free, excess is taxable
     */
    public function calculate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'period' => 'required|in:full_year,first_half,second_half',
            'payment_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $year = $request->year;
        $period = $request->period;

        // Determine date range based on period
        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($period === 'first_half') {
            $endDate = $year . '-06-30';
        } elseif ($period === 'second_half') {
            $startDate = $year . '-07-01';
        }

        DB::beginTransaction();

        try {
            // Generate batch number
            $lastBatch = ThirteenthMonthPay::whereYear('computation_date', $year)->latest()->first();
            $batchNum = $lastBatch ? intval(substr($lastBatch->batch_number, -4)) + 1 : 1;
            $batchNumber = $year . '-13M-' . str_pad($batchNum, 4, '0', STR_PAD_LEFT);

            // Create main record
            $thirteenthMonth = ThirteenthMonthPay::create([
                'batch_number' => $batchNumber,
                'year' => $year,
                'period' => $period,
                'computation_date' => now(),
                'payment_date' => $request->payment_date,
                'status' => 'computed',
                'computed_by' => auth()->id(),
                'total_amount' => 0,
            ]);

            // Get all active employees
            $employees = Employee::where('employment_status', '!=', 'resigned')
                ->where('employment_status', '!=', 'terminated')
                ->get();

            $totalAmount = 0;

            foreach ($employees as $employee) {
                // Get total basic salary from payroll items for the period
                $basicSalary = PayrollItem::whereHas('payroll', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('pay_date', [$startDate, $endDate])
                        ->where('status', 'paid');
                })
                    ->where('employee_id', $employee->id)
                    ->sum('basic_pay');

                if ($basicSalary <= 0) {
                    continue; // Skip employees with no salary for the period
                }

                // Calculate 13th month pay (basic salary / 12)
                $thirteenthMonthAmount = $basicSalary / 12;

                // Tax-free limit is 90,000 per year (Philippine law)
                $taxFreeLimit = 90000;
                $nonTaxable = min($thirteenthMonthAmount, $taxFreeLimit);
                $taxable = max($thirteenthMonthAmount - $taxFreeLimit, 0);

                // Calculate withholding tax on taxable amount (using annualized rate)
                // Simplified: 0% on first 250k, 20% on 250k-400k, 25% on 400k-800k, etc.
                $withholdingTax = $this->calculateWithholdingTax($taxable);

                $netPay = $thirteenthMonthAmount - $withholdingTax;

                // Create item
                ThirteenthMonthPayItem::create([
                    'thirteenth_month_pay_id' => $thirteenthMonth->id,
                    'employee_id' => $employee->id,
                    'total_basic_salary' => $basicSalary,
                    'taxable_thirteenth_month' => $taxable,
                    'non_taxable_thirteenth_month' => $nonTaxable,
                    'withholding_tax' => $withholdingTax,
                    'net_pay' => $netPay,
                ]);

                $totalAmount += $netPay;
            }

            // Update total amount
            $thirteenthMonth->update(['total_amount' => $totalAmount]);

            DB::commit();

            return response()->json([
                'message' => '13th month pay calculated successfully',
                'batch_number' => $batchNumber,
                'total_employees' => $thirteenthMonth->items()->count(),
                'total_amount' => $totalAmount,
                'thirteenth_month_pay' => $thirteenthMonth->load('items.employee'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to calculate 13th month pay',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all 13th month pay batches
     */
    public function index(Request $request)
    {
        $query = ThirteenthMonthPay::with(['computedBy', 'approvedBy'])
            ->withCount('items');

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(15));
    }

    /**
     * Get specific batch with all items
     */
    public function show($id)
    {
        $thirteenthMonth = ThirteenthMonthPay::with([
            'items.employee.department',
            'items.employee.location',
            'computedBy',
            'approvedBy'
        ])->findOrFail($id);

        return response()->json($thirteenthMonth);
    }

    /**
     * Approve 13th month pay batch
     */
    public function approve($id)
    {
        $thirteenthMonth = ThirteenthMonthPay::findOrFail($id);

        if ($thirteenthMonth->status === 'approved') {
            return response()->json(['message' => '13th month pay already approved'], 400);
        }

        $thirteenthMonth->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => '13th month pay approved successfully',
            'thirteenth_month_pay' => $thirteenthMonth,
        ]);
    }

    /**
     * Mark as paid
     */
    public function markPaid($id)
    {
        $thirteenthMonth = ThirteenthMonthPay::findOrFail($id);

        if ($thirteenthMonth->status !== 'approved') {
            return response()->json(['message' => '13th month pay must be approved first'], 400);
        }

        $thirteenthMonth->update(['status' => 'paid']);

        return response()->json([
            'message' => '13th month pay marked as paid',
            'thirteenth_month_pay' => $thirteenthMonth,
        ]);
    }

    /**
     * Calculate withholding tax (simplified Philippine TRAIN law)
     */
    private function calculateWithholdingTax($taxableAmount)
    {
        if ($taxableAmount <= 0) {
            return 0;
        }

        // Philippine TRAIN law tax brackets (annualized)
        $brackets = [
            ['min' => 0, 'max' => 250000, 'rate' => 0, 'base' => 0],
            ['min' => 250000, 'max' => 400000, 'rate' => 0.20, 'base' => 0],
            ['min' => 400000, 'max' => 800000, 'rate' => 0.25, 'base' => 30000],
            ['min' => 800000, 'max' => 2000000, 'rate' => 0.30, 'base' => 130000],
            ['min' => 2000000, 'max' => 8000000, 'rate' => 0.32, 'base' => 490000],
            ['min' => 8000000, 'max' => PHP_INT_MAX, 'rate' => 0.35, 'base' => 2410000],
        ];

        foreach ($brackets as $bracket) {
            if ($taxableAmount >= $bracket['min'] && $taxableAmount < $bracket['max']) {
                $excess = $taxableAmount - $bracket['min'];
                return $bracket['base'] + ($excess * $bracket['rate']);
            }
        }

        return 0;
    }
}
