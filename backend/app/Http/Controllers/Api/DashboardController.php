<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Payroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $data = [
            'stats' => [
                'totalEmployees' => Employee::where('employment_status', 'active')->count(),
                'activeEmployees' => Employee::where('employment_status', 'active')->where('is_active', true)->count(),
                'periodPayroll' => 0,
                'presentToday' => 0,
                'pendingApprovals' => 0,
            ],
            'recent_attendance' => [],
            'recent_payrolls' => [],
        ];

        return response()->json($data);
    }
}
