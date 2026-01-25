<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Holiday extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'date',
        'type',
        'description',
        'is_recurring',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this holiday
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this holiday
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope to get only active holidays
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get holidays by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get holidays in date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope to get holidays for a specific year
     */
    public function scopeForYear($query, $year)
    {
        return $query->whereYear('date', $year);
    }

    /**
     * Check if a given date is a holiday
     */
    public static function isHoliday($date)
    {
        $date = Carbon::parse($date);
        return static::active()
            ->whereDate('date', $date)
            ->exists();
    }

    /**
     * Get holiday for a given date
     */
    public static function getHolidayForDate($date)
    {
        $date = Carbon::parse($date);
        return static::active()
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * Calculate holiday pay multiplier based on holiday type and day of week
     * Regular holiday: rate * 2
     * Regular holiday on Sunday: rate * 2 * 1.3
     * Special holiday (8hrs): rate * 2 * 1.3
     */
    public function getPayMultiplier($date = null)
    {
        $checkDate = $date ? Carbon::parse($date) : Carbon::parse($this->date);
        $isSunday = $checkDate->isSunday();

        if ($this->type === 'regular') {
            return $isSunday ? 2.6 : 2.0; // 2 * 1.3 = 2.6 for Sunday
        } elseif ($this->type === 'special') {
            return 2.6; // Special holidays are paid at 2 * 1.3 for 8 hours
        }

        return 1.0; // Default
    }

    /**
     * Get formatted holiday type
     */
    public function getFormattedTypeAttribute()
    {
        return ucfirst($this->type) . ' Holiday';
    }
}
