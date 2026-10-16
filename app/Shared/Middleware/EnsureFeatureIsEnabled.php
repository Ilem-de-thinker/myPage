<?php

namespace App\Shared\Middleware;

use App\Domains\Billing\Services\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a public feature until it is switched on.
 *
 * Returns 404 rather than a "coming soon" page on purpose. A soft launch flag
 * that returns 503 tells the world the feature exists, and a directory with no
 * content in it looks broken rather than absent. A 404 is simply honest about
 * what is there.
 */
class EnsureFeatureIsEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Feature::enabled($feature), 404);

        return $next($request);
    }
}
