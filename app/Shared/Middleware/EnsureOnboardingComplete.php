<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->user();

        if ($seller === null || $seller->isAdmin()) {
            return $next($request);
        }

        if ($seller->storePages()->doesntExist()) {
            $skippedToDashboard = $request->routeIs('dashboard') && $seller->onboarding_skipped_at !== null;

            if (! $skippedToDashboard) {
                return redirect()->route('onboarding.show', 'business');
            }
        }

        return $next($request);
    }
}
