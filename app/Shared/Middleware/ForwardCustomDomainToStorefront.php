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
 * So on one of our own domains the request is re-issued with the seller's slug
 * put back in front of the path. That is exactly the request the storefront
 * already knows how to answer, and it means there is one implementation of every
 * shop page rather than a second one maintained in parallel.
 *
 * Anything the storefront has no answer for — /login, /dashboard — has no slug
 * route to fall back on and 404s, which is right: those are platform pages and
 * they are not reachable from a shop's domain.
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

        $forwarded = Request::create(
            '/'.$page->slug.$request->getPathInfo(),
            $request->getMethod(),
            $request->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
        );

        $forwarded->headers->replace($request->headers->all());
        $forwarded->setUserResolver($request->getUserResolver());
        $forwarded->setRouteResolver(fn () => Route::getRoutes()->match($forwarded));

        return Route::dispatch($forwarded);
    }
}
