<?php

namespace App\Domains\Admin\Middleware;

use App\Shared\Tenancy\CrossTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin is a flag on a seller rather than a separate guard, so one identity
 * means one set of credentials. Everything under /admin is also invisible to
 * search engines, which is deliberate for an internal operations panel.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->user();

        abort_unless($seller?->isAdmin(), 404);

        // From here the panel reads every seller's rows, not just the admin's.
        CrossTenant::enable();

        return $next($request);
    }
}
