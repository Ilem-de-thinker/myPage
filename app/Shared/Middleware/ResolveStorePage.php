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

        $page = $this->resolver->resolveBySlug($slug);

        abort_if(! $page, 404);

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
     * A signed preview URL only authorises the request it is on, so the grant is
     * carried in a cookie to keep deep links (products, cart, contact) working.
     */
    private function hasPreviewCookie(Request $request, string $slug): bool
    {
        return $request->cookie($this->previewCookieName($slug)) === $request->ip();
    }

    private function previewCookie(Request $request, string $slug): Cookie
    {
        return CookieJar::make(
            $this->previewCookieName($slug),
            $request->ip(),
            self::PREVIEW_COOKIE_TTL_MINUTES * 60,
        )->withPath('/');
    }

    private function previewCookieName(string $slug): string
    {
        return 'preview_'.$slug;
    }
}
