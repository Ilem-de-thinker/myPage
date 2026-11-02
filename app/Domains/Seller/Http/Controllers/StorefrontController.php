<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Analytics\Services\RecordStoreEvent;
use App\Domains\Booking\Actions\RecordServiceView;
use App\Domains\Booking\Actions\RescheduleBooking;
use App\Domains\Booking\Enums\ServiceSort;
use App\Domains\Booking\Exceptions\OutsideCancellationWindow;
use App\Domains\Booking\Exceptions\SlotUnavailable;
use App\Domains\Booking\Http\Requests\ServiceFiltersRequest;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Booking\Services\CancellationPolicy;
use App\Domains\Booking\Services\ServiceCatalogQuery;
use App\Domains\Catalog\Actions\RecordProductView;
use App\Domains\Catalog\Actions\SubmitReview;
use App\Domains\Catalog\Enums\ProductSort;
use App\Domains\Catalog\Http\Requests\ProductFiltersRequest;
use App\Domains\Catalog\Http\Requests\StoreReviewRequest;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\ProductCatalogQuery;
use App\Domains\Orders\Actions\GenerateWhatsAppOrderLink;
use App\Domains\Orders\Actions\RecordAbandonedCart;
use App\Domains\Orders\Exceptions\InvalidCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Actions\RecordStorePageView;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\BlockRenderer;
use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Support\StorePolicies;
use App\Shared\Support\Money;
use App\Shared\Support\PhoneNumber;
use App\Shared\Support\StoreUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function show(StorePage $page, BlockRenderer $blocks, RecordStorePageView $views, RecordStoreEvent $events, Request $request): View
    {
        // The home page's reviews block reads approved rows off the page, so they
        // are loaded here rather than lazily from inside the block — which has no
        // idea which queries it owns.
        $page->load(['reviews' => fn ($query) => $query
            ->published()
            ->with('reviewable')
            ->latest('published_at')
            ->limit(20)]);

        // Counted here and not in middleware: the storefront group also serves
        // products, cart and checkout, and a visit that never reaches the home
        // page is not what this counter is measuring. The event decides whether
        // this is a new visitor for today, so a duplicate visit does not move
        // the discovery counter either.
        if ($events->handle($page, 'view', $request)) {
            $views->handle($page);
        }

        return view("themes.{$page->theme->value}.page", [
            'page' => $page,
            // Resolved here, not in the template: the order is the theme's and
            // empty blocks have already been dropped.
            'blocks' => $blocks->forPage($page, 'home'),
            'products' => $page->theme === Theme::Store
                // category as well as images: the product card prints the
                // category name, and lazy loading is off in production, so an
                // un-eager-loaded relation is a 500 on the home page rather than
                // one extra query.
                ? Product::query()->with(['images', 'category'])->active()->where('store_page_id', $page->id)->latest()->limit(8)->get()
                : null,
        ]);
    }

    /**
     * The merchant's own policy pages. A seller can override any section through
     * settings.policies; otherwise the store's contact and delivery details fill
     * in a plain-language default that makes clear the seller, not the platform,
     * is the merchant.
     */
    public function policy(StorePage $page, string $policy, StorePolicies $policies): View
    {
        abort_unless(in_array($policy, StorePolicies::slugs(), true), 404);

        return view('themes.shared.pages.store-policy', [
            'page' => $page,
            'policy' => $policies->forPage($page, $policy),
            'slug' => $policy,
        ]);
    }

    public function about(StorePage $page, BlockRenderer $blocks): View
    {
        return view("themes.{$page->theme->value}.about", [
            'page' => $page,
            'blocks' => $blocks->forPage($page, 'about'),
        ]);
    }

    /**
     * The contact page, which is also where a customer reviews the business.
     *
     * The reviews are eager-loaded here, approved-only, rather than lazily from
     * the block: the block is rendered on both themes and has no idea which
     * queries it is responsible for.
     */
    public function contact(StorePage $page, BlockRenderer $blocks): View
    {
        $page->load(['reviews' => fn ($query) => $query
            ->published()
            ->with('reviewable')
            ->latest('published_at')
            ->limit(20)]);

        return view("themes.{$page->theme->value}.contact", [
            'page' => $page,
            'blocks' => $blocks->forPage($page, 'contact'),
        ]);
    }

    /**
     * The store's catalogue, filtered.
     *
     * The query lives in ProductCatalogQuery so the sidebar's price bounds and
     * category counts are derived from the same filters as the rows. Writing the
     * chain inline here meant the bounds and the results were two independent
     * pieces of logic that agreed only until one of them changed.
     */
    public function products(StorePage $page, ProductFiltersRequest $filters, ProductCatalogQuery $catalog): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        return view('themes.store.products', [
            'page' => $page,
            'products' => $catalog->paginate($page, $filters),
            'categories' => $catalog->categoriesWithCounts($page, $filters),
            'priceBounds' => $catalog->priceBounds($page),
            'sorts' => ProductSort::cases(),
            'filters' => $filters,
            'totalProducts' => $catalog->totalProducts($page),
        ]);
    }

    public function search(StorePage $page, Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        abort_if($query === '' || mb_strlen($query) > 120, 422, 'Enter a search term.');

        $products = Product::query()->active()->where('store_page_id', $page->id)->search($query)->with('images')->limit(12)->get()->map(fn (Product $product): array => [
            'type' => 'product', 'name' => $product->name, 'slug' => $product->slug, 'description' => Str::limit(strip_tags((string) $product->description), 140), 'price' => $product->priceLabel, 'original_price' => $product->isOnSale() ? Money::format($product->originalPriceKobo()) : null, 'url' => StoreUrl::path('storefront.product', ['slug' => $page->slug, 'productSlug' => $product->slug]),
        ]);
        $services = $page->theme === Theme::Service
            ? Service::withoutGlobalScopes()->where('store_page_id', $page->id)->where('is_active', true)->where(fn ($builder) => $builder->where('name', 'like', '%'.$query.'%')->orWhere('description', 'like', '%'.$query.'%'))->limit(12)->get()->map(fn (Service $service): array => [
                'type' => 'service', 'name' => $service->name, 'slug' => $service->slug, 'description' => Str::limit(strip_tags((string) $service->description), 140), 'price' => $service->priceWithUnitLabel(), 'original_price' => $service->isOnSale() ? Money::format($service->originalPriceKobo()) : null, 'url' => StoreUrl::path('storefront.service', ['slug' => $page->slug, 'serviceSlug' => $service->slug]),
            ])
            : collect();

        return response()->json(['query' => $query, 'mode' => 'keyword_fallback', 'results' => $products->concat($services)->values()]);
    }

    public function productShow(StorePage $page, string $productSlug, RecordProductView $views, RecordStoreEvent $events, Request $request): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        $product = Product::query()
            ->with(['images', 'category', 'variants'])
            ->active()
            ->where('store_page_id', $page->id)
            ->where('slug', $productSlug)
            ->firstOrFail();

        if ($events->handle($page, 'product_view', $request, ['product_id' => $product->id])) {
            $views->handle($product);
        }

        // Eager-loaded, and only the approved ones. The scope is here rather than
        // in the view so no template can be handed a pending row by accident —
        // and no template has to remember to check.
        $product->load(['reviews' => fn ($query) => $query
            ->published()
            ->with('reviewable')
            ->latest('published_at')
            ->limit(20)]);

        $related = Product::query()
            ->with(['images', 'category'])
            ->active()
            ->where('store_page_id', $page->id)
            ->whereKeyNot($product->id)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->latest()
            ->limit(4)
            ->get();

        return view('themes.store.product-show', compact('page', 'product', 'related'));
    }

    /**
     * Leave a review of the business itself, rather than of one product or one
     * service.
     *
     * This is the one that carries the most weight and the one most likely to be
     * abuse, because it is about the person rather than the thing. It lands on
     * the contact page, which is where somebody who has dealt with the business
     * already is, and it is rate-limited like the others.
     */
    public function reviewBusiness(StorePage $page, StoreReviewRequest $request, SubmitReview $submit): RedirectResponse
    {
        $submit->handle($page, $page, $request->reviewData());

        return back()
            ->withInput($request->except(['body', 'rating']))
            ->with('review_sent', 'Thank you. Your review will appear once the seller has read it.');
    }

    /**
     * Leave a review on a product.
     *
     * Always lands as pending and always says so. Silently accepting a review and
     * showing it later, or accepting it and quietly dropping it, are both worse
     * than a sentence telling the person it will appear once the seller has read
     * it.
     */
    public function reviewProduct(StorePage $page, string $productSlug, StoreReviewRequest $request, SubmitReview $submit): RedirectResponse
    {
        abort_unless($page->theme === Theme::Store, 404);

        $product = Product::query()
            ->active()
            ->where('store_page_id', $page->id)
            ->where('slug', $productSlug)
            ->firstOrFail();

        $submit->handle($page, $product, $request->reviewData());

        return back()
            ->withInput($request->except(['body', 'rating']))
            ->with('review_sent', 'Thank you. Your review will appear once the seller has read it.');
    }

    /**
     * Leave a review on a service.
     *
     * A paused service still accepts reviews: someone who had it bookmarked is
     * exactly the person most likely to come back and review it, and the service
     * being temporarily unavailable is not a reason to lose their words. It is
     * not rendered anywhere while paused, so the review simply waits.
     */
    public function reviewService(StorePage $page, string $serviceSlug, StoreReviewRequest $request, SubmitReview $submit): RedirectResponse
    {
        abort_unless($page->theme === Theme::Service, 404);

        $service = Service::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('slug', $serviceSlug)
            ->firstOrFail();

        $submit->handle($page, $service, $request->reviewData());

        return back()
            ->withInput($request->except(['body', 'rating']))
            ->with('review_sent', 'Thank you. Your review will appear once the seller has read it.');
    }

    /**
     * The service directory, filtered.
     *
     * `$blocks` still comes from the seller: the page's own intro copy is content
     * they wrote, and a filter cannot replace it.
     */
    public function services(StorePage $page, BlockRenderer $blocks, ServiceFiltersRequest $filters, ServiceCatalogQuery $catalog): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        return view('themes.service.services', [
            'page' => $page,
            'blocks' => $blocks->forPage($page, 'services'),
            'services' => $catalog->paginate($page, $filters),
            'filters' => $filters,
            'sorts' => ServiceSort::cases(),
            'durationOptions' => ServiceFiltersRequest::durationOptions(),
            'units' => $catalog->availableUnits($page, $filters),
            'priceBounds' => $catalog->priceBounds($page),
            'tiers' => $catalog->tierBreakdown($page, $filters),
            'totalServices' => $catalog->totalServices($page),
        ]);
    }

    public function serviceShow(StorePage $page, string $serviceSlug, BlockRenderer $blocks, RecordServiceView $views, RecordStoreEvent $events, Request $request): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        $service = $this->bookableServices($page)->firstWhere('slug', $serviceSlug);

        abort_if($service === null, 404);

        if ($events->handle($page, 'service_view', $request, ['service_id' => $service->id])) {
            $views->handle($service);
        }

        $service->load(['reviews' => fn ($query) => $query
            ->published()
            ->with('reviewable')
            ->latest('published_at')
            ->limit(20)]);

        return view('themes.service.service-show', [
            'page' => $page,
            'blocks' => $blocks->forPage($page, 'service-show'),
            'service' => $service,
        ]);
    }

    /**
     * The services a visitor is allowed to see and book.
     *
     * Only active ones. A paused service stays in the seller's dashboard but
     * must not be linkable, or someone could share a URL to something that
     * cannot be booked.
     *
     * @return Collection<int, Service>
     */
    private function bookableServices(StorePage $page): Collection
    {
        return Service::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('is_active', true)
            // The booking widget asks which people can do each service, so this
            // belongs in the same query rather than one per row.
            ->with('staff')
            ->orderBy('position')
            ->get();
    }

    public function cart(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        return view('themes.store.cart', ['page' => $page]);
    }

    public function checkout(StorePage $page): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        // The checkout shows the shop's own areas and nothing else. A shop that
        // never configured delivery keeps the free-text address it had before.
        $profile = $page->deliveryProfile()->with('zones')->first();

        return view('themes.store.checkout', [
            'page' => $page,
            'deliveryProfile' => $profile,
            'deliveryZones' => $profile?->zones ?? collect(),
        ]);
    }

    /**
     * Remember the cart the moment the buyer gives us a way to reach them.
     *
     * Fired when the email field on checkout loses focus. Prices come from
     * PriceCart, never from the request, so a tampered payload cannot invent an
     * order the seller would then chase.
     */
    public function captureCart(StorePage $page, Request $request, RecordAbandonedCart $capture): JsonResponse
    {
        abort_unless($page->theme === Theme::Store, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        // An abandoned cart the seller cannot contact is worse than no record at
        // all: it would sit on their list with no way to follow it up.
        if (blank($data['email'] ?? null) && blank($data['phone'] ?? null)) {
            return response()->json([
                'message' => 'We need an email address or a phone number before we can save this cart.',
                'errors' => ['email' => ['An email address or a phone number is required.']],
            ], 422);
        }

        $cart = array_map(fn (array $item) => [
            'product_id' => $item['id'],
            'variant_id' => $item['variant_id'] ?? null,
            'quantity' => $item['qty'],
        ], $data['items']);

        try {
            $abandoned = $capture->handle($page, $cart, [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
        } catch (InvalidCart $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'reference' => $abandoned->reference,
            'total_kobo' => $abandoned->total_kobo,
        ]);
    }

    public function book(StorePage $page, BlockRenderer $blocks): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        $services = $this->bookableServices($page);

        // Staff are optional and only worth a filter when there is more than
        // one, which is what the widget checks before drawing the select.
        $staff = $services->first()?->staff ?? collect();

        return view('themes.service.booking', [
            'page' => $page,
            'blocks' => $blocks->forPage($page, 'booking'),
            'services' => $services,
            'staff' => $staff,
        ]);
    }

    public function whatsappOrder(StorePage $page, Request $request, GenerateWhatsAppOrderLink $whatsappOrder, RecordStoreEvent $events): JsonResponse
    {
        abort_unless($page->theme === Theme::Store, 404);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $cart = array_map(fn (array $item) => [
            'product_id' => $item['id'],
            'variant_id' => $item['variant_id'] ?? null,
            'quantity' => $item['qty'],
        ], $data['items']);

        try {
            $events->handle($page, 'whatsapp_tap', $request);
            // The order is saved before the buyer is sent to WhatsApp so the seller
            // has a pending record with the same reference the buyer quotes.
            $result = $whatsappOrder->handle($page, $cart, [
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
        } catch (InvalidCart $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json([
            'reference' => $result['reference'],
            'total_kobo' => $result['total_kobo'],
            'total' => $result['total'],
            'url' => $result['url'],
        ]);
    }

    /**
     * Let a buyer find their order with the reference from their receipt.
     *
     * The reference alone is not enough. Anyone can guess or be handed one, so
     * the buyer also has to give the email or phone the order was placed with.
     * A wrong guess returns the same "we could not find that order" as a
     * reference that does not exist, so this cannot be used to probe for orders.
     */
    public function trackOrder(StorePage $page, Request $request): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        $reference = trim((string) $request->query('reference', ''));
        $contact = trim((string) $request->query('contact', ''));
        $searched = $reference !== '' && $contact !== '';

        $order = null;

        if ($searched) {
            $order = Order::withoutGlobalScopes()
                ->where('store_page_id', $page->getKey())
                ->where('reference', $reference)
                ->get()
                ->first(fn (Order $candidate) => $this->contactMatches($candidate, $contact));
        }

        return view('themes.store.order-tracking', compact('page', 'order', 'reference', 'contact', 'searched'));
    }

    /**
     * Let a client find a booking they already made.
     *
     * The mirror of trackOrder, and for the same reason. A reference on its own
     * is not a secret — it is printed on a receipt and read out on WhatsApp — so
     * the contact the booking was made with is required too. A wrong guess
     * returns the same "we could not find that booking" as a reference that does
     * not exist, which is what stops this from being a way to enumerate other
     * people's appointments.
     */
    public function manageBooking(StorePage $page, Request $request): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        $reference = trim((string) $request->query('reference', ''));
        $contact = trim((string) $request->query('contact', ''));
        $searched = $reference !== '' && $contact !== '';

        $booking = null;

        if ($searched) {
            $booking = Booking::withoutGlobalScopes()
                ->with(['service', 'staffMember'])
                ->where('store_page_id', $page->getKey())
                ->where('reference', $reference)
                ->get()
                ->first(fn (Booking $candidate) => $this->contactMatches($candidate, $contact));
        }

        return view('themes.service.manage-booking', [
            'page' => $page,
            'booking' => $booking,
            'reference' => $reference,
            'contact' => $contact,
            'searched' => $searched,
            'viaLink' => false,
        ]);
    }

    public function rescheduleBookingForm(
        StorePage $page,
        Request $request,
        AvailabilityService $availability,
        CancellationPolicy $policy,
    ): View|RedirectResponse {
        abort_unless($page->theme === Theme::Service, 404);
        $booking = $this->findBooking($page, $request);

        if (! $booking) {
            return redirect(StoreUrl::path('storefront.manage-booking', ['slug' => $page->slug]));
        }

        $timezone = $availability->timezoneFor($booking->service);
        $date = (string) $request->query('date', $booking->starts_at->timezone($timezone)->toDateString());
        $staff = $request->query('staff') ? $booking->storePage->staffMembers()->find($request->query('staff')) : $booking->staffMember;
        $slots = $availability->slotsFor($booking->service, Carbon::parse($date, $timezone)->startOfDay(), $staff, (int) config('mypage.booking_slot_step_minutes', 30))
            ->reject(fn (Carbon $slot): bool => $slot->equalTo($booking->starts_at->setTimezone($timezone)))
            ->map(fn (Carbon $slot): array => ['starts_at' => $slot->toIso8601String(), 'label' => $slot->format('g:i A')])
            ->values();

        return view('themes.service.manage-booking', [
            'page' => $page,
            'booking' => $booking,
            'reference' => $request->query('reference'),
            'contact' => $request->query('contact'),
            'searched' => true,
            'reschedule' => true,
            'slots' => $slots,
            'selectedDate' => $date,
            'locked' => $policy->isLocked($booking),
            'hoursUntil' => $policy->hoursUntil($booking),
            'windowHours' => $policy->windowHoursFor($page),
        ]);
    }

    public function rescheduleBooking(
        StorePage $page,
        Request $request,
        RescheduleBooking $reschedule,
    ): RedirectResponse {
        abort_unless($page->theme === Theme::Service, 404);
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:40'],
            'contact' => ['required', 'string', 'max:160'],
            'starts_at' => ['required', 'date'],
            'staff' => ['nullable', 'integer'],
        ]);
        $booking = $this->findBooking($page, $request);

        if (! $booking) {
            return redirect(StoreUrl::path('storefront.manage-booking', ['slug' => $page->slug]));
        }

        try {
            $staff = ! empty($data['staff']) ? $booking->storePage->staffMembers()->find($data['staff']) : $booking->staffMember;
            $reschedule->handle($booking, Carbon::parse($data['starts_at']), null, $staff);
        } catch (OutsideCancellationWindow|SlotUnavailable $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        return redirect(StoreUrl::path('storefront.manage-booking', [
            'slug' => $page->slug,
            'reference' => $booking->reference,
            'contact' => $data['contact'],
        ]))->with('status', 'Your appointment was moved.');
    }

    /**
     * An order, through the buyer's own signed link.
     *
     * Renders the same screen the reference lookup does, with the record already
     * resolved — one screen, two ways in, so a buyer who bookmarked their link and a
     * buyer who types their reference are looking at the same thing.
     *
     * The `signed` middleware has already proved the link was minted by this
     * platform and has not expired. What is left to check is that the reference
     * belongs to *this* studio, because the slug is part of the URL and a link can
     * be edited.
     */
    public function orderByLink(StorePage $page, string $reference): View
    {
        abort_unless($page->theme === Theme::Store, 404);

        $order = Order::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('reference', $reference)
            ->firstOrFail();

        return view('themes.store.order-tracking', [
            'page' => $page,
            'order' => $order,
            'reference' => $reference,
            'contact' => null,
            'searched' => true,
            // Reached by a link that already carries its own authorisation, so
            // the lookup form is noise rather than help.
            'viaLink' => true,
        ]);
    }

    /**
     * A booking, through the client's own signed link.
     *
     * The mirror of orderByLink. Public and unthrottled because the signature is
     * the gate and a valid one is unguessable; the lookup it replaces is the
     * thing this was added to stop people having to do.
     */
    public function bookingByLink(StorePage $page, string $reference): View
    {
        abort_unless($page->theme === Theme::Service, 404);

        $booking = Booking::withoutGlobalScopes()
            ->with(['service', 'staffMember'])
            ->where('store_page_id', $page->getKey())
            ->where('reference', $reference)
            ->firstOrFail();

        return view('themes.service.manage-booking', [
            'page' => $page,
            'booking' => $booking,
            'reference' => $reference,
            'contact' => null,
            'searched' => true,
            'viaLink' => true,
        ]);
    }

    /**
     * Does this contact belong to the order?
     *
     * Phone numbers are compared normalised, because the same buyer types
     * "08031234567" on the form and "+2348031234567" on the receipt.
     */
    private function contactMatches(Order|Booking $record, string $contact): bool
    {
        if (Str::lower(trim($record->customer_email ?? '')) === Str::lower($contact)) {
            return true;
        }

        $given = PhoneNumber::normalize($contact);

        return $given !== null && $given === PhoneNumber::normalize($record->customer_phone);
    }

    private function findBooking(StorePage $page, Request $request): ?Booking
    {
        $reference = trim((string) $request->input('reference', $request->query('reference', '')));
        $contact = trim((string) $request->input('contact', $request->query('contact', '')));

        if ($reference === '' || $contact === '') {
            return null;
        }

        return Booking::withoutGlobalScopes()
            ->with(['service', 'staffMember', 'storePage'])
            ->where('store_page_id', $page->getKey())
            ->where('reference', $reference)
            ->get()
            ->first(fn (Booking $candidate): bool => $this->contactMatches($candidate, $contact));
    }
}
