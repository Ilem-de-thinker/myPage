<?php

namespace App\Shared\Support;

use App\Domains\Seller\Models\StorePage;

/**
 * The networks a Nigerian small business actually gets found on.
 *
 * Sellers type a handle, not a URL. "adatextiles" should become a link to their
 * profile, and "instagram.com/adatextiles" should too, because people paste both.
 * Anything we cannot confidently turn into a link is dropped rather than rendered
 * as-is: a javascript: href or a stray quote in a footer is not worth the risk of
 * trusting a seller's typing.
 */
final class SocialLinks
{
    /**
     * platform => [label, icon key, host, accepts '@' on the front]
     *
     * @var array<string, array{label: string, host: string, at: bool}>
     */
    private const PLATFORMS = [
        'instagram' => ['label' => 'Instagram', 'host' => 'https://www.instagram.com/', 'at' => false],
        'facebook' => ['label' => 'Facebook', 'host' => 'https://www.facebook.com/', 'at' => false],
        'tiktok' => ['label' => 'TikTok', 'host' => 'https://www.tiktok.com/', 'at' => true],
        'x' => ['label' => 'X', 'host' => 'https://x.com/', 'at' => false],
        'youtube' => ['label' => 'YouTube', 'host' => 'https://www.youtube.com/', 'at' => true],
        'linkedin' => ['label' => 'LinkedIn', 'host' => 'https://www.linkedin.com/company/', 'at' => false],
    ];

    /**
     * @return list<string>
     */
    public static function platforms(): array
    {
        return array_keys(self::PLATFORMS);
    }

    public static function label(string $platform): string
    {
        return self::PLATFORMS[$platform]['label'] ?? ucfirst($platform);
    }

    /**
     * The links worth showing, in a fixed order.
     *
     * Order is ours rather than the seller's input order so two shops with the
     * same networks render identically. Dropped, not hidden: an unusable handle
     * should leave no gap in the footer.
     *
     * @return list<array{platform: string, label: string, url: string}>
     */
    public static function forPage(StorePage $page): array
    {
        $links = [];

        foreach (self::PLATFORMS as $platform => $definition) {
            $url = self::url($platform, $page->social_links[$platform] ?? null);

            if ($url !== null) {
                $links[] = ['platform' => $platform, 'label' => $definition['label'], 'url' => $url];
            }
        }

        return $links;
    }

    /**
     * Turn whatever the seller typed into an absolute https URL, or null.
     *
     * @return array<string, string> platform => normalised URL
     */
    public static function normalise(?array $input): array
    {
        $clean = [];

        foreach ($input ?? [] as $platform => $value) {
            if (! isset(self::PLATFORMS[$platform])) {
                continue;
            }

            $url = self::url($platform, is_string($value) ? $value : null);

            if ($url !== null) {
                $clean[$platform] = $url;
            }
        }

        return $clean;
    }

    /**
     * Accepts a bare handle, an @handle, or a full profile URL on that platform.
     *
     * Only the platform's own host is accepted for a pasted URL. Someone typing
     * "instagram.com" into the TikTok box is either confused or trying to get a
     * link of ours pointing somewhere we did not expect.
     */
    private static function url(string $platform, ?string $value): ?string
    {
        $definition = self::PLATFORMS[$platform] ?? null;

        if ($definition === null || blank($value)) {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '@')) {
            $value = substr($value, 1);
        }

        // A pasted profile URL: keep the last path segment, which is the handle.
        if (str_contains($value, '/')) {
            $host = parse_url($value, PHP_URL_HOST);

            if ($host === null || ! str_contains(strtolower($host), strtolower(parse_url($definition['host'], PHP_URL_HOST) ?? ''))) {
                return null;
            }

            $segments = array_values(array_filter(explode('/', parse_url($value, PHP_URL_PATH) ?: '')));
            $value = (string) end($segments);
        }

        // An @ pulled out of a path is still a handle, not a character to reject.
        if (str_starts_with($value, '@')) {
            $value = substr($value, 1);
        }

        // Handles are not URLs. Anything with a scheme, a space or a control
        // character is not a handle, whatever the platform is.
        if ($value === '' || preg_match('#[^\pL\pN._-]#u', $value) === 1) {
            return null;
        }

        if (mb_strlen($value) > 64) {
            return null;
        }

        return $definition['host'].($definition['at'] ? '@' : '').rawurlencode($value);
    }
}
