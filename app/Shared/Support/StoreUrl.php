<?php

namespace App\Shared\Support;

use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Services\SlugResolver;
use Illuminate\Support\Facades\URL;

final class StoreUrl
{
    /**
     * The shop's canonical address, for links from anywhere else.
     *
     * Always the subdomain, even when a seller also has a custom domain: this is
     * the address the platform can guarantee, and the one a directory card can
     * point at without knowing which domain the seller owns.
     */
    public static function home(StorePage $page): string
    {
        return self::absolute($page, 'storefront.show');
    }

    public static function homeForSlug(string $slug): string
    {
        return route('storefront.show', ['slug' => $slug]);
    }

    /**
     * An absolute canonical link to any storefront route.
     *
     * Canonical links use the platform subdomain. In-store navigation should
     * use path() instead, so a buyer who arrived through a custom domain stays
     * on that domain.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function absolute(StorePage $page, string $name, array $parameters = []): string
    {
        return route($name, [...$parameters, 'slug' => $page->slug]);
    }

    /**
     * A link inside a shop, left relative.
     *
     * A storefront is served from {store}.{domain} and, when the seller owns one,
     * from their own domain too. An absolute link would pick one of those addresses
     * and walk the buyer off the other, so navigation within a shop stays on the
     * host they arrived on. The slug is still required: it is the route's domain
     * parameter, replaced before the host is stripped.
     *
     * @param  array<string, mixed>|string  $parameters
     */
    public static function path(string $name, array|string $parameters = []): string
    {
        return route($name, $parameters, absolute: false);
    }

    public static function homePath(StorePage $page): string
    {
        return self::path('storefront.show', ['slug' => $page->slug]);
    }

    /**
     * A platform page, named on the apex even when rendered inside a shop.
     *
     * Non-domain routes inherit the host of the current request, which from a
     * storefront is the seller's host — and platform paths 404 there. APP_URL is
     * the apex by definition, and the launch gate already requires it to be right.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function platform(string $name, array $parameters = []): string
    {
        return rtrim((string) config('app.url'), '/').self::path($name, $parameters);
    }

    /**
     * An absolute link to a storefront route, on the host the buyer is already on.
     *
     * Used where the URL leaves the application and comes back — a gateway
     * callback, primarily. A custom domain must survive that round trip, so the
     * original host is preferred when it is genuinely bound to this shop. Anything
     * else falls back to the canonical subdomain rather than trusting the input.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function onCurrentHost(StorePage $page, string $name, array $parameters = []): string
    {
        $request = request();

        if (! $request) {
            return route($name, $parameters);
        }

        $host = (string) $request->server('ORIGINAL_HOST', $request->getHost());
        $canonical = StorefrontMode::usesPaths()
            ? (string) config('mypage.domain')
            : $page->slug.'.'.config('mypage.domain');

        $isShopHost = $host === $canonical
            || app(SlugResolver::class)->resolveByHost($host)?->is($page) === true;

        if (! $isShopHost) {
            $host = $canonical;
        }

        return $request->getScheme().'://'.$host.self::path($name, $parameters);
    }

    public static function preview(StorePage $page, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('storefront.show', now()->addMinutes($minutes), ['slug' => $page->slug]);
    }

    public static function forSeller(StorePage $page): string
    {
        return $page->isPublished() ? self::home($page) : self::preview($page);
    }
}
