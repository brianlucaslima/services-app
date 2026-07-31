<?php

declare(strict_types=1);

namespace App\Brain\Helpers;

class TimeHelper
{
    /**
     * Converts decimal hours to a human-readable format like "2h 30m" or "2h".
     */
    public static function decimalToHuman(string|float|int|null $decimal): string
    {
        if (empty($decimal)) {
            return '0h';
        }

        $decimal = (float) $decimal;
        $hours = floor($decimal);
        $minutes = (int) round(($decimal - $hours) * 60);

        if ($minutes === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$minutes}m";
    }

    /**
     * Parses user input (like "2:30", "2:20", "2.5") into decimal hours.
     */
    public static function humanToDecimal(string|float|int|null $value): float
    {
        if (empty($value)) {
            return 0.00;
        }

        $value = str_replace(' ', '', (string) $value);

        // If user input has a colon (e.g. "2:20" or "2:30")
        if (str_contains($value, ':')) {
            $parts = explode(':', $value);
            $hours = (int) $parts[0];
            $minutes = isset($parts[1]) ? (int) $parts[1] : 0;

            return $hours + ($minutes / 60);
        }

        // Default to plain float
        return (float) $value;
    }

    /**
     * Converts decimal hours to a colon format like "02:30" or "02:20".
     */
    public static function decimalToColon(string|float|int|null $decimal): string
    {
        if (empty($decimal)) {
            return '00:00';
        }

        $decimal = (float) $decimal;
        $hours = floor($decimal);
        $minutes = (int) round(($decimal - $hours) * 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
