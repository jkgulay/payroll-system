<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeeLeave;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateEmployeeLeaveStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:update-leave-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update employee activity status based on approved leave dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $updatedCount = 0;

        // Find employees who should be on leave today
        $activeLeaves = EmployeeLeave::where('status', 'approved')
            ->whereDate('leave_date_from', '<=', $today)
            ->whereDate('leave_date_to', '>=', $today)
            ->with('employee')
            ->get();

        foreach ($activeLeaves as $leave) {
            $employee = $leave->employee;

            if ($employee && $employee->activity_status !== 'on_leave') {
                $employee->update(['activity_status' => 'on_leave']);
                $updatedCount++;
                $this->info("Set employee #{$employee->id} ({$employee->full_name}) to on_leave");
                Log::info("Automated: Employee #{$employee->id} set to on_leave (Leave ID: {$leave->id})");
            }
        }

        // Find employees whose leave has ended and should return to active
        $employeesOnLeave = Employee::where('activity_status', 'on_leave')->get();

        foreach ($employeesOnLeave as $employee) {
            // Check if they have any active leaves today
            $hasActiveLeave = EmployeeLeave::where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('leave_date_from', '<=', $today)
                ->whereDate('leave_date_to', '>=', $today)
                ->exists();

            if (!$hasActiveLeave) {
                $employee->update(['activity_status' => 'active']);
                $updatedCount++;
                $this->info("Set employee #{$employee->id} ({$employee->full_name}) back to active");
                Log::info("Automated: Employee #{$employee->id} returned to active status (leave ended)");
            }
        }

        $this->info("Leave status update completed. {$updatedCount} employees updated.");

        return Command::SUCCESS;
    }
}
