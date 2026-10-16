<?php

namespace App\Shared\Middleware;

use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Services\SlugResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie as CookieJar;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class ResolveStorePage
{
    private const PREVIEW_COOKIE_TTL_MINUTES = 60;

    public function __construct(private SlugResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug');
        $request->route()->forgetParameter('slug');

        $page = $this->resolve($request, $slug);

        abort_if(! $page, 404);

        // A custom-domain request has no slug in the path, so the page's own slug
        // stands in for it. Preview cookies are named after the page either way,
        // which is what keeps a deep link working after the first redirect.
        $slug ??= $page->slug;

        $hasPreviewGrant = $request->hasValidSignature() || $this->hasPreviewCookie($request, $slug);

        abort_unless($page->isPublished() || $hasPreviewGrant, 404);

        if (! $page->isPublished() && $request->hasValidSignature()) {
            CookieJar::queue($this->previewCookie($request, $slug));
        }

        app()->instance(StorePage::class, $page);
        View::share('page', $page);

        try {
            return $next($request);
        } finally {
            app()->forgetInstance(StorePage::class);
            View::share('page', null);
        }
    }

    /**
     * Which shop is this request for?
     *
     * A host that is one of ours always wins, because on a seller's own domain
     * the hostname is the address and there is nothing in the path to trust. A
     * mypage.ng request falls through to the slug in the path.
     *
     * The host lookup only ever returns a page whose domain has been verified by
     * DNS, so an unclaimed hostname resolves to nothing rather than to whichever
     * shop registered first.
     */
    private function resolve(Request $request, ?string $slug): ?StorePage
    {
        $byHost = $this->resolver->resolveByHost($request->getHost());

        if ($byHost !== null) {
            return $byHost;
        }

        return $slug === null ? null : $this->resolver->resolveBySlug($slug);
    }

    /**
     * A signed preview URL only authorises the request it is on, so the grant is
     * carried in a cookie to keep deep links (products, cart, contact) working.
     */
    private function hasPreviewCookie(Request $request, string $slug): bool
    {
        return $request->cookie($this->previewCookieName($slug)) === $request->ip();
    }

    private function previewCookie(Request $request, string $slug): Cookie
    {
        // The value stays the plain address. EncryptCookies encrypts the response
        // once on its way out, and EncryptCookies would null a value that arrived
        // unencrypted: so this is encrypted by the framework, never by us.
        return CookieJar::make($this->previewCookieName($slug), $request->ip())
            ->withPath('/');
    }

    private function previewCookieName(string $slug): string
    {
        return 'preview_'.$slug;
    }
}
