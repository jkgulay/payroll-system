<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for different route groups to prevent abuse
    |
    */

    'api' => [
        'attempts' => env('API_RATE_LIMIT', 60),
        'per_minute' => 1,
    ],

    'login' => [
        'attempts' => env('LOGIN_RATE_LIMIT', 5),
        'per_minute' => 1,
        'decay_minutes' => 15, // Block for 15 minutes after max attempts
    ],

    'file_upload' => [
        'attempts' => env('FILE_UPLOAD_RATE_LIMIT', 10),
        'per_minute' => 1,
    ],

    'sensitive_operations' => [
        'attempts' => env('SENSITIVE_OPERATIONS_RATE_LIMIT', 20),
        'per_minute' => 1,
    ],

];
