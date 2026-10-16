<?php

namespace Database\Factories\Domains\Orders\Models;

use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'store_page_id' => StorePage::factory(),
            'seller_id' => fn (array $attributes) => StorePage::find($attributes['store_page_id'])?->seller_id,
            'reference' => 'MP'.fake()->numerify('######'),
            'source' => OrderSource::Platform,
            'status' => OrderStatus::PendingPayment,
            'total_kobo' => fake()->numberBetween(100000, 5000000),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '+23480'.fake()->numerify('########'),
            'customer_note' => null,
        ];
    }

    public function whatsapp(): static
    {
        return $this->state(fn () => [
            'source' => OrderSource::Whatsapp,
            'status' => OrderStatus::Pending,
        ]);
    }

    public function platform(): static
    {
        return $this->state(fn () => [
            'source' => OrderSource::Platform,
            'status' => OrderStatus::PendingPayment,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid]);
    }
}
