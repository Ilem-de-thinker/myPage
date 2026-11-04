<?php

namespace App\Shared\Support;

final class StorefrontMode
{
    public const SUBDOMAIN = 'subdomain';

    public const PATH = 'path';

    public static function current(): string
    {
        return config('mypage.storefront_mode') === self::PATH ? self::PATH : self::SUBDOMAIN;
    }

    public static function usesPaths(): bool
    {
        return self::current() === self::PATH;
    }

    public static function prefix(): string
    {
        return 'store/{slug}';
    }
}
