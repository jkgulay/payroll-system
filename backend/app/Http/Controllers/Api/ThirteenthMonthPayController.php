<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThirteenthMonthPay;
use App\Models\ThirteenthMonthPayItem;
use App\Models\Employee;
use App\Models\Project;
use App\Models\EmployeeLoan;
use App\Models\PayrollItem;
use App\Models\CompanyInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class ThirteenthMonthPayController extends Controller
{
    /**
     * Calculate 13th month pay for all active employees
     * Philippine law: Total basic salary for the year / 12
     * First 90,000 is tax-free, excess is taxable
     */
    public function calculate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
                'period' => 'required|in:full_year,first_half,second_half',
                'payment_date' => 'required|date',
                'department' => 'nullable|integer|exists:projects,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $year = $request->year;
            $period = $request->period;
            $department = $request->department;

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
                $lastBatch = ThirteenthMonthPay::where('year', $year)
                    ->orderBy('id', 'desc')
                    ->first();
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
                    'computed_by' => auth()->id() ?? 1,
                    'total_amount' => 0,
                    'department' => $department, // Store department filter
                ]);

                // Get all active employees (not resigned or terminated)
                $employeesQuery = Employee::whereNotIn('activity_status', ['resigned', 'terminated']);

                // Apply department (project) filter if provided
                if ($department) {
                    $employeesQuery->where('project_id', $department);
                }

                $employees = $employeesQuery->get();

                $totalAmount = 0;

                foreach ($employees as $employee) {
                    // Get total basic salary from payroll items for the period
                    // Use period_end date (when salary was earned) not payment_date (when salary was paid)
                    $basicSalary = PayrollItem::whereHas('payroll', function ($query) use ($startDate, $endDate) {
                        $query->where(function ($q) use ($startDate, $endDate) {
                            // Check if payroll period overlaps with the 13th month calculation period
                            $q->whereBetween('period_start', [$startDate, $endDate])
                                ->orWhereBetween('period_end', [$startDate, $endDate])
                                ->orWhere(function ($q2) use ($startDate, $endDate) {
                                    $q2->where('period_start', '<=', $startDate)
                                        ->where('period_end', '>=', $endDate);
                                });
                        })->whereIn('status', ['paid', 'finalized']);
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
                Log::error('13th Month Pay Calculation Error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'request' => $request->all()
                ]);
                return response()->json([
                    'message' => 'Failed to calculate 13th month pay',
                    'error' => $e->getMessage(),
                    'trace' => config('app.debug') ? $e->getTraceAsString() : null
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('13th Month Pay Validation Error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Invalid request data',
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
            'items.employee.project',
            'items.employee.positionRate',
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
            'approved_by' => auth()->id() ?? 1,
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

    /**
     * Export 13th month pay to PDF with department grouping (Simple format)
     */
    public function exportPdf($id, Request $request)
    {
        $thirteenthMonth = ThirteenthMonthPay::with([
            'items.employee.positionRate',
            'computedBy',
            'approvedBy'
        ])->findOrFail($id);

        // Group employees by department (project)
        $employeesByDepartment = $thirteenthMonth->items()
            ->with('employee.project')
            ->get()
            ->groupBy(function ($item) {
                return $item->employee->project?->name ?? 'NO DEPARTMENT';
            })
            ->sortKeys();

        // Get company info from database
        $companyInfo = CompanyInfo::first();

        // Prepare data for PDF
        $data = [
            'thirteenthMonth' => $thirteenthMonth,
            'employeesByDepartment' => $employeesByDepartment,
            'companyInfo' => $companyInfo,
            'preparedBy' => $thirteenthMonth->computedBy->name ?? 'N/A',
            'approvedBy' => $thirteenthMonth->approvedBy->name ?? 'N/A',
        ];

        $pdf = Pdf::loadView('pdf.thirteenth-month-pay', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->download('13th-month-pay-' . $thirteenthMonth->batch_number . '.pdf');
    }

    /**
     * Export 13th month pay to detailed PDF with department grouping
     * Includes: Rate, Days Worked, /12, Employee's Savings, C/A Balance, Gross, Cash Advance, Net
     */
    public function exportPdfDetailed($id, Request $request)
    {
        $year = null;
        $thirteenthMonth = ThirteenthMonthPay::with([
            'items.employee.positionRate',
            'computedBy',
            'approvedBy'
        ])->findOrFail($id);

        $year = $thirteenthMonth->year;

        // Determine date range based on period
        $startDate = $year . '-01-01';
        $endDate = $year . '-12-31';

        if ($thirteenthMonth->period === 'first_half') {
            $endDate = $year . '-06-30';
        } elseif ($thirteenthMonth->period === 'second_half') {
            $startDate = $year . '-07-01';
        }

        // Get all employee IDs from the 13th month pay
        $employeeIds = $thirteenthMonth->items()->get()->pluck('employee_id')->toArray();

        // Get payroll summary data for each employee
        $payrollSummary = [];
        foreach ($thirteenthMonth->items as $item) {
            $employeeId = $item->employee_id;
            $employee = $item->employee;

            $payrollItems = PayrollItem::whereHas('payroll', function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('period_start', [$startDate, $endDate])
                        ->orWhereBetween('period_end', [$startDate, $endDate]);
                })->whereIn('status', ['paid', 'finalized']);
            })
                ->where('employee_id', $employeeId)
                ->get();

            $totalDaysWorked = $payrollItems->sum('days_worked');
            $totalSavings = $payrollItems->sum('employee_savings');
            $totalCashAdvance = $payrollItems->sum('cash_advance');

            // Get rate from payroll items, or fallback to employee's position rate
            $avgRate = $payrollItems->count() > 0 ? $payrollItems->avg('rate') : 0;
            if ($avgRate <= 0 && $employee) {
                // Fallback to employee's current rate from position or custom rate
                $avgRate = $employee->getBasicSalary() ?? 0;
            }

            // Get C/A Balance from active loans
            $caBalance = EmployeeLoan::where('employee_id', $employeeId)
                ->where('loan_type', 'cash_advance')
                ->where('status', 'active')
                ->sum('balance');

            $payrollSummary[$employeeId] = [
                'rate' => $avgRate,
                'total_days_worked' => $totalDaysWorked,
                'total_savings' => $totalSavings,
                'total_cash_advance' => $totalCashAdvance,
                'ca_balance' => $caBalance,
            ];
        }

        // Group employees by department (project)
        $employeesByDepartment = $thirteenthMonth->items()
            ->with('employee.project')
            ->get()
            ->groupBy(function ($item) {
                return $item->employee->project?->name ?? 'NO DEPARTMENT';
            })
            ->sortKeys();

        // Get company info from database
        $companyInfo = CompanyInfo::first();

        // Prepare data for PDF
        $data = [
            'thirteenthMonth' => $thirteenthMonth,
            'employeesByDepartment' => $employeesByDepartment,
            'payrollSummary' => $payrollSummary,
            'companyInfo' => $companyInfo,
            'preparedBy' => $thirteenthMonth->computedBy->name ?? 'N/A',
            'approvedBy' => $thirteenthMonth->approvedBy->name ?? 'N/A',
        ];

        $pdf = Pdf::loadView('pdf.thirteenth-month-pay-detailed', $data);
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('13th-month-pay-detailed-' . $thirteenthMonth->batch_number . '.pdf');
    }

    /**
     * Get list of departments for filtering
     */
    public function getDepartments()
    {
        $departments = Project::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($project) {
                return [
                    'value' => $project->id,
                    'title' => $project->name,
                ];
            })
            ->values();

        return response()->json($departments);
    }

    /**
     * Delete 13th month pay record
     * Only computed status can be deleted
     */
    public function destroy($id)
    {
        $thirteenthMonth = ThirteenthMonthPay::findOrFail($id);

        // Only allow deletion of computed status
        if ($thirteenthMonth->status !== 'computed') {
            return response()->json([
                'message' => 'Only computed 13th month pay records can be deleted'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Delete related items first
            $thirteenthMonth->items()->delete();

            // Delete the main record
            $thirteenthMonth->delete();

            DB::commit();

            return response()->json([
                'message' => '13th month pay record deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting 13th month pay: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete 13th month pay record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
