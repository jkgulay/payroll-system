<?php

/**
 * Attendance System API Routes
 * Add these routes to routes/api.php
 */

use App\Http\Controllers\Api\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {

    // Standard CRUD
    Route::apiResource('attendance', AttendanceController::class);

    // Biometric Integration
    Route::post('attendance/import-biometric', [AttendanceController::class, 'importBiometric'])
        ->name('attendance.import-biometric');

    Route::post('attendance/fetch-from-device', [AttendanceController::class, 'fetchFromDevice'])
        ->name('attendance.fetch-from-device');

    Route::post('attendance/sync-employees', [AttendanceController::class, 'syncEmployees'])
        ->name('attendance.sync-employees');

    Route::get('attendance/device-info', [AttendanceController::class, 'deviceInfo'])
        ->name('attendance.device-info');

    // Approval Workflow
    Route::post('attendance/{attendance}/approve', [AttendanceController::class, 'approve'])
        ->name('attendance.approve');

    Route::post('attendance/{attendance}/reject', [AttendanceController::class, 'reject'])
        ->name('attendance.reject');

    Route::get('attendance/pending-approvals', [AttendanceController::class, 'pendingApprovals'])
        ->name('attendance.pending-approvals');

    // Reports and Summaries
    Route::get('attendance/summary', [AttendanceController::class, 'summary'])
        ->name('attendance.summary');

    Route::get('attendance/employee/{employeeId}/summary', [AttendanceController::class, 'employeeSummary'])
        ->name('attendance.employee-summary');

    // Utilities
    Route::post('attendance/mark-absent', [AttendanceController::class, 'markAbsent'])
        ->name('attendance.mark-absent');
});
