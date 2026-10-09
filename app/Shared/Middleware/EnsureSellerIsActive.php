<?php

namespace App\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isActive()) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account is suspended.']);
        }

        return $next($request);
    }
}
