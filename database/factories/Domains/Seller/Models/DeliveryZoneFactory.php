<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Seller\Models\DeliveryProfile;
use App\Domains\Seller\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DeliveryZone> */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'delivery_profile_id' => DeliveryProfile::factory(),
            'name' => fake()->city(),
            'state' => fake()->state(),
            'fee_kobo' => fake()->numberBetween(50_000, 300_000),
            'position' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => ['fee_kobo' => 0]);
    }
}
