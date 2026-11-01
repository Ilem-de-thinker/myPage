<?php

namespace App\Domains\Booking\Actions;

use App\Domains\Booking\Enums\ServiceMode;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Models\DeliveryProfile;

/**
 * Resolves where a service happens and what travelling to the buyer costs.
 *
 * The service's own mode wins; a null mode inherits the store profile, so a
 * provider who turns on home service does not have to touch every service.
 * Travel is only charged when the resolved mode is actually "we come to you".
 */
class QuoteServiceFulfilment
{
    /**
     * @return array{mode: ServiceMode, travel_fee_kobo: int}
     */
    public function handle(Service $service, ?DeliveryProfile $profile = null): array
    {
        $profile ??= DeliveryProfile::where('store_page_id', $service->store_page_id)->first();

        $mode = $service->service_mode
            ?? ($profile?->offers_home_service ? ServiceMode::Home : ServiceMode::AtLocation);

        return [
            'mode' => $mode,
            'travel_fee_kobo' => $mode === ServiceMode::Home
                ? max(0, (int) ($service->travel_fee_kobo ?? 0))
                : 0,
        ];
    }
}
