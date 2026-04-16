<?php

namespace App\Console\Commands;

use App\Models\EmployeeDeduction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairDeductionStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --apply: execute updates; otherwise runs as dry-run.
     * --employee-id: optional scope for a specific employee.
     */
    protected $signature = 'deductions:repair-statuses
                            {--apply : Apply updates (default is dry-run)}
                            {--employee-id= : Optional employee ID filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair deductions that are completed but still have balance, and align installments/end_date with remaining balance';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apply = (bool) $this->option('apply');
        $employeeId = $this->option('employee-id');

        $query = EmployeeDeduction::query()
            ->whereNull('deleted_at');

        if (!empty($employeeId)) {
            $query->where('employee_id', $employeeId);
        }

        $deductions = $query->get();

        if ($deductions->isEmpty()) {
            $this->info('No deductions found for the given scope.');
            return Command::SUCCESS;
        }

        $this->info(($apply ? 'APPLY' : 'DRY RUN') . ': scanning ' . $deductions->count() . ' deductions...');

        $rowsToFix = [];

        foreach ($deductions as $deduction) {
            $balance = (float) $deduction->balance;
            $totalAmount = max((float) $deduction->total_amount, 0);
            $installmentsPaid = max((int) $deduction->installments_paid, 0);
            $installments = max((int) $deduction->installments, 1);

            $amountPerCutoff = (float) ($deduction->amount_per_cutoff ?? 0);
            if ($amountPerCutoff <= 0 && $installments > 0) {
                $amountPerCutoff = $totalAmount / $installments;
            }

            $targetStatus = $balance <= 0 ? 'completed' : 'active';
            $targetInstallments = $installments;
            $targetEndDate = $deduction->end_date;
            $targetBalance = $balance <= 0 ? 0.0 : $balance;

            if ($targetBalance > 0 && $amountPerCutoff > 0) {
                $remainingInstallmentsNeeded = (int) ceil($targetBalance / $amountPerCutoff);
                $targetInstallments = max($installments, $installmentsPaid + $remainingInstallmentsNeeded, 1);

                if (!empty($deduction->start_date)) {
                    $extendedEndDate = Carbon::parse($deduction->start_date)
                        ->addMonths((int) ceil($targetInstallments / 2))
                        ->toDateString();

                    if (empty($deduction->end_date) || Carbon::parse($extendedEndDate)->gt(Carbon::parse($deduction->end_date))) {
                        $targetEndDate = $extendedEndDate;
                    }
                }
            }

            $needsFix =
                $deduction->status !== $targetStatus ||
                (float) $deduction->balance !== (float) $targetBalance ||
                (int) $deduction->installments !== (int) $targetInstallments ||
                ((string) ($deduction->end_date ?? '')) !== ((string) ($targetEndDate ?? ''));

            if (!$needsFix) {
                continue;
            }

            $rowsToFix[] = [
                'id' => $deduction->id,
                'employee_id' => $deduction->employee_id,
                'old_status' => $deduction->status,
                'new_status' => $targetStatus,
                'old_balance' => $deduction->balance,
                'new_balance' => $targetBalance,
                'old_installments' => $deduction->installments,
                'new_installments' => $targetInstallments,
                'old_end_date' => $deduction->end_date,
                'new_end_date' => $targetEndDate,
            ];

            if ($apply) {
                $deduction->update([
                    'status' => $targetStatus,
                    'balance' => $targetBalance,
                    'installments' => $targetInstallments,
                    'end_date' => $targetEndDate,
                ]);
            }
        }

        if (empty($rowsToFix)) {
            $this->info('No inconsistent deductions found.');
            return Command::SUCCESS;
        }

        $this->table(
            [
                'ID',
                'Emp',
                'Status',
                'New Status',
                'Balance',
                'New Balance',
                'Inst',
                'New Inst',
                'End Date',
                'New End Date',
            ],
            array_map(function (array $row) {
                return [
                    $row['id'],
                    $row['employee_id'],
                    $row['old_status'],
                    $row['new_status'],
                    number_format((float) $row['old_balance'], 2),
                    number_format((float) $row['new_balance'], 2),
                    $row['old_installments'],
                    $row['new_installments'],
                    $row['old_end_date'],
                    $row['new_end_date'],
                ];
            }, $rowsToFix)
        );

        if ($apply) {
            $this->info('Applied fixes to ' . count($rowsToFix) . ' deductions.');
        } else {
            $this->warn('Dry-run only. Re-run with --apply to persist changes.');
        }

        return Command::SUCCESS;
    }
}
