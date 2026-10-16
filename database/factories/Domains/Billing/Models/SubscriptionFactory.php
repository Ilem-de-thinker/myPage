<?php

namespace Database\Factories\Domains\Billing\Models;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $startsAt = now()->subDays(3);

        return [
            'seller_id' => Seller::factory(),
            'plan_id' => Plan::factory(),
            'status' => SubscriptionStatus::Active,
            'price_kobo' => fake()->numberBetween(350000, 2500000),
            'trial_ends_at' => null,
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $startsAt->copy()->addMonth(),
            'cancelled_at' => null,
            'grace_ends_at' => null,
            'payment_method' => 'gateway',
            'gateway_reference' => 'SUB_'.strtoupper(fake()->bothify('??##??##')),
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(10),
            'current_period_starts_at' => null,
            'current_period_ends_at' => null,
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => now()->addDays(5),
        ]);
    }

    /**
     * Lapsed: past due with the grace period already spent.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::PastDue,
            'grace_ends_at' => now()->subDay(),
        ]);
    }

    public function forSeller(Seller $seller): static
    {
        return $this->state(fn () => ['seller_id' => $seller->getKey()]);
    }
}
