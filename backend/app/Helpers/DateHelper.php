<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Get current date in Y-m-d format
     * Standardized format for database date fields
     */
    public static function today(): string
    {
        return Carbon::now()->format('Y-m-d');
    }

    /**
     * Get current datetime
     * For timestamp fields
     */
    public static function now(): Carbon
    {
        return Carbon::now();
    }

    /**
     * Format date to Y-m-d (database format)
     */
    public static function toDateString($date): ?string
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse($date)->format('Y-m-d');
    }

    /**
     * Format datetime to Y-m-d H:i:s (database timestamp format)
     */
    public static function toDateTimeString($date): ?string
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date->format('Y-m-d H:i:s');
        }

        return Carbon::parse($date)->format('Y-m-d H:i:s');
    }

    /**
     * Parse any date format to Carbon instance
     */
    public static function parse($date): ?Carbon
    {
        if (!$date) {
            return null;
        }

        if ($date instanceof Carbon) {
            return $date;
        }

        return Carbon::parse($date);
    }

    /**
     * Get date n days ago
     */
    public static function daysAgo(int $days): string
    {
        return Carbon::now()->subDays($days)->format('Y-m-d');
    }

    /**
     * Get date n days from now
     */
    public static function daysFromNow(int $days): string
    {
        return Carbon::now()->addDays($days)->format('Y-m-d');
    }

    /**
     * Get date n years ago
     */
    public static function yearsAgo(int $years): string
    {
        return Carbon::now()->subYears($years)->format('Y-m-d');
    }

    /**
     * Check if date is in the past
     */
    public static function isPast($date): bool
    {
        return Carbon::parse($date)->isPast();
    }

    /**
     * Check if date is in the future
     */
    public static function isFuture($date): bool
    {
        return Carbon::parse($date)->isFuture();
    }

    /**
     * Get difference in days between two dates
     */
    public static function daysBetween($date1, $date2): int
    {
        $d1 = self::parse($date1);
        $d2 = self::parse($date2);
        return $d1->diffInDays($d2);
    }

    /**
     * Format date for display (human-readable)
     */
    public static function formatForDisplay($date, string $format = 'M d, Y'): ?string
    {
        if (!$date) {
            return null;
        }

        return Carbon::parse($date)->format($format);
    }
}
