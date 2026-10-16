<?php

namespace Database\Factories\Domains\Orders\Models;

use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AbandonedCart> */
class AbandonedCartFactory extends Factory
{
    protected $model = AbandonedCart::class;

    public function definition(): array
    {
        $total = fake()->numberBetween(100000, 4000000);

        return [
            'store_page_id' => StorePage::factory(),
            'seller_id' => fn (array $attributes) => StorePage::find($attributes['store_page_id'])?->seller_id,
            'reference' => 'AC'.fake()->numerify('######'),
            'email' => fake()->safeEmail(),
            'phone' => '+23480'.fake()->numerify('########'),
            'cart' => [
                ['name' => 'Left Behind Item', 'quantity' => 2, 'line_kobo' => $total],
            ],
            'total_kobo' => $total,
            'status' => AbandonedCartStatus::Open,
            'recovered_order_id' => null,
            'reminded_at' => null,
        ];
    }

    public function recovered(): static
    {
        return $this->state(fn () => ['status' => AbandonedCartStatus::Recovered]);
    }

    public function reminded(): static
    {
        return $this->state(fn () => ['reminded_at' => now()]);
    }
}
