<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class SalaryAdjustment extends Model
{
    use HasFactory;

    protected static array $columnExistsCache = [];

    protected $fillable = [
        'employee_id',
        'amount',
        'type',
        'reason',
        'reference_period',
        'effective_date',
        'status',
        'applied_payroll_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    /**
     * Get the employee that owns the adjustment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the payroll where this adjustment was applied.
     */
    public function appliedPayroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class, static::appliedPayrollColumn());
    }

    /**
     * Get the user who created this adjustment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for pending adjustments.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for applied adjustments.
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied');
    }

    /**
     * Get the effective amount (negative for deductions, positive for additions)
     */
    public function getEffectiveAmountAttribute(): float
    {
        return $this->type === 'deduction' ? -abs((float) $this->amount) : abs((float) $this->amount);
    }

    /**
     * Accessor for description attribute (alias for reference_period for backwards compatibility)
     */
    public function getDescriptionAttribute(mixed $value): ?string
    {
        return $value ?? ($this->attributes['reference_period'] ?? null);
    }

    public function setDescriptionAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('description')) {
            $this->attributes['description'] = $value;
        }

        if (static::hasSchemaColumn('reference_period')) {
            $this->attributes['reference_period'] = $value;
        }
    }

    public function getReferencePeriodAttribute(mixed $value): ?string
    {
        return $value ?? ($this->attributes['description'] ?? null);
    }

    public function setReferencePeriodAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('reference_period')) {
            $this->attributes['reference_period'] = $value;
        }

        if (static::hasSchemaColumn('description')) {
            $this->attributes['description'] = $value;
        }
    }

    public function getTypeAttribute(mixed $value): ?string
    {
        return $value ?? ($this->attributes['adjustment_type'] ?? null);
    }

    public function setTypeAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('type')) {
            $this->attributes['type'] = $value;
        }

        if (static::hasSchemaColumn('adjustment_type')) {
            $this->attributes['adjustment_type'] = $value;
        }
    }

    public function getAdjustmentTypeAttribute(mixed $value): ?string
    {
        return $value ?? ($this->attributes['type'] ?? null);
    }

    public function setAdjustmentTypeAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('adjustment_type')) {
            $this->attributes['adjustment_type'] = $value;
        }

        if (static::hasSchemaColumn('type')) {
            $this->attributes['type'] = $value;
        }
    }

    public function getAppliedPayrollIdAttribute(mixed $value): ?int
    {
        if ($value !== null) {
            return (int) $value;
        }

        $legacyValue = $this->attributes['payroll_id'] ?? null;
        return $legacyValue !== null ? (int) $legacyValue : null;
    }

    public function setAppliedPayrollIdAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('applied_payroll_id')) {
            $this->attributes['applied_payroll_id'] = $value;
        }

        if (static::hasSchemaColumn('payroll_id')) {
            $this->attributes['payroll_id'] = $value;
        }
    }

    public function getPayrollIdAttribute(mixed $value): ?int
    {
        if ($value !== null) {
            return (int) $value;
        }

        $modernValue = $this->attributes['applied_payroll_id'] ?? null;
        return $modernValue !== null ? (int) $modernValue : null;
    }

    public function setPayrollIdAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('payroll_id')) {
            $this->attributes['payroll_id'] = $value;
        }

        if (static::hasSchemaColumn('applied_payroll_id')) {
            $this->attributes['applied_payroll_id'] = $value;
        }
    }

    public function setNotesAttribute(mixed $value): void
    {
        if (static::hasSchemaColumn('notes')) {
            $this->attributes['notes'] = $value;
        }
    }

    public static function referencePeriodColumn(): string
    {
        if (static::hasSchemaColumn('reference_period')) {
            return 'reference_period';
        }

        return 'description';
    }

    public static function adjustmentTypeColumn(): string
    {
        if (static::hasSchemaColumn('type')) {
            return 'type';
        }

        return 'adjustment_type';
    }

    public static function appliedPayrollColumn(): string
    {
        if (static::hasSchemaColumn('applied_payroll_id')) {
            return 'applied_payroll_id';
        }

        return 'payroll_id';
    }

    public static function notesColumn(): ?string
    {
        return static::hasSchemaColumn('notes') ? 'notes' : null;
    }

    private static function hasSchemaColumn(string $column): bool
    {
        if (!array_key_exists($column, static::$columnExistsCache)) {
            static::$columnExistsCache[$column] =
                Schema::hasTable('salary_adjustments')
                && Schema::hasColumn('salary_adjustments', $column);
        }

        return static::$columnExistsCache[$column];
    }
}
