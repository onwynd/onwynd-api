<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Date Helper
 * Date and time utilities
 */
class DateHelper
{
    /**
     * Get start of period
     */
    public static function getStartOfPeriod($period = 'month')
    {
        return match ($period) {
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()
        };
    }

    /**
     * Get end of period
     */
    public static function getEndOfPeriod($period = 'month')
    {
        return match ($period) {
            'week' => now()->endOfWeek(),
            'month' => now()->endOfMonth(),
            'year' => now()->endOfYear(),
            default => now()
        };
    }

    /**
     * Get day name
     */
    public static function getDayName($dayOfWeek)
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return $days[$dayOfWeek] ?? 'Unknown';
    }

    /**
     * Check if date is in past
     */
    public static function isPast($date)
    {
        return Carbon::parse($date)->isPast();
    }

    /**
     * Check if date is in future
     */
    public static function isFuture($date)
    {
        return Carbon::parse($date)->isFuture();
    }

    /**
     * Get days until date
     */
    public static function daysUntil($date)
    {
        return now()->diffInDays(Carbon::parse($date));
    }

    /**
     * Format date for display
     */
    public static function formatForDisplay($date, $format = 'M d, Y')
    {
        return Carbon::parse($date)->format($format);
    }

    /**
     * Format time for display
     */
    public static function formatTimeForDisplay($time)
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:i A');
    }
}
