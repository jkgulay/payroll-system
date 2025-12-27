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

// Registration disabled - accounts created by admin/accountant through employee management

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'index']);
    Route::get('/employee/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'employeeDashboard']);
    Route::get('/accountant/dashboard/stats', [App\Http\Controllers\Api\AccountantController::class, 'getDashboardStats']);

    // User profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/profile', [App\Http\Controllers\Api\UserProfileController::class, 'getProfile']);
    Route::put('/profile', [App\Http\Controllers\Api\UserProfileController::class, 'updateProfile']);
    Route::post('/profile/change-password', [App\Http\Controllers\Api\UserProfileController::class, 'changePassword']);
    Route::post('/profile/upload-avatar', [App\Http\Controllers\Api\UserProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/remove-avatar', [App\Http\Controllers\Api\UserProfileController::class, 'removeAvatar']);

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
    Route::get('/payroll/{payroll}/export-pdf', [App\Http\Controllers\Api\PayrollController::class, 'exportPdf']);

    // Payslips
    Route::get('/payslips/employee/{employee}', [App\Http\Controllers\Api\PayslipController::class, 'employeePayslips']);
    Route::get('/payslips/my-payslips', [App\Http\Controllers\Api\PayslipController::class, 'myPayslips']);
    Route::get('/payslips/{payrollItem}/pdf', [App\Http\Controllers\Api\PayslipController::class, 'downloadPdf']);
    Route::get('/payslips/{payrollItem}/excel', [App\Http\Controllers\Api\PayslipController::class, 'downloadExcel']);
    Route::get('/payslips/{payrollItem}/view', [App\Http\Controllers\Api\PayslipController::class, 'view']);

    // Employee Benefits
    Route::apiResource('allowances', App\Http\Controllers\Api\AllowanceController::class);
    Route::apiResource('loans', App\Http\Controllers\Api\LoanController::class);
    Route::post('/loans/{loan}/payments', [App\Http\Controllers\Api\LoanController::class, 'recordPayment']);
    Route::apiResource('deductions', App\Http\Controllers\Api\DeductionController::class);
    Route::apiResource('bonuses', App\Http\Controllers\Api\BonusController::class);

    // 13th Month Pay
    Route::get('/thirteenth-month', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'index']);
    Route::get('/thirteenth-month/{id}', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'show']);
    Route::post('/thirteenth-month/calculate', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'calculate']);
    Route::post('/thirteenth-month/{id}/approve', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'approve']);
    Route::post('/thirteenth-month/{id}/mark-paid', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'markPaid']);

    // Recruitment - Job Postings
    Route::get('/job-postings', [App\Http\Controllers\Api\RecruitmentController::class, 'getJobPostings']);
    Route::post('/job-postings', [App\Http\Controllers\Api\RecruitmentController::class, 'storeJobPosting']);
    Route::put('/job-postings/{id}', [App\Http\Controllers\Api\RecruitmentController::class, 'updateJobPosting']);

    // Recruitment - Applicants
    Route::get('/applicants', [App\Http\Controllers\Api\RecruitmentController::class, 'getApplicants']);
    Route::post('/applicants', [App\Http\Controllers\Api\RecruitmentController::class, 'storeApplicant']);
    Route::put('/applicants/{id}/status', [App\Http\Controllers\Api\RecruitmentController::class, 'updateApplicantStatus']);
    Route::post('/applicants/{id}/convert-to-employee', [App\Http\Controllers\Api\RecruitmentController::class, 'convertToEmployee']);

    // Attendance Corrections
    Route::get('/attendance-corrections', [App\Http\Controllers\Api\AttendanceCorrectionController::class, 'index']);
    Route::get('/attendance-corrections/{id}', [App\Http\Controllers\Api\AttendanceCorrectionController::class, 'show']);
    Route::post('/attendance-corrections', [App\Http\Controllers\Api\AttendanceCorrectionController::class, 'store']);
    Route::post('/attendance-corrections/{id}/approve', [App\Http\Controllers\Api\AttendanceCorrectionController::class, 'approve']);
    Route::post('/attendance-corrections/{id}/reject', [App\Http\Controllers\Api\AttendanceCorrectionController::class, 'reject']);

    // Accountant Routes
    Route::post('/payslip-modifications', [App\Http\Controllers\Api\AccountantController::class, 'submitPayslipModification']);
    Route::put('/attendance/{id}/update', [App\Http\Controllers\Api\AccountantController::class, 'updateAttendance']);
    Route::get('/employees/{employeeId}/attendance', [App\Http\Controllers\Api\AccountantController::class, 'getEmployeeAttendance']);

    // Accountant Resume Management
    Route::prefix('accountant-resumes')->group(function () {
        // Accountant routes
        Route::post('/upload', [App\Http\Controllers\Api\AccountantResumeController::class, 'upload']);
        Route::get('/my-resumes', [App\Http\Controllers\Api\AccountantResumeController::class, 'myResumes']);
        Route::get('/approved', [App\Http\Controllers\Api\AccountantResumeController::class, 'approvedResumes']);
        Route::delete('/{id}', [App\Http\Controllers\Api\AccountantResumeController::class, 'destroy']);
        
        // Admin routes
        Route::middleware(['role:admin'])->group(function () {
            Route::get('/pending', [App\Http\Controllers\Api\AccountantResumeController::class, 'pendingResumes']);
            Route::get('/all', [App\Http\Controllers\Api\AccountantResumeController::class, 'allResumes']);
            Route::post('/{id}/approve', [App\Http\Controllers\Api\AccountantResumeController::class, 'approve']);
            Route::post('/{id}/reject', [App\Http\Controllers\Api\AccountantResumeController::class, 'reject']);
        });
        
        // Download (both accountant and admin)
        Route::get('/{id}/download', [App\Http\Controllers\Api\AccountantResumeController::class, 'download']);
    });

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
