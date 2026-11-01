<?php

namespace App\Domains\Shared\Middleware;

use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Models\StorePage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an expired merchant's storefront readable but not sellable.
 *
 * Taking the whole page down punishes buyers who already have a link, and
 * mutating the seller's store status would fight with admin moderation. Instead
 * the catalogue stays visible, a notice is shown, and the commerce endpoints
 * refuse new orders until the subscription is restored.
 */
class EnsureStoreIsOpen
{
    /**
     * The public endpoints that create a sale or booking. Everything else under
     * the storefront stays available, including search, chat, reviews and events.
     *
     * @var list<string>
     */
    private const COMMERCE_ROUTES = [
        'checkout.store',
        'storefront.checkout.whatsapp',
        'storefront.checkout.capture',
        'storefront.booking.store',
        'storefront.booking.whatsapp',
        'storefront.booking-reschedule.store',
    ];

    public function __construct(private readonly SubscriptionAvailability $availability) {}

    public function handle(Request $request, Closure $next): Response
    {
        $page = app()->bound(StorePage::class) ? app(StorePage::class) : null;

        if ($page === null || ! $this->availability->enabled()) {
            View::share('storeReadOnly', false);

            return $next($request);
        }

        $subscription = $page->seller?->subscriptions()->first();
        $readOnly = $subscription !== null && ! $subscription->canWrite();

        View::share('storeReadOnly', $readOnly);

        if (! $readOnly || $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->routeIs(self::COMMERCE_ROUTES)) {
            abort(402, 'This shop is temporarily not accepting new orders. Please check back soon.');
        }

        return $next($request);
    }
}
