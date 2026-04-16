<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CompanySettingService;
use Illuminate\Http\Request;

class PayrollConfigController extends Controller
{
    public function __construct(private CompanySettingService $settings)
    {
        $this->middleware('role:admin');
    }

    public function show()
    {
        return response()->json($this->buildConfig());
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'overtime.regularDay' => 'required|numeric|min:0',
            'overtime.sunday' => 'required|numeric|min:0',
            'overtime.dailyCap.positionIds' => 'nullable|array',
            'overtime.dailyCap.positionIds.*' => 'integer|exists:position_rates,id',
            'overtime.dailyCap.employeeIds' => 'nullable|array',
            'overtime.dailyCap.employeeIds.*' => 'integer|exists:employees,id',
            'holidays.regularHoliday' => 'required|numeric|min:0',
            'holidays.regularHolidaySunday' => 'required|numeric|min:0',
            'holidays.specialHoliday' => 'required|numeric|min:0',
        ]);

        $dailyCapPositionIds = $this->normalizeIdArray(
            data_get($validated, 'overtime.dailyCap.positionIds', [])
        );
        $dailyCapEmployeeIds = $this->normalizeIdArray(
            data_get($validated, 'overtime.dailyCap.employeeIds', [])
        );

        $this->settings->set(
            'payroll.overtime.regularDay',
            $validated['overtime']['regularDay'],
            'number',
            'payroll',
            'Regular day overtime multiplier'
        );
        $this->settings->set(
            'payroll.overtime.sunday',
            $validated['overtime']['sunday'],
            'number',
            'payroll',
            'Sunday overtime multiplier'
        );
        $this->settings->set(
            'payroll.overtime.dailyCap.positionIds',
            $dailyCapPositionIds,
            'json',
            'payroll',
            'Positions limited to 2-hour maximum daily overtime'
        );
        $this->settings->set(
            'payroll.overtime.dailyCap.employeeIds',
            $dailyCapEmployeeIds,
            'json',
            'payroll',
            'Employees limited to 2-hour maximum daily overtime'
        );
        $this->settings->set(
            'payroll.holidays.regularHoliday',
            $validated['holidays']['regularHoliday'],
            'number',
            'payroll',
            'Regular holiday overtime multiplier'
        );
        $this->settings->set(
            'payroll.holidays.regularHolidaySunday',
            $validated['holidays']['regularHolidaySunday'],
            'number',
            'payroll',
            'Regular holiday Sunday overtime multiplier'
        );
        $this->settings->set(
            'payroll.holidays.specialHoliday',
            $validated['holidays']['specialHoliday'],
            'number',
            'payroll',
            'Special holiday overtime multiplier'
        );

        return response()->json($this->buildConfig());
    }

    private function buildConfig(): array
    {
        return [
            'overtime' => [
                'regularDay' => (float) $this->settings->get(
                    'payroll.overtime.regularDay',
                    (float) config('payroll.overtime.regular_multiplier', 1.25)
                ),
                'sunday' => (float) $this->settings->get(
                    'payroll.overtime.sunday',
                    1.3
                ),
                'dailyCap' => [
                    'hours' => 2,
                    'positionIds' => $this->normalizeIdArray(
                        $this->settings->get('payroll.overtime.dailyCap.positionIds', [])
                    ),
                    'employeeIds' => $this->normalizeIdArray(
                        $this->settings->get('payroll.overtime.dailyCap.employeeIds', [])
                    ),
                ],
            ],
            'holidays' => [
                'regularHoliday' => (float) $this->settings->get(
                    'payroll.holidays.regularHoliday',
                    1.3
                ),
                'regularHolidaySunday' => (float) $this->settings->get(
                    'payroll.holidays.regularHolidaySunday',
                    1.3
                ),
                'specialHoliday' => (float) $this->settings->get(
                    'payroll.holidays.specialHoliday',
                    1.3
                ),
            ],
        ];
    }

    private function normalizeIdArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
