<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateAttendanceHours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:recalculate 
                            {--start-date= : Start date for recalculation (Y-m-d)}
                            {--end-date= : End date for recalculation (Y-m-d)}
                            {--employee-id= : Specific employee ID to recalculate}
                            {--all : Recalculate all attendance records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate attendance hours (regular, overtime, undertime, late) for existing records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Attendance::query()
            ->whereNotNull('time_in')
            ->whereNotNull('time_out');

        if ($this->option('start-date')) {
            $query->where('attendance_date', '>=', $this->option('start-date'));
        }

        if ($this->option('end-date')) {
            $query->where('attendance_date', '<=', $this->option('end-date'));
        }

        if ($this->option('employee-id')) {
            $query->where('employee_id', $this->option('employee-id'));
        }

        if (!$this->option('all') && !$this->option('start-date') && !$this->option('end-date') && !$this->option('employee-id')) {
            $this->error('Please specify --all, --start-date, --end-date, or --employee-id');
            return 1;
        }

        $totalRecords = $query->count();
        
        if ($totalRecords === 0) {
            $this->info('No attendance records found matching the criteria.');
            return 0;
        }

        $this->info("Found {$totalRecords} attendance records to recalculate.");
        
        if (!$this->confirm('Do you want to proceed?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $processed = 0;
        $errors = 0;

        $query->chunk(100, function ($attendances) use (&$processed, &$errors, $bar) {
            foreach ($attendances as $attendance) {
                try {
                    $attendance->calculateHours();
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error("Failed to recalculate attendance ID {$attendance->id}: " . $e->getMessage());
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Recalculation complete!");
        $this->info("Processed: {$processed}");
        if ($errors > 0) {
            $this->warn("Errors: {$errors} (check logs for details)");
        }

        return 0;
    }
}
