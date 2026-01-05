<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log salary change for an employee
     */
    public static function logSalaryChange(Employee $employee, $oldSalary, $newSalary): self
    {
        return self::create([
            'user_id' => auth()->id(),
            'module' => 'employees',
            'action' => 'salary_changed',
            'description' => "Basic salary changed from ₱" . number_format($oldSalary, 2) .
                " to ₱" . number_format($newSalary, 2) .
                " for employee {$employee->employee_number} ({$employee->full_name})",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => ['basic_salary' => $oldSalary],
            'new_values' => ['basic_salary' => $newSalary],
        ]);
    }

    /**
     * Log position change (which may affect salary)
     */
    public static function logPositionChange(Employee $employee, $oldPosition, $newPosition, $oldSalary = null, $newSalary = null): self
    {
        $description = "Position changed from '{$oldPosition}' to '{$newPosition}'";
        $oldValues = ['position' => $oldPosition];
        $newValues = ['position' => $newPosition];

        if ($oldSalary !== null && $newSalary !== null && $oldSalary != $newSalary) {
            $description .= " (salary adjusted from ₱" . number_format($oldSalary, 2) .
                " to ₱" . number_format($newSalary, 2) . ")";
            $oldValues['basic_salary'] = $oldSalary;
            $newValues['basic_salary'] = $newSalary;
        }

        $description .= " for employee {$employee->employee_number} ({$employee->full_name})";

        return self::create([
            'user_id' => auth()->id(),
            'module' => 'employees',
            'action' => 'position_changed',
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }
}
