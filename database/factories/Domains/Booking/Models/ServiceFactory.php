<?php

namespace Database\Factories\Domains\Booking\Models;

use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Service> */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        // No unique(): faker's pool is global and finite, and the slug already
        // carries a random suffix.
        $name = Str::title(fake()->words(2, true));

        return [
            'store_page_id' => StorePage::factory(),
            'seller_id' => fn (array $attributes) => StorePage::find($attributes['store_page_id'])?->seller_id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->paragraph(),
            'duration_min' => 60,
            'buffer_min' => 0,
            'price_kobo' => fake()->numberBetween(500000, 8000000),
            'deposit_type' => DepositType::None,
            'deposit_value' => 0,
            'is_active' => true,
            'position' => 0,
            'billing_unit' => BillingUnit::PerProject,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * The service the seller wants first on the directory, and the one the card
     * puts the Featured ribbon on.
     */
    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    /**
     * A service with the card's deliverable tick-list filled in.
     *
     * @param  array<int, string>|null  $items
     */
    public function withDeliverables(?array $items = null): static
    {
        return $this->state(fn () => [
            'deliverables' => $items ?? [
                'Consultation and plan',
                'The work itself',
                'Aftercare advice',
            ],
            'turnaround_label' => '5 working days',
        ]);
    }

    public function billedPer(BillingUnit $unit): static
    {
        return $this->state(fn () => ['billing_unit' => $unit]);
    }

    public function lasting(int $minutes): static
    {
        return $this->state(fn () => ['duration_min' => $minutes]);
    }

    public function withBuffer(int $minutes): static
    {
        return $this->state(fn () => ['buffer_min' => $minutes]);
    }

    public function withFixedDeposit(int $kobo): static
    {
        return $this->state(fn () => [
            'deposit_type' => DepositType::Fixed,
            'deposit_value' => $kobo,
        ]);
    }

    public function withPercentDeposit(int $percent): static
    {
        return $this->state(fn () => [
            'deposit_type' => DepositType::Percent,
            'deposit_value' => $percent,
        ]);
    }
}
