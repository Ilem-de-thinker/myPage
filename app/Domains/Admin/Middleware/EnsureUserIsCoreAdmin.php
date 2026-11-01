<?php

namespace App\Domains\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Core admins can change the platform itself — settings, plans, feature flags,
 * gateway connections, roles and grace overrides. Staff admins keep the
 * operational panel but must not reach these.
 *
 * The outer `admin` middleware has already run, so a non-admin never reaches
 * this and still gets the panel's deliberate 404 rather than a 403.
 */
class EnsureUserIsCoreAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isCoreAdmin(), 403);

        return $next($request);
    }
}
