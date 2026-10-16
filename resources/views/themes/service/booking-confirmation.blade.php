{{--
    Rendered straight from the booking, not from stored content: a confirmation
    page must never disappear because a seller left a field blank.

    The lookup bar at the top of the confirmation page posts back to this same
    route with ?booking=, which is the only thing PublicBookingController reads.
--}}
<x-themes.shared.layout :page="$page">
  <x-themes.service.blocks.booking-confirmation.details :page="$page" :booking="$booking ?? null" />
</x-themes.shared.layout>