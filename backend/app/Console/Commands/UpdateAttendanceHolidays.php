<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Holiday;
use Illuminate\Console\Command;
use Carbon\Carbon;

class UpdateAttendanceHolidays extends Command
{
    protected $signature = 'attendance:update-holidays {--year=} {--date-range=}';
    
    protected $description = 'Update existing attendance records to mark holidays';

    public function handle()
    {
        $this->info('Starting to update attendance holidays...');
        
        $query = Attendance::query();
        
        // Filter by year if provided
        if ($this->option('year')) {
            $year = $this->option('year');
            $query->whereYear('attendance_date', $year);
            $this->info("Filtering by year: {$year}");
        }
        
        // Filter by date range if provided
        if ($this->option('date-range')) {
            $range = explode(',', $this->option('date-range'));
            if (count($range) === 2) {
                $query->whereBetween('attendance_date', [$range[0], $range[1]]);
                $this->info("Filtering by date range: {$range[0]} to {$range[1]}");
            }
        }
        
        $attendances = $query->get();
        $this->info("Found {$attendances->count()} attendance records to check");
        
        $updated = 0;
        $bar = $this->output->createProgressBar($attendances->count());
        
        foreach ($attendances as $attendance) {
            $holiday = Holiday::getHolidayForDate($attendance->attendance_date);
            
            if ($holiday) {
                $attendance->is_holiday = true;
                $attendance->holiday_type = $holiday->type;
                $attendance->save();
                $updated++;
            } else if ($attendance->is_holiday) {
                // Clear holiday flag if date is no longer a holiday
                $attendance->is_holiday = false;
                $attendance->holiday_type = null;
                $attendance->save();
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        $this->info("Updated {$updated} attendance records with holiday information");
        $this->info('Done!');
        
        return 0;
    }
}
