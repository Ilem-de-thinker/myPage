<?php

namespace Database\Factories\Domains\Booking\Models;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'store_page_id' => StorePage::factory(),
            'seller_id' => fn (array $attributes) => StorePage::find($attributes['store_page_id'])?->seller_id,
            'service_id' => Service::factory(),
            'staff_member_id' => null,
            'customer_id' => null,
            'reference' => 'BK'.fake()->regexify('[A-Z0-9]{8}'),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => BookingStatus::Confirmed,
            'price_kobo' => fake()->numberBetween(500000, 5000000),
            'deposit_kobo' => 0,
            'expires_at' => null,
            'reminded_24h_at' => null,
            'reminded_2h_at' => null,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '+23480'.fake()->numerify('########'),
            'notes' => null,
            'source' => 'web',
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->addMinutes(15),
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Confirmed,
            'expires_at' => null,
            'reminded_24h_at' => null,
            'reminded_2h_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => BookingStatus::Cancelled]);
    }

    /**
     * A hold whose payment window has already run out.
     */
    public function expiredHold(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
