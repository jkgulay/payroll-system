<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Construction Payroll Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Philippine construction company payroll system
    |
    */

    'company' => [
        'name' => env('COMPANY_NAME', 'Construction Company Inc.'),
        'tin' => env('COMPANY_TIN'),
        'sss' => env('COMPANY_SSS'),
        'philhealth' => env('COMPANY_PHILHEALTH'),
        'pagibig' => env('COMPANY_PAGIBIG'),
    ],

    'payroll' => [
        'frequency' => env('PAYROLL_FREQUENCY', 'semi_monthly'),
        'standard_work_hours' => env('STANDARD_WORK_HOURS', 8),
        'overtime_multiplier' => env('OVERTIME_MULTIPLIER', 1.25),
        'night_diff_rate' => env('NIGHT_DIFF_RATE', 0.10),
    ],

    'cutoff' => [
        'first_half' => [
            'start' => 1,
            'end' => 15,
            'payment_day' => 20, // Pay on 20th
        ],
        'second_half' => [
            'start' => 16,
            'end' => 0, // End of month
            'payment_day' => 5, // Pay on 5th of next month
        ],
    ],

    'overtime_rates' => [
        'regular' => 1.25,          // Regular overtime
        'rest_day' => 1.30,         // Rest day
        'rest_day_overtime' => 1.69, // Rest day with overtime
        'special_holiday' => 1.30,  // Special non-working holiday
        'special_overtime' => 1.69, // Special holiday with overtime
        'regular_holiday' => 2.00,  // Regular holiday
        'regular_overtime' => 2.60, // Regular holiday with overtime
    ],

    'night_shift' => [
        'start' => '22:00',
        'end' => '06:00',
        'rate' => 0.10, // 10% additional
    ],

    'allowances' => [
        'types' => [
            'water' => 'Water Allowance',
            'cola' => 'Cost of Living Allowance (COLA)',
            'transportation' => 'Transportation Allowance',
            'meal' => 'Meal Allowance',
            'communication' => 'Communication Allowance',
            'incentive' => 'Performance Incentive',
            'site' => 'Site Allowance',
            'safety' => 'Safety Equipment Allowance',
            'other' => 'Other Allowance',
        ],
    ],

    'deduction_types' => [
        'ppe' => 'PPE (Shared Cost)',
        'tools' => 'Tools and Equipment',
        'uniform' => 'Uniform',
        'damage' => 'Damage/Breakage',
        'cash_advance' => 'Cash Advance',
        'insurance' => 'Insurance',
        'cooperative' => 'Cooperative',
        'other' => 'Other Deduction',
    ],

    'loan_types' => [
        'sss' => 'SSS Loan',
        'pagibig' => 'Pag-IBIG Loan',
        'company' => 'Company Loan',
        'emergency' => 'Emergency Loan',
        'salary_advance' => 'Salary Advance',
    ],

    // Philippine minimum wages by region (update as needed)
    'minimum_wage' => [
        'NCR' => 610,
        'Region_I' => 470,
        'Region_II' => 460,
        'Region_III' => 490,
        'Region_IV-A' => 500,
        'Region_IV-B' => 420,
        'Region_V' => 420,
        'Region_VI' => 450,
        'Region_VII' => 468,
        'Region_VIII' => 430,
        'Region_IX' => 433,
        'Region_X' => 438,
        'Region_XI' => 443,
        'Region_XII' => 430,
        'CAR' => 420,
        'CARAGA' => 420,
        'BARMM' => 430,
    ],

    // 13th month pay settings
    'thirteenth_month' => [
        'tax_exempt_limit' => 90000, // First 90k is tax-exempt
        'computation_basis' => 'basic_salary', // Only basic salary
        'payment_month' => 12, // December
    ],

    // Position-based default daily rates (construction industry standard)
    'position_rates' => [
        // Skilled Workers
        'Carpenter' => 550,
        'Mason' => 550,
        'Plumber' => 520,
        'Electrician' => 570,
        'Welder' => 560,
        'Painter' => 480,
        'Steel Worker' => 550,
        'Heavy Equipment Operator' => 650,

        // Semi-Skilled Workers
        'Construction Worker' => 450,
        'Helper' => 420,
        'Laborer' => 400,
        'Rigger' => 480,
        'Scaffolder' => 480,

        // Technical/Supervisory
        'Foreman' => 750,
        'Site Engineer' => 1200,
        'Project Engineer' => 1500,
        'Safety Officer' => 800,
        'Quality Control Inspector' => 700,
        'Surveyor' => 650,

        // Support Staff
        'Warehouse Keeper' => 450,
        'Timekeeper' => 450,
        'Security Guard' => 400,
        'Driver' => 480,
    ],

    // Working days calculation
    'working_days_per_month' => 22,
    'working_days_per_semi_month' => 11,
    'standard_hours_per_day' => 8,
    'rest_day' => 0, // 0 = Sunday, 6 = Saturday

    // Attendance settings
    'attendance' => [
        'standard_time_in' => '08:00',
        'standard_time_out' => '17:00',
        'grace_period_minutes' => 15, // Late if more than this
        'half_day_hours' => 4,
        'break_duration_minutes' => 60,
        'night_shift_start' => '22:00',
        'night_shift_end' => '06:00',
        'auto_mark_absent' => true, // Auto-mark absent for missing records
        'require_approval_manual_entry' => true,
    ],

    // Biometric settings
    'biometric' => [
        'enabled' => env('BIOMETRIC_ENABLED', false),
        'device_type' => env('BIOMETRIC_DEVICE_TYPE', 'zkteco'), // zkteco, suprema, etc.
        'device_ip' => env('BIOMETRIC_DEVICE_IP', ''),
        'device_port' => env('BIOMETRIC_DEVICE_PORT', 4370),
        'import_format' => 'csv', // csv, json, api
    ],

    // Yunatt Cloud API settings (for web portal integration)
    'yunatt' => [
        'enabled' => env('YUNATT_ENABLED', false),
        'api_url' => env('YUNATT_API_URL', 'https://api.yunatt.com/v1'), // Replace with actual URL
        'api_key' => env('YUNATT_API_KEY', ''),
        'company_id' => env('YUNATT_COMPANY_ID', ''),
        'username' => env('YUNATT_USERNAME', ''),
        'password' => env('YUNATT_PASSWORD', ''),
        'sync_interval_minutes' => 60, // Auto-sync every hour
    ],
];
