<?php

namespace App\Shared\Support;

final class Money
{
    /**
     * Convert a human-entered naira amount to integer kobo.
     *
     * Handles currency symbols, comma separators, shorthand (15k, 1.5m), and
     * ranges ("15,000 - 20,000") by using the first amount as the lower bound.
     */
    public static function toKobo(string|float|int|null $naira): int
    {
        if ($naira === null || $naira === '') {
            return 0;
        }

        if (is_int($naira)) {
            return $naira * 100;
        }

        if (is_float($naira)) {
            return (int) round($naira * 100);
        }

        $value = mb_strtolower(trim($naira));
        $value = str_replace([',', '₦', 'ngn', ' '], '', $value);

        if (! preg_match('/(\d+(?:\.\d+)?)([km])?/', $value, $matches)) {
            return 0;
        }

        $amount = (float) $matches[1];
        $amount *= match ($matches[2] ?? '') {
            'k' => 1000,
            'm' => 1000000,
            default => 1,
        };

        return (int) round($amount * 100);
    }

    public static function format(int $kobo): string
    {
        return '₦'.number_format($kobo / 100, $kobo % 100 ? 2 : 0);
    }
}
