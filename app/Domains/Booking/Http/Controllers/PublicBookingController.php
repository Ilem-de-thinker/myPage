<?php

namespace App\Domains\Booking\Http\Controllers;

use App\Domains\Booking\Actions\CreateBooking;
use App\Domains\Booking\Actions\RequestBookingByWhatsApp;
use App\Domains\Booking\Exceptions\SlotUnavailable;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Payments\Exceptions\GatewayNotConfigured;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\ContactMatcher;
use App\Shared\Support\StoreUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * The buyer-facing half of the Service theme, alongside StorefrontController.
 */
class PublicBookingController
{
    /**
     * The slot list the booking widget renders, as JSON.
     *
     * Public and unauthenticated, so it is throttled: it is the one endpoint that
     * does repeated date-window queries.
     */
    public function availability(Request $request, AvailabilityService $availability): JsonResponse
    {
        $page = $this->page($request);

        $data = $request->validate([
            'service' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'staff' => ['nullable', 'integer'],
        ]);

        $service = $this->service($page, $data['service']);
        $staff = $this->staff($page, $data['staff'] ?? null);

        try {
            $date = Carbon::parse($data['date'], $availability->timezoneFor($service))->startOfDay();
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['date' => 'That date is not valid.']);
        }

        $slots = $availability
            ->slotsFor($service, $date, $staff, (int) config('mypage.booking_slot_step_minutes', 30))
            ->map(fn (Carbon $slot) => [
                // Sent as an ISO string with the store's offset, so the browser
                // does not have to guess what "11:00" meant.
                'starts_at' => $slot->toIso8601String(),
                'label' => $slot->format('g:i A'),
            ])
            ->values();

        return response()->json(['slots' => $slots]);
    }

    /**
     * Reserve a slot. Free services confirm outright; anything with a deposit
     * goes to the same payment flow the store theme uses.
     */
    public function store(
        Request $request,
        CreateBooking $createBooking,
        InitializePayment $initializePayment,
    ): RedirectResponse {
        $page = $this->page($request);

        $data = $request->validate([
            'service' => ['required', 'integer'],
            'staff' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = $this->service($page, $data['service']);
        $staff = $this->staff($page, $data['staff'] ?? null);

        $timezone = app(AvailabilityService::class)->timezoneFor($service);

        try {
            $startsAt = Carbon::parse($data['starts_at'], $timezone);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['starts_at' => 'That start time is not valid.']);
        }

        try {
            $booking = $createBooking->handle($service, $startsAt, $staff, [
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (SlotUnavailable $exception) {
            throw ValidationException::withMessages(['starts_at' => $exception->getMessage()]);
        }

        if ($booking->amountDueKobo() === 0) {
            return redirect(StoreUrl::path('storefront.booking-confirmation', [
                'slug' => $page->slug,
                'booking' => $booking->reference,
                'contact' => $data['email'] ?? $data['phone'],
            ]))
                ->with('status', 'Booked. We have your slot.');
        }

        /*
         * The slot is held and the customer has committed to it, so a gateway
         * that is down is our failure to report, not theirs to stare at. The
         * hold expires on its own if they cannot pay, and WhatsApp is the way
         * this business would normally take the booking anyway.
         */
        try {
            $checkoutUrl = $initializePayment->handle($booking, $data['email'] ?? $data['phone']);
        } catch (GatewayNotConfigured) {
            return back()
                ->withInput()
                ->withErrors([
                    'starts_at' => 'This business has not connected a payment provider yet, so it cannot take deposits. Your slot is held for the next 15 minutes — message us on WhatsApp instead.',
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'starts_at' => 'We could not reach the payment provider. Your slot is held for the next 15 minutes — try again, or message us on WhatsApp.',
                ]);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * The free path for a visitor who would rather message than pay. Nothing is
     * held: the seller confirms the slot by hand from their inbox.
     */
    public function requestWhatsApp(
        Request $request,
        RequestBookingByWhatsApp $requestBooking,
        AvailabilityService $availability,
    ): JsonResponse {
        $page = $this->page($request);

        $data = $request->validate([
            'service' => ['required', 'integer'],
            'staff' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = $this->service($page, $data['service']);
        $staff = $this->staff($page, $data['staff'] ?? null);

        try {
            $startsAt = Carbon::parse($data['starts_at'], $availability->timezoneFor($service));
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['starts_at' => 'That start time is not valid.']);
        }

        $result = $requestBooking->handle(
            $service,
            $startsAt,
            $staff,
            $data,
        );

        return response()->json([
            'reference' => $result['booking']->reference,
            'url' => $result['url'],
        ]);
    }

    /**
     * The receipt, straight after paying.
     *
     * Reached with a reference and the contact the booking was made with — a
     * reference alone is a string anybody can be handed, so possession of it is
     * not proof of purchase. Arriving without a match sends the visitor to the
     * lookup page rather than revealing anything. The alternative door is the
     * signed /b/{reference} link issued at booking time.
     */
    public function confirmation(Request $request): View|RedirectResponse
    {
        $page = $this->page($request);

        $reference = (string) $request->query('booking', '');

        $booking = $reference === '' ? null : Booking::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('reference', $reference)
            ->first();

        $contact = (string) $request->query('contact', '');

        if ($booking === null || ! ContactMatcher::matches($booking, $contact)) {
            // Same answer as the lookup page: no booking, no details. A booking
            // reference alone is a string anybody can be handed, so the receipt
            // asks for the same contact the booking was made with.
            return redirect(StoreUrl::path('storefront.manage-booking', ['slug' => $page->slug]));
        }

        return view('themes.service.booking-confirmation', [
            'page' => $page,
            'booking' => $booking->load('service'),
        ]);
    }

    private function service(StorePage $page, int $id): Service
    {
        $service = Service::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('is_active', true)
            ->find($id);

        // 404, not 422: a service from another store genuinely does not exist on
        // this page, and saying so avoids confirming that it exists elsewhere.
        abort_unless($service, 404);

        return $service;
    }

    private function staff(StorePage $page, ?int $id): ?StaffMember
    {
        if ($id === null) {
            return null;
        }

        $staff = StaffMember::withoutGlobalScopes()
            ->where('store_page_id', $page->getKey())
            ->where('is_active', true)
            ->find($id);

        abort_unless($staff, 422, 'That person is no longer available.');

        return $staff;
    }

    private function page(Request $request): StorePage
    {
        $page = app(StorePage::class);

        abort_unless($page->theme === Theme::Service, 404);

        return $page;
    }
}
