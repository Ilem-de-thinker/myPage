<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Seller\Models\DeliveryProfile;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryProfile> */
class DeliveryProfileFactory extends Factory
{
    protected $model = DeliveryProfile::class;

    public function definition(): array
    {
        return [
            'store_page_id' => StorePage::factory(),
            'offers_delivery' => true,
            'offers_pickup' => false,
            'offers_home_service' => false,
            'free_over_kobo' => null,
            'radius_km' => null,
            'notes' => null,
        ];
    }

    public function pickupOnly(): static
    {
        return $this->state(fn () => [
            'offers_delivery' => false,
            'offers_pickup' => true,
        ]);
    }

    public function freeOver(int $kobo): static
    {
        return $this->state(fn () => ['free_over_kobo' => $kobo]);
    }
}
