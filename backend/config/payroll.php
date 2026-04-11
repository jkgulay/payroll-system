<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standard Working Hours
    |--------------------------------------------------------------------------
    |
    | The standard number of hours an employee is expected to work per day.
    | Used for calculating overtime and undertime.
    |
    */

    'standard_hours_per_day' => env('PAYROLL_STANDARD_HOURS', 8),

    /*
    |--------------------------------------------------------------------------
    | Attendance Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for attendance tracking, time-in requirements, and
    | grace periods.
    |
    */

    'attendance' => [
        'standard_time_in' => env('PAYROLL_STANDARD_TIME_IN', '07:30'),
        'standard_time_out' => env('PAYROLL_STANDARD_TIME_OUT', '17:00'),
        'grace_period_minutes' => env('PAYROLL_GRACE_PERIOD', 3),
        'half_day_late_minutes' => env('PAYROLL_HALF_DAY_LATE_MINUTES', 61),
        // Hours threshold below which attendance is considered half-day (e.g., less than 5 hours = half day)
        'half_day_hours_threshold' => env('PAYROLL_HALF_DAY_HOURS_THRESHOLD', 5.0),
        'night_shift_start' => env('PAYROLL_NIGHT_START', '22:00'),
        'night_shift_end' => env('PAYROLL_NIGHT_END', '06:00'),

        // Break Detection Settings (for biometric import)
        'break_detection_min_hours' => env('PAYROLL_BREAK_MIN_HOURS', 3),
        'break_detection_max_hours' => env('PAYROLL_BREAK_MAX_HOURS', 6),

        // Smart Detection Settings (3-punch scenario detection)
        'smart_detection_window_minutes' => env('PAYROLL_SMART_DETECTION_MINUTES', 120), // 2 hours

        // OT Grace Period (minimum gap between regular time_out and OT start)
        'ot_grace_period_minutes' => env('PAYROLL_OT_GRACE_MINUTES', 15),

        // Limit how far back attendance records are recalculated after
        // schedule updates, to avoid rewriting historical payroll periods.
        'schedule_recalculation_lookback_days' => env('PAYROLL_SCHEDULE_RECALC_LOOKBACK_DAYS', 60),

        // Overnight punch carry-over:
        // If enabled, punches at or before the cutoff time are attached to the
        // previous attendance date so records like 20:14 -> 01:00 stay together.
        'overnight_carry_enabled' => env('PAYROLL_OVERNIGHT_CARRY_ENABLED', true),
        // Restrict carry-over to employees whose configured schedule crosses midnight.
        'overnight_carry_require_overnight_schedule' => env('PAYROLL_OVERNIGHT_CARRY_REQUIRE_OVERNIGHT_SCHEDULE', true),
        'overnight_carry_cutoff_hour' => env('PAYROLL_OVERNIGHT_CARRY_CUTOFF_HOUR', 6),

        // Ignore noisy placeholder punches that some device exports inject at exactly 00:00.
        // Real punches such as 00:03 are retained.
        'ignore_exact_midnight_placeholder' => env('PAYROLL_IGNORE_EXACT_MIDNIGHT_PLACEHOLDER', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Undertime Configuration
    |--------------------------------------------------------------------------
    |
    | Undertime tracking is now managed per-department through the
    | Attendance Settings UI. Each project/department can have its own
    | time_in, time_out, and grace_period_minutes settings.
    |
    */

    'undertime' => [
        // Enable/disable undertime tracking globally
        'enabled' => env('PAYROLL_UNDERTIME_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Overtime Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for overtime pay calculations.
    |
    */

    'overtime' => [
        'regular_multiplier' => 1.25,  // Regular overtime (125% of hourly rate)
        'holiday_multiplier' => 2.0,   // Holiday overtime (200% of hourly rate)
        'night_diff_multiplier' => 0.10, // Night differential (10% additional)
    ],

    /*
    |--------------------------------------------------------------------------
    | Deduction Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for various deductions.
    |
    */

    'deductions' => [
        // Government contributions
        'sss_enabled' => true,
        'philhealth_enabled' => true,
        'pagibig_enabled' => true,

        // Undertime deduction formula: (rate / standard_hours) * undertime_hours
        'undertime_per_hour' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rest Day
    |--------------------------------------------------------------------------
    |
    | The day of the week considered as rest day (0 = Sunday, 6 = Saturday)
    |
    */

    'rest_day' => env('PAYROLL_REST_DAY', 0), // Default: Sunday

    /*
    |--------------------------------------------------------------------------
    | Working Days Configuration
    |--------------------------------------------------------------------------
    |
    | Number of working days per payroll period.
    |
    */

    'working_days_per_month' => env('PAYROLL_WORKING_DAYS_PER_MONTH', 22),
    'working_days_per_semi_month' => env('PAYROLL_WORKING_DAYS_PER_SEMI_MONTH', 11),

    /*
    |--------------------------------------------------------------------------
    | Export Layout Configuration
    |--------------------------------------------------------------------------
    |
    | Controls how many employee rows are printed per page in payroll register
    | exports (PDF, Word, Excel) so acknowledgment/signature footer blocks
    | remain visible and do not overlap the table.
    |
    */

    'exports' => [
        'register_rows_per_page' => env('PAYROLL_REGISTER_ROWS_PER_PAGE', 20),
    ],

];
