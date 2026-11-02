<?php

namespace App\Domains\Auth\Http\Middleware;

use App\Domains\Auth\Services\PolicyConsent;
use App\Domains\Seller\Models\Seller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePoliciesAreCurrent
{
    public function __construct(private readonly PolicyConsent $consent) {}

    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->user();

        if ($seller instanceof Seller && $this->consent->pending($seller) !== []) {
            return redirect()->route('legal.consent');
        }

        return $next($request);
    }
}
