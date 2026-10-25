<?php

namespace App\Shared\Support;

final class Color
{
    /**
     * The lightest and darkest ink a brand colour can carry.
     *
     * Near-black rather than pure black: it is the page ink, so a button label
     * matches the text sitting next to it.
     */
    private const INK_LIGHT = '#ffffff';

    private const INK_DARK = '#15171b';

    /**
     * The ink that can be read on the seller's brand colour.
     *
     * Picked by measured contrast, never by a brightness formula. A YIQ
     * threshold gets this wrong for exactly the mid-tone colours that look like
     * a good brand choice: it picks white on #16A34A (3.30:1, an AA failure on
     * the platform's own default) and on #B8860B (3.25:1), where dark ink reads
     * at 6.4:1 and 4.8:1 instead.
     */
    public static function contrast(string $hex): string
    {
        return self::contrastRatio($hex, self::INK_LIGHT) >= self::contrastRatio($hex, self::INK_DARK)
            ? self::INK_LIGHT
            : self::INK_DARK;
    }

    /**
     * WCAG relative luminance.
     */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::linearChannels($hex);

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrastRatio(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        $lighter = max($first, $second);
        $darker = min($first, $second);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * The brand colour as OKLCH components, for building every other token in
     * the theme from one hue.
     *
     * Mixing a colour toward white by a percentage is what makes brand systems
     * fall apart: in linear light, yellow mixes to mud. Anchoring lightness and
     * carrying only the hue and chroma forward keeps contrast predictable for
     * any colour a seller can choose.
     *
     * @return array{0: float, 1: float, 2: float} lightness, chroma, hue in degrees
     */
    public static function oklch(string $hex): array
    {
        [$r, $g, $b] = self::linearChannels($hex);

        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;

        // The cube roots of these sums are positive for any real sRGB colour:
        // the guard that browsers apply is for out-of-gamut values.
        $l = $l ** (1 / 3);
        $m = $m ** (1 / 3);
        $s = $s ** (1 / 3);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $b2 = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        $chroma = sqrt(($a * $a) + ($b2 * $b2));
        $hue = $chroma < 1e-6 ? 0.0 : fmod(rad2deg(atan2($b2, $a)) + 360, 360);

        return [max(0.0, min(1.0, $lightness)), $chroma, round($hue, 1)];
    }

    /**
     * The brand colour darkened enough to be read as text on paper.
     *
     * Sellers pick fills, not text colours, and the difference is not academic:
     * brand yellow is 1.68:1 on white. Chroma and hue are untouched, so the
     * colour still reads as the seller's own.
     */
    public static function textSafe(string $hex): string
    {
        [$lightness, $chroma, $hue] = self::oklch($hex);

        if (self::contrastRatio($hex, self::INK_LIGHT) >= 4.5) {
            return strtolower($hex);
        }

        /*
         * Contrast against a white page falls as lightness rises, so the
         * readable shades are all below the seller's own. Bisect for the
         * lightest of them: that keeps the hue and as much chroma as will
         * survive, so brand yellow reads as gold rather than as black.
         */
        $readable = 0.0;
        $unreadable = $lightness;

        for ($step = 0; $step < 24; $step++) {
            $middle = ($readable + $unreadable) / 2;

            if (self::contrastRatio(self::fromOklch($middle, $chroma, $hue), self::INK_LIGHT) >= 4.5) {
                $readable = $middle;

                continue;
            }

            $unreadable = $middle;
        }

        return self::fromOklch($readable, $chroma, $hue);
    }

    /**
     * Build a compact 50–900 scale that keeps the merchant hue while reducing
     * chroma at the extremes so every shade remains usable in UI surfaces.
     *
     * @return array<int, string>
     */
    public static function palette(string $hex): array
    {
        [$lightness, $chroma, $hue] = self::oklch($hex);
        $anchors = [50 => 0.97, 100 => 0.93, 200 => 0.86, 300 => 0.76, 400 => 0.65, 500 => $lightness, 600 => 0.45, 700 => 0.35, 800 => 0.25, 900 => 0.16];

        return collect($anchors)->mapWithKeys(fn (float $anchor, int $step): array => [
            $step => self::fromOklch($anchor, min($chroma, 0.16) * (1 - abs(0.5 - $anchor) * 0.65), $hue),
        ])->all();
    }

    /**
     * @return list<float> red, green, blue as 0-1 linear values
     */
    private static function linearChannels(string $hex): array
    {
        return array_map(
            fn (float $channel) => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
            self::channels($hex),
        );
    }

    /**
     * @return list<float> red, green, blue as 0-1 values
     */
    private static function channels(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [0.0, 0.0, 0.0];
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private static function fromOklch(float $lightness, float $chroma, float $hue): string
    {
        $a = $chroma * cos(deg2rad($hue));
        $b = $chroma * sin(deg2rad($hue));

        $l = ($lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        $red = 4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s;
        $green = -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s;
        $blue = -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s;

        $encode = static fn (float $channel) => (int) round(
            ($channel <= 0.0031308 ? $channel * 12.92 : 1.055 * ($channel ** (1 / 2.4)) - 0.055) * 255
        );

        return sprintf('#%02x%02x%02x', ...array_map(
            static fn (float $channel) => max(0, min(255, $encode($channel))),
            [$red, $green, $blue],
        ));
    }
}
