<?php

namespace App\Shared\Support;

use App\Domains\Booking\Models\Booking;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\URL;

/**
 * The link a buyer keeps.
 *
 * Buyers on this platform never have an account, which means the usual way back
 * to "where is my order" — a login — does not exist. The order-tracking screen
 * settled for asking for a reference and a contact, and that is a weak answer:
 * the reference is printed on a receipt and read out on WhatsApp, so it is not
 * a secret, and it has to be retyped at every shop a buyer has ever used.
 *
 * The link itself is the capability. An unguessable, expiring URL needs no
 * account and no password, and it only ever opens one order or booking. These
 * are built on Laravel's signed URLs because there is nothing here to authorise
 * — only to show.
 *
 * The links expire. A link that never expires is a permanent public URL for
 * somebody's address and order history, which is not what a convenience is
 * worth. Thirty days is longer than any delivery or appointment.
 */
final class BuyerLinks
{
    /**
     * How long a buyer's own link stays good.
     *
     * Long enough to cover a delivery, a reschedule and a forgotten link; short
     * enough that a link forwarded by accident stops working eventually.
     */
    public const EXPIRES_DAYS = 30;

    public static function order(StorePage $page, Order $order, ?int $days = null): string
    {
        return URL::temporarySignedRoute('storefront.order.show', self::expiry($days), [
            'slug' => $page->slug,
            'reference' => $order->reference,
        ]);
    }

    public static function booking(StorePage $page, Booking $booking, ?int $days = null): string
    {
        return URL::temporarySignedRoute('storefront.booking.show', self::expiry($days), [
            'slug' => $page->slug,
            'reference' => $booking->reference,
        ]);
    }

    private static function expiry(?int $days): \DateTimeInterface
    {
        return now()->addDays($days ?? self::EXPIRES_DAYS);
    }
}
