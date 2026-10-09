<?php

namespace App\Shared\Support;

final class PhoneNumber
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $phone);
        $digits = ltrim($digits, '+');

        if (str_starts_with($digits, '234')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+234'.substr($digits, 1);
        }

        if (strlen($digits) === 10) {
            return '+234'.$digits;
        }

        return '+'.$digits;
    }

    public static function isValidNigerian(?string $phone): bool
    {
        return $phone !== null && (bool) preg_match('/^\+234\d{10}$/', self::normalize($phone) ?? '');
    }
}
