<?php

namespace App\Shared\Middleware;

use App\Domains\Seller\Services\SlugResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a seller's shop when the request arrives on their own domain.
 *
 * The storefront and the seller dashboard share one path space. On mypage.ng
 * that never comes up, because a shop lives at /{slug}/... and the dashboard
 * lives at /products. On a seller's own domain the slug is gone from the path,
 * so /products is ambiguous, and whichever group was registered first wins.
 * The dashboard was, which meant a customer landing on shop.com/products got
 * bounced to the seller login screen.
 *
 * So on a seller's own domain the request is re-issued against the shop's
 * canonical subdomain. The storefront routes are domain-constrained, so rewriting
 * the host is what makes the request match them, and it keeps the address the
 * buyer left as an alias rather than a second copy of every page.
 *
 * Anything the storefront has no answer for — /login, /dashboard — finds no
 * route on the tenant host and 404s via EnsureApexHost, which is right: those are
 * platform pages and they are not reachable from a shop's domain.
 */
class ForwardCustomDomainToStorefront
{
    public function __construct(private SlugResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $page = $this->resolver->resolveByHost($request->getHost());

        // Not one of ours: this really is the platform, and the apex routes
        // below it are the correct answer.
        if ($page === null) {
            return $next($request);
        }

        $canonical = $page->slug.'.'.config('mypage.domain');

        $server = $request->server->all();
        $server['HTTP_HOST'] = $canonical;
        $server['SERVER_NAME'] = $canonical;

        $forwarded = Request::create(
            $request->getScheme().'://'.$canonical.$request->getRequestUri(),
            $request->getMethod(),
            $request->request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
        );

        $forwarded->headers->replace($request->headers->all());
        $forwarded->headers->set('HOST', $canonical);
        $forwarded->server->set('REQUEST_URI', $request->getRequestUri());

        // Keep the address the buyer actually used. The gateway callback is built
        // from it so a custom-domain buyer is returned to that domain rather than
        // walked onto the subdomain. Set here, after the request arrived, so a
        // client cannot forge it.
        $forwarded->server->set('ORIGINAL_HOST', $request->getHost());
        $forwarded->setUserResolver($request->getUserResolver());
        $forwarded->setRouteResolver(fn () => Route::getRoutes()->match($forwarded));

        return Route::dispatch($forwarded);
    }
}
