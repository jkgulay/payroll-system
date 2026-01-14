<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class GovernmentRate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'min_salary',
        'max_salary',
        'employee_rate',
        'employer_rate',
        'employee_fixed',
        'employer_fixed',
        'total_contribution',
        'effective_date',
        'end_date',
        'is_active',
        'sort_order',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'employee_rate' => 'decimal:4',
        'employer_rate' => 'decimal:4',
        'employee_fixed' => 'decimal:2',
        'employer_fixed' => 'decimal:2',
        'total_contribution' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForSalary($query, $salary)
    {
        return $query->where(function ($q) use ($salary) {
            $q->where(function ($subQ) use ($salary) {
                $subQ->where('min_salary', '<=', $salary)
                    ->where('max_salary', '>=', $salary);
            })->orWhere(function ($subQ) use ($salary) {
                $subQ->where('min_salary', '<=', $salary)
                    ->whereNull('max_salary');
            });
        });
    }

    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ?? now();
        return $query->where('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    // Helper methods
    public static function getContributionForSalary($type, $monthlySalary, $date = null)
    {
        $rate = static::active()
            ->byType($type)
            ->forSalary($monthlySalary)
            ->effectiveOn($date)
            ->orderBy('effective_date', 'desc')
            ->first();

        if (!$rate) {
            return [
                'employee' => 0,
                'employer' => 0,
                'total' => 0,
            ];
        }

        $employeeContribution = $rate->employee_fixed ?? ($monthlySalary * ($rate->employee_rate / 100));
        $employerContribution = $rate->employer_fixed ?? ($monthlySalary * ($rate->employer_rate / 100));

        // Use total_contribution if specified (for fixed brackets)
        if ($rate->total_contribution) {
            return [
                'employee' => $rate->employee_fixed ?? 0,
                'employer' => $rate->employer_fixed ?? 0,
                'total' => $rate->total_contribution,
            ];
        }

        return [
            'employee' => round($employeeContribution, 2),
            'employer' => round($employerContribution, 2),
            'total' => round($employeeContribution + $employerContribution, 2),
        ];
    }
}
