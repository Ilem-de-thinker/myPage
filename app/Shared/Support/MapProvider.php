<?php

namespace App\Shared\Support;

use App\Domains\Admin\Models\PlatformSetting;

/**
 * Which map the platform renders, and how to build its URLs.
 *
 * OpenStreetMap is the default because it needs no key and no billing account.
 * The admin can choose Google, but an installed app without a browser key falls
 * back rather than rendering a broken picker; that fallback is resolved here so
 * every caller agrees on which provider is actually in use.
 */
class MapProvider
{
    public const OPENSTREETMAP = 'openstreetmap';

    public const GOOGLE = 'google';

    /**
     * The value the admin saved, whether or not it can be honoured.
     */
    public static function selection(): string
    {
        $stored = PlatformSetting::value('platform.map_provider', self::OPENSTREETMAP);

        return $stored === self::GOOGLE ? self::GOOGLE : self::OPENSTREETMAP;
    }

    /**
     * The provider that will actually render.
     */
    public static function provider(): string
    {
        if (self::selection() === self::GOOGLE && self::googleKey() !== null) {
            return self::GOOGLE;
        }

        return self::OPENSTREETMAP;
    }

    public static function isGoogle(): bool
    {
        return self::provider() === self::GOOGLE;
    }

    public static function label(): string
    {
        return self::isGoogle() ? 'Google Maps' : 'OpenStreetMap';
    }

    /**
     * The key is optional: without it Google is selected but cannot render.
     */
    public static function googleKey(): ?string
    {
        $key = config('services.google.maps_browser_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * An embeddable map for a contact page, or null when there is no pin.
     *
     * Google can find a place by name; the OSM export iframe cannot, so a
     * seller with no coordinates simply gets no map rather than a wrong one.
     */
    public static function embedUrl(?float $latitude, ?float $longitude, string $query = ''): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        if (self::isGoogle()) {
            return 'https://www.google.com/maps/embed/v1/place?key='.urlencode((string) self::googleKey())
                .'&q='.rawurlencode($query !== '' ? $query : $latitude.','.$longitude);
        }

        $bbox = implode(',', [
            $longitude - 0.012,
            $latitude - 0.008,
            $longitude + 0.012,
            $latitude + 0.008,
        ]);

        return 'https://www.openstreetmap.org/export/embed.html?bbox='.rawurlencode($bbox)
            .'&layer=mapnik&marker='.rawurlencode($latitude.','.$longitude);
    }

    /**
     * A link out to the full map, used when a visitor wants directions.
     */
    public static function externalUrl(?float $latitude, ?float $longitude, string $query = ''): ?string
    {
        if (self::isGoogle()) {
            return $query !== ''
                ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query)
                : null;
        }

        if ($latitude === null || $longitude === null) {
            return $query !== ''
                ? 'https://www.openstreetmap.org/search?query='.rawurlencode($query)
                : null;
        }

        return 'https://www.openstreetmap.org/?mlat='.rawurlencode((string) $latitude)
            .'&mlon='.rawurlencode((string) $longitude)
            .'#map=16/'.$latitude.'/'.$longitude;
    }
}
