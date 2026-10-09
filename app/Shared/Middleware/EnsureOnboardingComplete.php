<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->storePages()->doesntExist()) {
            return redirect()->route('onboarding.show', 'business');
        }

        return $next($request);
    }
}
