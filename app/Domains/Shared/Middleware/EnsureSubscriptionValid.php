<?php

namespace App\Domains\Shared\Middleware;

use App\Domains\Billing\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates dashboard writes on a live subscription.
 *
 * Deliberately narrow: it never touches the public storefront. A seller's page
 * stays up through a failed debit, because silently taking a shop offline is
 * worse than the unpaid month, and they keep trading while they sort it out.
 */
class EnsureSubscriptionValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->user();

        if ($seller === null) {
            return $next($request);
        }

        // Reads only: a lapsed seller must still be able to see their page,
        // export their orders and go and pay.
        if ($request->isMethodSafe() || $request->routeIs('billing.*')) {
            return $next($request);
        }

        $subscription = Subscription::with('plan')->where('seller_id', $seller->getKey())->first();

        // No subscription row at all means this seller predates billing, or has
        // not picked a plan yet. Fail open: blocking a seller who has never been
        // charged would be indefensible.
        if ($subscription === null || $subscription->canWrite()) {
            return $next($request);
        }

        // One warning, then they land on the page that explains it.
        if ($request->expectsJson()) {
            abort(402, 'Your subscription has lapsed. Renew it to keep selling.');
        }

        return redirect()
            ->route('billing.index')
            ->with('status', 'Your subscription has lapsed. Renew it to keep making changes.');
    }
}
