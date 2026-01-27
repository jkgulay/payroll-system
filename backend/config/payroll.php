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
        'standard_time_in' => env('PAYROLL_STANDARD_TIME_IN', '08:00'),
        'grace_period_minutes' => env('PAYROLL_GRACE_PERIOD', 15),
        'night_shift_start' => env('PAYROLL_NIGHT_START', '22:00'),
        'night_shift_end' => env('PAYROLL_NIGHT_END', '06:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Undertime Department Configuration
    |--------------------------------------------------------------------------
    |
    | Departments that require strict undertime tracking with specific
    | time-in requirements and grace periods.
    |
    */

    'undertime' => [
        // Enable/disable undertime tracking
        'enabled' => env('PAYROLL_UNDERTIME_ENABLED', true),

        // Standard time-in for undertime-tracked departments
        'standard_time_in' => env('PAYROLL_UNDERTIME_TIME_IN', '08:00'),

        // Grace period in minutes (e.g., 3 minutes = 8:00 to 8:03)
        'grace_period_minutes' => env('PAYROLL_UNDERTIME_GRACE_PERIOD', 3),

        // Half-day threshold (time-in at or after this time = half-day)
        'half_day_threshold' => env('PAYROLL_UNDERTIME_HALFDAY_THRESHOLD', '09:01'),

        // Departments that require strict undertime tracking
        'tracked_departments' => [
            'Admin Resign',
            'Sur admin',
            'Weekly Admin (mix)',
            'ENGINEER SA SITE',
            'Giovanni Construction and Power On Enterprise Co',
        ],
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

];
