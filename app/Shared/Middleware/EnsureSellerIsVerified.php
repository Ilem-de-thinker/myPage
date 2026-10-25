<?php

namespace App\Shared\Middleware;

use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Seller\Models\Seller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retained for routes that may need a business-verification gate in a future
 * payment-specific flow. The seller dashboard deliberately does not use it:
 * business verification is a buyer-facing trust signal.
 *
 * Registered as 'business.verified', not 'verified'. Laravel already owns that
 * name and it means the seller's email address; this answers a different
 * question, and the two are applied together on the dashboard group.
 *
 * An admin looks at a CAC certificate or a national ID and stamps
 * store_pages.verified_at. Public storefronts use that value to explain the
 * seller's verification state before a buyer pays.
 *
 * Two states are deliberately not this middleware's problem:
 *
 *   - No store page yet. onboarding.complete owns a seller who has not finished
 *     the wizard, and verification is not something they can act on until they
 *     have something to verify.
 *   - The badge screen itself, and the onboarding wizard. Gating the page a
 *     blocked seller is sent to would loop forever.
 *
 * Fail to the badge screen rather than to a 403: the seller has an action to take,
 * and a bare refusal generates a support ticket instead of an application.
 */
class EnsureSellerIsVerified
{
    /**
     * Routes a blocked seller must still be able to reach.
     *
     * Both are excluded at their own route definition too, which is where a reader
     * looks. This list is the belt to that braces: applying business.verified to
     * either again is a redirect loop.
     *
     * @var list<string>
     */
    private const EXEMPT = [
        'badge.*',
        'onboarding.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $seller = $request->user();

        if ($seller === null || $this->isExempt($request)) {
            return $next($request);
        }

        $page = $seller->storePages()->first();

        if ($page === null || $page->verified_at !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Verify your business before you can use this part of the dashboard.');
        }

        return redirect()
            ->route('badge.apply')
            ->with('status', $this->message($seller));
    }

    private function isExempt(Request $request): bool
    {
        foreach (self::EXEMPT as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A seller whose application is already in the queue has done the thing we
     * asked of them. Telling them to apply again would be both wrong and the kind
     * of thing that makes people give up.
     */
    private function message(Seller $seller): string
    {
        $pending = BadgeApplication::where('seller_id', $seller->getKey())
            ->where('status', 'pending')
            ->exists();

        return $pending
            ? 'Your verification is with us. Everything else stays locked until we have looked at it.'
            : 'Verify your business to unlock your dashboard. It takes a CAC certificate or a national ID.';
    }
}
