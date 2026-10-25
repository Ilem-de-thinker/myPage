<?php

namespace App\Shared\Middleware;

use App\Shared\Support\Hostname;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform pages live on the apex, not on a tenant's subdomain.
 *
 * A shop now owns {store}.{domain}. If a dashboard or auth route answered there,
 * it would either render our screens under a seller's address or start a second,
 * host-only session that looks signed in and is not. Anything the storefront does
 * not answer on a tenant host is therefore a 404.
 *
 * A custom domain never reaches this: ForwardCustomDomainToStorefront answers it
 * first and dispatches the storefront instead.
 */
class EnsureApexHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = Hostname::withoutWww(Hostname::normalise($request->getHost()) ?? $request->getHost());
        $apex = Hostname::withoutWww(strtolower(trim((string) config('mypage.domain'))));

        if ($host !== '' && $host !== $apex && str_ends_with($host, '.'.$apex)) {
            abort(404);
        }

        return $next($request);
    }
}
