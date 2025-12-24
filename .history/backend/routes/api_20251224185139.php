<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PayrollController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication routes (Laravel Sanctum)
Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {
    
    // User profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Employees
    Route::apiResource('employees', App\Http\Controllers\Api\EmployeeController::class);
    Route::get('/employees/{employee}/allowances', [App\Http\Controllers\Api\EmployeeController::class, 'allowances']);
    Route::get('/employees/{employee}/loans', [App\Http\Controllers\Api\EmployeeController::class, 'loans']);
    Route::get('/employees/{employee}/deductions', [App\Http\Controllers\Api\EmployeeController::class, 'deductions']);
    
    // Departments & Locations
    Route::apiResource('departments', App\Http\Controllers\Api\DepartmentController::class);
    Route::apiResource('locations', App\Http\Controllers\Api\LocationController::class);
    
    // Attendance
    Route::apiResource('attendance', App\Http\Controllers\Api\AttendanceController::class);
    Route::post('/attendance/import-biometric', [App\Http\Controllers\Api\AttendanceController::class, 'importBiometric']);
    Route::post('/attendance/{attendance}/approve', [App\Http\Controllers\Api\AttendanceController::class, 'approve']);
    Route::post('/attendance/{attendance}/reject', [App\Http\Controllers\Api\AttendanceController::class, 'reject']);
    Route::get('/attendance/employee/{employee}/summary', [App\Http\Controllers\Api\AttendanceController::class, 'employeeSummary']);
    
    // Payroll
    Route::apiResource('payroll', PayrollController::class);
    Route::post('/payroll/{payroll}/process', [PayrollController::class, 'process']);
    Route::post('/payroll/{payroll}/check', [PayrollController::class, 'check']);
    Route::post('/payroll/{payroll}/recommend', [PayrollController::class, 'recommend']);
    Route::post('/payroll/{payroll}/approve', [PayrollController::class, 'approve']);
    Route::post('/payroll/{payroll}/mark-paid', [PayrollController::class, 'markPaid']);
    Route::get('/payroll/{payroll}/summary', [PayrollController::class, 'summary']);
    Route::get('/payroll/{payroll}/items', [App\Http\Controllers\Api\PayrollController::class, 'items']);
    Route::get('/payroll/{payroll}/export-excel', [App\Http\Controllers\Api\PayrollController::class, 'exportExcel']);
    
    // Payslips
    Route::get('/payslips/employee/{employee}', [App\Http\Controllers\Api\PayslipController::class, 'employeePayslips']);
    Route::get('/payslips/{payrollItem}/pdf', [App\Http\Controllers\Api\PayslipController::class, 'downloadPdf']);
    Route::get('/payslips/{payrollItem}/view', [App\Http\Controllers\Api\PayslipController::class, 'view']);
    
    // Employee Benefits
    Route::apiResource('allowances', App\Http\Controllers\Api\AllowanceController::class);
    Route::apiResource('loans', App\Http\Controllers\Api\LoanController::class);
    Route::post('/loans/{loan}/payments', [App\Http\Controllers\Api\LoanController::class, 'recordPayment']);
    Route::apiResource('deductions', App\Http\Controllers\Api\DeductionController::class);
    Route::apiResource('bonuses', App\Http\Controllers\Api\BonusController::class);
    
    // 13th Month Pay
    Route::get('/thirteenth-month/{year}', [App\Http\Controllers\Api\ThirteenthMonthController::class, 'index']);
    Route::post('/thirteenth-month/compute', [App\Http\Controllers\Api\ThirteenthMonthController::class, 'compute']);
    Route::post('/thirteenth-month/{thirteenthMonth}/approve', [App\Http\Controllers\Api\ThirteenthMonthController::class, 'approve']);
    Route::post('/thirteenth-month/{thirteenthMonth}/pay', [App\Http\Controllers\Api\ThirteenthMonthController::class, 'markPaid']);
    
    // Recruitment
    Route::apiResource('applicants', App\Http\Controllers\Api\ApplicantController::class);
    Route::post('/applicants/{applicant}/interview', [App\Http\Controllers\Api\ApplicantController::class, 'scheduleInterview']);
    Route::post('/applicants/{applicant}/hire', [App\Http\Controllers\Api\ApplicantController::class, 'hire']);
    Route::get('/applicants/{applicant}/documents', [App\Http\Controllers\Api\ApplicantController::class, 'documents']);
    
    // Leave Management
    Route::apiResource('leave-types', App\Http\Controllers\Api\LeaveTypeController::class);
    Route::apiResource('leaves', App\Http\Controllers\Api\LeaveController::class);
    Route::post('/leaves/{leave}/approve', [App\Http\Controllers\Api\LeaveController::class, 'approve']);
    Route::post('/leaves/{leave}/reject', [App\Http\Controllers\Api\LeaveController::class, 'reject']);
    Route::get('/leaves/employee/{employee}/credits', [App\Http\Controllers\Api\LeaveController::class, 'employeeCredits']);
    
    // Holidays
    Route::apiResource('holidays', App\Http\Controllers\Api\HolidayController::class);
    Route::get('/holidays/year/{year}', [App\Http\Controllers\Api\HolidayController::class, 'byYear']);
    
    // Government Contributions
    Route::get('/government/sss-table', [App\Http\Controllers\Api\GovernmentController::class, 'sssTable']);
    Route::get('/government/philhealth-table', [App\Http\Controllers\Api\GovernmentController::class, 'philhealthTable']);
    Route::get('/government/pagibig-table', [App\Http\Controllers\Api\GovernmentController::class, 'pagibigTable']);
    Route::get('/government/tax-table/{periodType}', [App\Http\Controllers\Api\GovernmentController::class, 'taxTable']);
    Route::post('/government/compute-contributions', [App\Http\Controllers\Api\GovernmentController::class, 'computeContributions']);
    
    // Reports
    Route::get('/reports/payroll-summary', [App\Http\Controllers\Api\ReportController::class, 'payrollSummary']);
    Route::get('/reports/employee-earnings', [App\Http\Controllers\Api\ReportController::class, 'employeeEarnings']);
    Route::get('/reports/government-remittance', [App\Http\Controllers\Api\ReportController::class, 'governmentRemittance']);
    Route::get('/reports/attendance-summary', [App\Http\Controllers\Api\ReportController::class, 'attendanceSummary']);
    Route::get('/reports/loan-ledger', [App\Http\Controllers\Api\ReportController::class, 'loanLedger']);
    
    // Settings
    Route::get('/settings', [App\Http\Controllers\Api\SettingController::class, 'index']);
    Route::put('/settings', [App\Http\Controllers\Api\SettingController::class, 'update']);
    
    // Audit Logs
    Route::get('/audit-logs', [App\Http\Controllers\Api\AuditLogController::class, 'index']);
    Route::get('/audit-logs/module/{module}', [App\Http\Controllers\Api\AuditLogController::class, 'byModule']);
    
    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    
    // Dashboard / Analytics
    Route::get('/dashboard/stats', [App\Http\Controllers\Api\DashboardController::class, 'stats']);
    Route::get('/dashboard/payroll-trends', [App\Http\Controllers\Api\DashboardController::class, 'payrollTrends']);
    Route::get('/dashboard/employee-distribution', [App\Http\Controllers\Api\DashboardController::class, 'employeeDistribution']);
});
