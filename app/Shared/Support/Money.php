<?php

namespace App\Shared\Support;

final class Money
{
    public static function toKobo(string|float|int $naira): int
    {
        return (int) round(((float) str_replace(',', '', (string) $naira)) * 100);
    }

    public static function format(int $kobo): string
    {
        return '₦'.number_format($kobo / 100, $kobo % 100 ? 2 : 0);
    }
}
