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
        'grace_period_minutes' => env('PAYROLL_GRACE_PERIOD', 1),
        'half_day_late_minutes' => env('PAYROLL_HALF_DAY_LATE_MINUTES', 61),
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

        // Group 1: 8:00 AM time-in departments (Admin/Engineers)
        'group_8am' => [
            'standard_time_in' => '08:00',
            'grace_period_minutes' => 1, // Default 1 minute, uses project setting if available
            'half_day_threshold' => '09:01',
            'departments' => [
                'Admin Resign',
                'Sur admin',
                'Weekly Admin (mix)',
                'ENGINEER SA SITE',
                'Giovanni Construction and Power On Enterprise Co',
            ],
        ],

        // Group 2: 7:30 AM time-in departments (Remaining sites/departments)
        'group_730am' => [
            'standard_time_in' => '07:30',
            'grace_period_minutes' => 1, // Default 1 minute, uses project setting if available
            'half_day_threshold' => '08:31',
            'departments' => [
                // All other departments not in group_8am
                // Will be determined dynamically
            ],
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
