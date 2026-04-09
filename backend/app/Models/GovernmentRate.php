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
        return $query
            // Null min_salary is treated as 0 for non-bracket/manual rows.
            ->where(function ($q) use ($salary) {
                $q->whereNull('min_salary')
                    ->orWhere('min_salary', '<=', $salary);
            })
            // Null max_salary is open-ended.
            ->where(function ($q) use ($salary) {
                $q->whereNull('max_salary')
                    ->orWhere('max_salary', '>=', $salary);
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
        // Use effectiveOn() only (which handles date filtering) instead of active() + effectiveOn()
        // to avoid conflicts when computing contributions for past payroll periods
        $rate = static::where('is_active', true)
            ->byType($type)
            ->forSalary($monthlySalary)
            ->effectiveOn($date)
            ->orderBy('effective_date', 'desc')
            ->orderByRaw('COALESCE(min_salary, 0) DESC')
            ->first();

        if (!$rate) {
            return [
                'employee' => 0,
                'employer' => 0,
                'total' => 0,
            ];
        }

        // Use employee_fixed if it's set and > 0, otherwise calculate from rate
        $employeeContribution = ($rate->employee_fixed !== null && (float)$rate->employee_fixed > 0)
            ? (float)$rate->employee_fixed
            : ($monthlySalary * ($rate->employee_rate / 100));
        $employerContribution = ($rate->employer_fixed !== null && (float)$rate->employer_fixed > 0)
            ? (float)$rate->employer_fixed
            : ($monthlySalary * ($rate->employer_rate / 100));

        // Use total_contribution if specified (for fixed brackets).
        // If either side is missing, infer it from total to avoid returning zero.
        if ($rate->total_contribution !== null) {
            $totalContribution = (float) $rate->total_contribution;

            $employeeFixed = $rate->employee_fixed !== null
                ? (float) $rate->employee_fixed
                : null;
            $employerFixed = $rate->employer_fixed !== null
                ? (float) $rate->employer_fixed
                : null;

            if ($employeeFixed === null && $employerFixed === null) {
                $employeeFixed = round($totalContribution / 2, 2);
                $employerFixed = round($totalContribution - $employeeFixed, 2);
            } elseif ($employeeFixed === null) {
                $employeeFixed = round(max($totalContribution - $employerFixed, 0), 2);
            } elseif ($employerFixed === null) {
                $employerFixed = round(max($totalContribution - $employeeFixed, 0), 2);
            }

            return [
                'employee' => $employeeFixed,
                'employer' => $employerFixed,
                'total' => round($totalContribution, 2),
            ];
        }

        return [
            'employee' => round($employeeContribution, 2),
            'employer' => round($employerContribution, 2),
            'total' => round($employeeContribution + $employerContribution, 2),
        ];
    }

    /**
     * Calculate withholding tax using active dynamic tax rows from Government Rates.
     *
     * Supports both table styles configured in UI:
     * - Progressive base + marginal on excess (employee_fixed + employee_rate)
     * - Flat percentage per bracket (employee_rate only)
     *
     * @param float $taxableIncome
     * @param mixed $date Effective date used to pick active rows.
     * @param bool $splitSemiMonthly Split computed monthly tax by 2 for semi-monthly payrolls.
     */
    public static function calculateTaxForIncome(float $taxableIncome, $date = null, bool $splitSemiMonthly = false): float
    {
        if ($taxableIncome <= 0) {
            return 0;
        }

        $effectiveDate = Carbon::parse($date ?? now())->toDateString();

        static $cachedTaxBracketsByDate = [];
        if (!array_key_exists($effectiveDate, $cachedTaxBracketsByDate)) {
            $cachedTaxBracketsByDate[$effectiveDate] = static::query()
                ->where('is_active', true)
                ->where('type', 'tax')
                ->where('effective_date', '<=', $effectiveDate)
                ->where(function ($q) use ($effectiveDate) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $effectiveDate);
                })
                ->orderBy('effective_date', 'desc')
                ->orderBy('min_salary', 'asc')
                ->get();
        }

        $taxBrackets = $cachedTaxBracketsByDate[$effectiveDate];
        $taxBracket = $taxBrackets->first(function ($bracket) use ($taxableIncome) {
            $minSalary = (float) ($bracket->min_salary ?? 0);
            $maxSalary = $bracket->max_salary !== null ? (float) $bracket->max_salary : null;

            return $taxableIncome >= $minSalary
                && ($maxSalary === null || $taxableIncome <= $maxSalary);
        });

        if (!$taxBracket) {
            return 0;
        }

        $baseTax = (float) ($taxBracket->employee_fixed ?? 0);
        $marginalRate = (float) ($taxBracket->employee_rate ?? 0);
        $bracketMin = (float) ($taxBracket->min_salary ?? 0);
        $excess = max($taxableIncome - $bracketMin, 0);

        $hasBaseTax = $taxBracket->employee_fixed !== null && $baseTax > 0;
        $hasMarginalRate = $taxBracket->employee_rate !== null && $marginalRate > 0;

        $taxAmount = 0;
        if ($hasBaseTax && $hasMarginalRate) {
            $taxAmount = $baseTax + ($excess * ($marginalRate / 100));
        } elseif ($hasBaseTax) {
            $taxAmount = $baseTax;
        } elseif ($hasMarginalRate) {
            $taxBracketEffectiveDate = Carbon::parse($taxBracket->effective_date)->toDateString();
            $hasLowerBaseBracket = $taxBrackets->contains(function ($bracket) use ($bracketMin, $taxBracketEffectiveDate) {
                if (Carbon::parse($bracket->effective_date)->toDateString() !== $taxBracketEffectiveDate) {
                    return false;
                }

                return (float) ($bracket->min_salary ?? 0) < $bracketMin
                    && (float) ($bracket->employee_fixed ?? 0) > 0;
            });

            $taxableBase = $hasLowerBaseBracket ? $excess : $taxableIncome;
            $taxAmount = $taxableBase * ($marginalRate / 100);
        }

        if ($taxAmount <= 0 && $taxBracket->total_contribution !== null) {
            $taxAmount = (float) $taxBracket->total_contribution;
        }

        if ($taxAmount <= 0) {
            return 0;
        }

        if ($splitSemiMonthly) {
            $taxAmount /= 2;
        }

        return round($taxAmount, 2);
    }
}
