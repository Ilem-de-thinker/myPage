<?php

namespace App\Shared\Support;

use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Storage;

/**
 * The two seeded demo storefronts, resolved for the marketing pages.
 *
 * The landing page shows a miniature of each live storefront in a browser frame
 * and links into both. Rather than keeping a second, hand-written copy of that
 * content in the template — which is how the demo and the actual shop drift
 * apart — the frame is filled from the same pages the links point at.
 *
 * Every method returns null when the demo has not been seeded, so a fresh
 * install shows the hero without the frame rather than an empty browser window.
 */
final class DemoStorefronts
{
    /**
     * The service-booking demo: name, handle, location and its top listing.
     *
     * @return array{name: string, handle: string, location: string, image: ?string, listing: ?string, price: ?string, detail: ?string, meta: string, footnote: string, href: string}|null
     */
    public static function service(): ?array
    {
        $page = self::page(config('mypage.demo.service'), Theme::Service);

        if ($page === null) {
            return null;
        }

        $service = Service::query()
            ->with('staff')
            ->where('store_page_id', $page->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->first();

        return [
            'name' => $page->name,
            'handle' => self::handle($page),
            'location' => $page->locationLabel() ?? 'Nigeria',
            'image' => self::image($page->heroImage()),
            'listing' => $service?->name ?? 'Book a session',
            'price' => $service?->priceLabel(),
            'detail' => $service !== null && $service->hasDeposit()
                ? Money::format($service->depositKobo()).' Deposit'
                : null,
            'meta' => self::nextSlotLabel($service),
            'footnote' => $service?->durationLabel() ?? 'By appointment',
            'href' => StoreUrl::home($page),
        ];
    }

    /**
     * The retail demo: name, handle, location and its newest product.
     *
     * @return array{name: string, handle: string, location: string, image: ?string, listing: string, price: string, meta: string, href: string}|null
     */
    public static function store(): ?array
    {
        $page = self::page(config('mypage.demo.store'), Theme::Store);

        if ($page === null) {
            return null;
        }

        $product = Product::query()
            ->with('images')
            ->active()
            ->where('store_page_id', $page->id)
            ->latest()
            ->first();

        return [
            'name' => $page->name,
            'handle' => self::handle($page),
            'location' => $page->locationLabel() ?? 'Nigeria',
            'image' => $product?->primary_image_url,
            'listing' => $product?->name ?? $page->name,
            'price' => $product?->price_label ?? Money::format(0),
            'meta' => $product?->stock_qty !== null
                ? $product->stock_qty.' in stock'
                : 'Dispatches in 24 hours',
            // The buyer on the notification card is a real one, so the card shows
            // a real name from a real order rather than an invented customer.
            'customer' => Order::query()
                ->where('store_page_id', $page->id)
                ->whereNotNull('customer_name')
                ->latest()
                ->value('customer_name'),
            'href' => StoreUrl::home($page),
        ];
    }

    /**
     * The published page for a demo slug, or null when the demo is not seeded.
     */
    private static function page(?string $slug, Theme $theme): ?StorePage
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return StorePage::query()
            ->where('slug', $slug)
            ->where('theme', $theme)
            ->first();
    }

    /**
     * The public handle, which is the slug and nothing else.
     */
    private static function handle(StorePage $page): string
    {
        return 'mypage.ng/'.$page->slug;
    }

    /**
     * A stored path turned into a URL, or null when the seller uploaded nothing.
     */
    private static function image(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::url($path);
    }

    /**
     * The next bookable slot for a service, in the seller's own timezone.
     *
     * Read from AvailabilityService rather than formatted from today: a landing
     * page that quotes an appointment time nobody can book is the same class of
     * lie as a page that quotes a price nobody can buy from.
     */
    private static function nextSlotLabel(?Service $service): string
    {
        if ($service === null) {
            return 'By appointment';
        }

        $availability = app(AvailabilityService::class);
        $timezone = $availability->timezoneFor($service);

        // Fourteen days is the widest window that still terminates quickly on a
        // page nobody is waiting on, and covers a weekly rota plus a weekend.
        for ($offset = 1; $offset <= 14; $offset++) {
            $slots = $availability->slotsFor($service, now($timezone)->addDays($offset));

            if ($slots->isNotEmpty()) {
                return $slots->first()->format('D, d M · g:i A');
            }
        }

        return 'Fully booked';
    }
}
