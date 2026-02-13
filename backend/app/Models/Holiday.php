<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
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
     * OPTIMIZATION: Clear holiday cache when holidays change
     */
    protected static function booted()
    {
        static::saved(function () {
            // Clear specific holiday caches (cache tags not available with file driver)
            Cache::forget('holidays');
            Cache::forget('active_holidays');
            // Clear year-specific caches
            for ($year = now()->year - 1; $year <= now()->year + 2; $year++) {
                Cache::forget("holidays_year_{$year}");
            }
        });

        static::deleted(function () {
            Cache::forget('holidays');
            Cache::forget('active_holidays');
            for ($year = now()->year - 1; $year <= now()->year + 2; $year++) {
                Cache::forget("holidays_year_{$year}");
            }
        });
    }

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
     * Includes both holidays with dates in the specified year
     * AND recurring holidays from other years (shown with the target year's date)
     */
    public function scopeForYear($query, $year)
    {
        return $query->where(function ($q) use ($year) {
            // Include holidays with dates in the specified year
            $q->whereYear('date', $year)
                // OR include recurring holidays from any year
                ->orWhere('is_recurring', true);
        });
    }

    /**
     * Check if a given date is a holiday
     * Supports recurring holidays (matches by month and day regardless of year)
     */
    public static function isHoliday($date)
    {
        $date = Carbon::parse($date);
        return static::active()
            ->where(function ($q) use ($date) {
                // Exact date match
                $q->whereDate('date', $date)
                    // OR recurring holiday matching month and day
                    ->orWhere(function ($q2) use ($date) {
                        $q2->where('is_recurring', true)
                            ->whereMonth('date', $date->month)
                            ->whereDay('date', $date->day);
                    });
            })
            ->exists();
    }

    /**
     * Get holiday for a given date
     * Supports recurring holidays (matches by month and day regardless of year)
     */
    public static function getHolidayForDate($date)
    {
        $date = Carbon::parse($date);
        return static::active()
            ->where(function ($q) use ($date) {
                // Exact date match
                $q->whereDate('date', $date)
                    // OR recurring holiday matching month and day
                    ->orWhere(function ($q2) use ($date) {
                        $q2->where('is_recurring', true)
                            ->whereMonth('date', $date->month)
                            ->whereDay('date', $date->day);
                    });
            })
            ->first();
    }

    /**
     * Calculate holiday pay multiplier based on holiday type and day of week
     * Regular holiday: rate * 2
     * Regular holiday on Sunday: rate * 2 * 1.3 = 2.6x
     * Special holiday: rate * 1.3
     * Special holiday on Sunday: rate * 1.3
     */
    public function getPayMultiplier($date = null)
    {
        $checkDate = $date ? Carbon::parse($date) : Carbon::parse($this->date);
        $isSunday = $checkDate->isSunday();

        if ($this->type === 'regular') {
            // Regular holiday: 200% of daily rate, +30% if on Sunday (rest day)
            return $isSunday ? 2.6 : 2.0;
        } elseif ($this->type === 'special') {
            // Special holiday: 130% of daily rate (same whether on Sunday or not)
            return 1.3;
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
