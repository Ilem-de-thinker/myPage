<?php

namespace App\Shared\Support;

final class Html
{
    private const ALLOWED = '<h2><h3><p><br><strong><em><ul><ol><li><a><img>';

    public static function sanitize(?string $html): string
    {
        if (! $html) {
            return '';
        }

        $clean = strip_tags($html, self::ALLOWED);

        return $clean;
    }
}
