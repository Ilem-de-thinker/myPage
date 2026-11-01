<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Services\AvailabilityService;
use App\Domains\Customers\Actions\UpsertCustomerFromBooking;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use App\Shared\Support\WhatsApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The free fallback for a visitor who would rather message than pay online.
 *
 * No slot is held: the buyer asks, the seller agrees. That is the point of the
 * fallback, and pretending otherwise would quietly block paying customers out of
 * a slot the seller never agreed to give away.
 */
class RequestBookingByWhatsApp
{
    public function __construct(
        private readonly UpsertCustomerFromBooking $upsertCustomerFromBooking,
        private readonly QuoteServiceFulfilment $fulfilment,
    ) {}

    /**
     * @param  array{name?: ?string, phone?: ?string, notes?: ?string}  $buyer
     * @return array{booking: Booking, url: ?string}
     */
    public function handle(Service $service, Carbon $startsAt, ?StaffMember $staff, array $buyer = []): array
    {
        $timezone = app(AvailabilityService::class)->timezoneFor($service);
        $start = $startsAt->copy()->setTimezone($timezone);

        // The same resolved fee the paid path would charge, snapshotted so the
        // seller's later edits cannot change what was quoted in the message.
        $travelFee = $this->fulfilment->handle($service)['travel_fee_kobo'];

        $booking = DB::transaction(function () use ($service, $start, $staff, $buyer, $travelFee) {
            $booking = Booking::withoutGlobalScopes()->create([
                'seller_id' => $service->seller_id,
                'store_page_id' => $service->store_page_id,
                'service_id' => $service->getKey(),
                'staff_member_id' => $staff?->getKey(),
                'reference' => Booking::generateReference(),
                'starts_at' => $start->copy()->utc(),
                'ends_at' => $start->copy()->addMinutes($service->duration_min)->utc(),
                // A request is not a booking: the seller confirms or declines.
                'status' => BookingStatus::PendingPayment,
                'price_kobo' => $service->price_kobo,
                'deposit_kobo' => 0,
                'travel_fee_kobo' => $travelFee,
                // Nothing is held, so nothing expires and nothing is reserved.
                'expires_at' => null,
                'customer_name' => $buyer['name'] ?? null,
                'customer_phone' => PhoneNumber::normalize($buyer['phone'] ?? null),
                'notes' => $buyer['notes'] ?? null,
                'source' => 'whatsapp',
            ]);

            $booking->customer_id = $this->upsertCustomerFromBooking->handle($booking)?->getKey();
            $booking->save();

            $booking->history()->create([
                'from_status' => null,
                'to_status' => $booking->status,
                'note' => 'Requested over WhatsApp',
            ]);

            return $booking;
        });

        return [
            'booking' => $booking,
            'url' => $this->whatsappUrl($service, $booking, $start, $timezone),
        ];
    }

    private function whatsappUrl(Service $service, Booking $booking, Carbon $start, string $timezone): ?string
    {
        $storePage = $service->relationLoaded('storePage')
            ? $service->storePage
            : StorePage::find($service->store_page_id);

        $message = "Hello {$storePage?->name}, I'd like to book {$service->name} on "
            .$start->setTimezone($timezone)->format('D j M Y \a\t g:i A').".\n"
            ."My reference is {$booking->reference}.";

        return $storePage ? WhatsApp::link($storePage, $message) : null;
    }
}
