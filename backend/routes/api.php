<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication routes with rate limiting to prevent brute force
Route::middleware(['throttle:login'])->group(function () {
    Route::post('/login', [App\Http\Controllers\Api\AuthController::class, 'login']);
    Route::post('/two-factor/verify', [App\Http\Controllers\Api\TwoFactorController::class, 'verify']);
});

Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->middleware('auth:sanctum');

// Two-Factor Authentication routes
// Registration disabled - accounts created by admin/accountant through employee management

// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'index']);
    Route::get('/employee/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'employeeDashboard']);
    Route::get('/accountant/dashboard/stats', [App\Http\Controllers\Api\AccountantController::class, 'getDashboardStats']);

    // AI Chat Routes
    Route::post('/chat', [App\Http\Controllers\Api\ChatController::class, 'chat']);
    Route::get('/chat/suggestions', [App\Http\Controllers\Api\ChatController::class, 'getSuggestedQuestions']);
    Route::post('/chat/clear', [App\Http\Controllers\Api\ChatController::class, 'clearHistory']);

    // User profile
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/profile', [App\Http\Controllers\Api\UserProfileController::class, 'getProfile']);
    Route::put('/profile', [App\Http\Controllers\Api\UserProfileController::class, 'updateProfile']);
    Route::post('/profile/change-password', [App\Http\Controllers\Api\UserProfileController::class, 'changePassword']);
    Route::post('/profile/upload-avatar', [App\Http\Controllers\Api\UserProfileController::class, 'uploadAvatar']);
    Route::delete('/profile/remove-avatar', [App\Http\Controllers\Api\UserProfileController::class, 'removeAvatar']);

    // Two-Factor Authentication (protected routes)
    Route::get('/two-factor/status', [App\Http\Controllers\Api\TwoFactorController::class, 'status']);
    Route::post('/two-factor/enable', [App\Http\Controllers\Api\TwoFactorController::class, 'enable']);
    Route::post('/two-factor/confirm', [App\Http\Controllers\Api\TwoFactorController::class, 'confirm']);
    Route::delete('/two-factor/disable', [App\Http\Controllers\Api\TwoFactorController::class, 'disable']);
    Route::post('/two-factor/recovery-codes', [App\Http\Controllers\Api\TwoFactorController::class, 'regenerateRecoveryCodes']);

    // Employee Import - MUST come before employees apiResource (rate limited)
    Route::middleware(['throttle:10,1'])->post('/employees/import', [App\Http\Controllers\Api\EmployeeImportController::class, 'import']);
    Route::get('/employees/import/template', [App\Http\Controllers\Api\EmployeeImportController::class, 'downloadTemplate']);

    // Employees - specific routes must come before resource routes
    Route::get('/employees/departments', [App\Http\Controllers\Api\EmployeeController::class, 'getDepartments']);
    Route::apiResource('employees', App\Http\Controllers\Api\EmployeeController::class);
    Route::get('/employees/{employee}/allowances', [App\Http\Controllers\Api\EmployeeController::class, 'allowances']);
    Route::get('/employees/{employee}/loans', [App\Http\Controllers\Api\EmployeeController::class, 'loans']);
    Route::get('/employees/{employee}/deductions', [App\Http\Controllers\Api\EmployeeController::class, 'deductions']);
    Route::get('/employees/{employee}/credentials', [App\Http\Controllers\Api\EmployeeController::class, 'getCredentials']);
    Route::post('/employees/{employee}/reset-password', [App\Http\Controllers\Api\EmployeeController::class, 'resetPassword']);
    Route::post('/employees/{employee}/update-pay-rate', [App\Http\Controllers\Api\EmployeeController::class, 'updatePayRate']);
    Route::post('/employees/{employee}/clear-custom-pay-rate', [App\Http\Controllers\Api\EmployeeController::class, 'clearCustomPayRate']);

    // Employee Applications
    Route::apiResource('employee-applications', App\Http\Controllers\Api\EmployeeApplicationController::class);
    Route::post('/employee-applications/{id}/approve', [App\Http\Controllers\Api\EmployeeApplicationController::class, 'approve']);
    Route::post('/employee-applications/{id}/reject', [App\Http\Controllers\Api\EmployeeApplicationController::class, 'reject']);

    // Projects & Locations
    Route::apiResource('projects', App\Http\Controllers\Api\ProjectController::class);
    Route::get('projects/{project}/employees', [App\Http\Controllers\Api\ProjectController::class, 'employees']);
    Route::post('projects/{project}/mark-complete', [App\Http\Controllers\Api\ProjectController::class, 'markComplete']);
    Route::post('projects/{project}/reactivate', [App\Http\Controllers\Api\ProjectController::class, 'reactivate']);
    Route::apiResource('locations', App\Http\Controllers\Api\LocationController::class);

    // Biometric Import Routes - Staff Information and Punch Records
    Route::post('/biometric/import-staff', [App\Http\Controllers\Api\BiometricImportController::class, 'importStaffInformation']);
    Route::post('/biometric/import-punch-records', [App\Http\Controllers\Api\BiometricImportController::class, 'importPunchRecords']);
    Route::get('/biometric/template-info', [App\Http\Controllers\Api\BiometricImportController::class, 'getTemplateInfo']);

    // Attendance - Specific routes MUST come before apiResource
    Route::post('/attendance/import-biometric', [App\Http\Controllers\Api\AttendanceController::class, 'importBiometric']);
    Route::post('/attendance/fetch-from-device', [App\Http\Controllers\Api\AttendanceController::class, 'fetchFromDevice']);
    Route::post('/attendance/sync-employees', [App\Http\Controllers\Api\AttendanceController::class, 'syncEmployees']);
    Route::post('/attendance/clear-device-logs', [App\Http\Controllers\Api\AttendanceController::class, 'clearDeviceLogs']);
    Route::get('/attendance/device-info', [App\Http\Controllers\Api\AttendanceController::class, 'deviceInfo']);
    Route::get('/attendance/pending-approvals', [App\Http\Controllers\Api\AttendanceController::class, 'pendingApprovals']);
    Route::get('/attendance/summary', [App\Http\Controllers\Api\AttendanceController::class, 'summary']);
    Route::get('/attendance/summary/export', [App\Http\Controllers\Api\AttendanceController::class, 'exportSummary']);
    Route::get('/attendance/employee/{employee}/summary', [App\Http\Controllers\Api\AttendanceController::class, 'employeeSummary']);
    Route::post('/attendance/mark-absent', [App\Http\Controllers\Api\AttendanceController::class, 'markAbsent']);
    Route::post('/attendance/{attendance}/approve', [App\Http\Controllers\Api\AttendanceController::class, 'approve']);
    Route::post('/attendance/{attendance}/reject', [App\Http\Controllers\Api\AttendanceController::class, 'reject']);

    // Daily Time Record (DTR) Routes
    Route::post('/attendance/dtr/generate', [App\Http\Controllers\Api\DailyTimeRecordController::class, 'generate']);
    Route::post('/attendance/dtr/generate-daily', [App\Http\Controllers\Api\DailyTimeRecordController::class, 'generateDaily']);
    Route::post('/attendance/dtr/preview', [App\Http\Controllers\Api\DailyTimeRecordController::class, 'preview']);

    Route::apiResource('attendance', App\Http\Controllers\Api\AttendanceController::class);

    // Position Rates
    Route::apiResource('position-rates', App\Http\Controllers\Api\PositionRateController::class);
    Route::post('/position-rates/{positionRate}/bulk-update', [App\Http\Controllers\Api\PositionRateController::class, 'bulkUpdateEmployees']);
    Route::get('/position-rates/by-name/search', [App\Http\Controllers\Api\PositionRateController::class, 'getByName']);
    Route::get('/positions/names', [App\Http\Controllers\Api\PositionRateController::class, 'getPositionNames']);

    // Employee Benefits
    Route::apiResource('allowances', App\Http\Controllers\Api\AllowanceController::class);

    // Meal Allowance Management - specific routes MUST come before apiResource
    Route::get('/meal-allowances/positions', [App\Http\Controllers\Api\MealAllowanceController::class, 'getPositions']);
    Route::post('/meal-allowances/employees-by-position', [App\Http\Controllers\Api\MealAllowanceController::class, 'getEmployeesByPosition']);
    Route::post('/meal-allowances/bulk-assign-by-position', [App\Http\Controllers\Api\MealAllowanceController::class, 'bulkAssignByPosition']);
    Route::post('/meal-allowances/{mealAllowance}/submit', [App\Http\Controllers\Api\MealAllowanceController::class, 'submit']);
    Route::post('/meal-allowances/{mealAllowance}/approval', [App\Http\Controllers\Api\MealAllowanceController::class, 'updateApproval']);
    Route::post('/meal-allowances/{mealAllowance}/generate-pdf', [App\Http\Controllers\Api\MealAllowanceController::class, 'generatePdf']);
    Route::get('/meal-allowances/{mealAllowance}/download-pdf', [App\Http\Controllers\Api\MealAllowanceController::class, 'downloadPdf']);
    Route::apiResource('meal-allowances', App\Http\Controllers\Api\MealAllowanceController::class);

    // Loans - specific routes MUST come before apiResource
    Route::get('/loans/pending', [App\Http\Controllers\Api\LoanController::class, 'index'])->middleware('role:admin');
    Route::post('/loans/{loan}/approve', [App\Http\Controllers\Api\LoanController::class, 'approve'])->middleware('role:admin');
    Route::post('/loans/{loan}/reject', [App\Http\Controllers\Api\LoanController::class, 'reject'])->middleware('role:admin');
    Route::post('/loans/{loan}/payments', [App\Http\Controllers\Api\LoanController::class, 'recordPayment']);
    Route::apiResource('loans', App\Http\Controllers\Api\LoanController::class);

    // Payroll - specific routes MUST come before apiResource
    Route::post('/payrolls/{payroll}/finalize', [App\Http\Controllers\PayrollController::class, 'finalize']);
    Route::get('/payrolls/{payroll}/download-register', [App\Http\Controllers\PayrollController::class, 'downloadRegister']);
    Route::get('/payrolls/{payroll}/employees/{employee}/download-payslip', [App\Http\Controllers\PayrollController::class, 'downloadPayslip']);
    Route::apiResource('payrolls', App\Http\Controllers\PayrollController::class);


    // Deductions - specific routes MUST come before apiResource
    Route::post('/deductions/{deduction}/record-installment', [App\Http\Controllers\Api\DeductionController::class, 'recordInstallment']);

    // Cash Bond specific routes
    Route::get('/cash-bonds', [App\Http\Controllers\Api\DeductionController::class, 'getCashBonds']);
    Route::post('/cash-bonds', [App\Http\Controllers\Api\DeductionController::class, 'createCashBond'])->middleware('role:admin,accountant');
    Route::post('/deductions/{deduction}/refund-cash-bond', [App\Http\Controllers\Api\DeductionController::class, 'refundCashBond'])->middleware('role:admin,accountant');

    Route::apiResource('deductions', App\Http\Controllers\Api\DeductionController::class);

    Route::apiResource('bonuses', App\Http\Controllers\Api\BonusController::class);

    // 13th Month Pay
    Route::get('/thirteenth-month', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'index']);
    Route::get('/thirteenth-month/departments', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'getDepartments']);
    Route::get('/thirteenth-month/{id}', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'show']);
    Route::get('/thirteenth-month/{id}/export-pdf', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'exportPdf']);
    Route::post('/thirteenth-month/calculate', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'calculate']);
    Route::post('/thirteenth-month/{id}/approve', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'approve']);
    Route::post('/thirteenth-month/{id}/mark-paid', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'markPaid']);
    Route::delete('/thirteenth-month/{id}', [App\Http\Controllers\Api\ThirteenthMonthPayController::class, 'destroy']);

    // Resignations - Define specific routes BEFORE the resource route to avoid conflicts
    Route::get('/resignations/employee/{employeeId}', [App\Http\Controllers\Api\ResignationController::class, 'getEmployeeResignation']);
    Route::post('/resignations/{id}/approve', [App\Http\Controllers\Api\ResignationController::class, 'approve']);
    Route::post('/resignations/{id}/reject', [App\Http\Controllers\Api\ResignationController::class, 'reject']);
    Route::post('/resignations/{id}/process-final-pay', [App\Http\Controllers\Api\ResignationController::class, 'processFinalPay']);
    Route::post('/resignations/{id}/release-final-pay', [App\Http\Controllers\Api\ResignationController::class, 'releaseFinalPay']);
    Route::get('/resignations/{id}/attachments/{attachmentIndex}/download', [App\Http\Controllers\Api\ResignationController::class, 'downloadAttachment']);
    Route::delete('/resignations/{id}/attachments/{attachmentIndex}', [App\Http\Controllers\Api\ResignationController::class, 'deleteAttachment']);
    Route::apiResource('resignations', App\Http\Controllers\Api\ResignationController::class);

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
    Route::get('/leaves/my-leaves', [App\Http\Controllers\Api\LeaveController::class, 'myLeaves']);
    Route::get('/leaves/my-credits', [App\Http\Controllers\Api\LeaveController::class, 'myCredits']);
    Route::get('/leaves/pending', [App\Http\Controllers\Api\LeaveController::class, 'pendingLeaves']);
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

    // Employee Documents and Government Information
    Route::get('/employees/{employeeId}/documents', [App\Http\Controllers\Api\EmployeeDocumentController::class, 'index']);
    Route::post('/employees/{employeeId}/documents', [App\Http\Controllers\Api\EmployeeDocumentController::class, 'store']);
    Route::get('/documents/{documentId}/download', [App\Http\Controllers\Api\EmployeeDocumentController::class, 'download']);
    Route::delete('/documents/{documentId}', [App\Http\Controllers\Api\EmployeeDocumentController::class, 'destroy']);
    Route::get('/employees/{employeeId}/government-info', [App\Http\Controllers\Api\EmployeeDocumentController::class, 'getGovernmentInfo']);
    Route::put('/employees/{employeeId}/government-info', [App\Http\Controllers\Api\EmployeeDocumentController::class, 'updateGovernmentInfo']);

    // Reports
    Route::get('/reports/payroll-summary', [App\Http\Controllers\Api\ReportController::class, 'payrollSummary']);
    Route::get('/reports/employee-earnings', [App\Http\Controllers\Api\ReportController::class, 'employeeEarnings']);
    Route::get('/reports/government-remittance', [App\Http\Controllers\Api\ReportController::class, 'governmentRemittance']);
    Route::get('/reports/attendance-summary', [App\Http\Controllers\Api\ReportController::class, 'attendanceSummary']);
    Route::get('/reports/loan-ledger', [App\Http\Controllers\Api\ReportController::class, 'loanLedger']);

    // Settings
    Route::get('/settings', [App\Http\Controllers\Api\SettingController::class, 'index']);
    Route::put('/settings', [App\Http\Controllers\Api\SettingController::class, 'update']);

    // Government Rates
    Route::apiResource('government-rates', App\Http\Controllers\Api\GovernmentRateController::class);
    Route::post('/government-rates/for-salary', [App\Http\Controllers\Api\GovernmentRateController::class, 'getForSalary']);
    Route::post('/government-rates/bulk-delete', [App\Http\Controllers\Api\GovernmentRateController::class, 'bulkDelete']);

    // Audit Logs
    Route::get('/audit-logs', [App\Http\Controllers\Api\AuditLogController::class, 'index']);
    Route::get('/audit-logs/module/{module}', [App\Http\Controllers\Api\AuditLogController::class, 'byModule']);

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

    // Dashboard Stats
    Route::get('/dashboard/stats', [App\Http\Controllers\Api\DashboardController::class, 'stats']);
    Route::get('/dashboard/today-staff-info', [App\Http\Controllers\Api\DashboardController::class, 'todayStaffInfo']);
    Route::get('/dashboard/recent-activities', [App\Http\Controllers\Api\DashboardController::class, 'recentActivities']);
    Route::get('/dashboard/upcoming-events', [App\Http\Controllers\Api\DashboardController::class, 'upcomingEvents']);
});
