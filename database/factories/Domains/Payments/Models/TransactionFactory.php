<?php

namespace Database\Factories\Domains\Payments\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Payments\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        // payable_id first: seller_id is resolved from it, and factory closures
        // are evaluated in definition order.
        $order = Order::factory()->create();

        return [
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->getKey(),
            'seller_id' => $order->seller_id,
            'reference' => 'MP'.Str::upper(Str::random(12)),
            'gateway' => 'paystack',
            'amount_kobo' => fake()->numberBetween(100000, 5000000),
            'status' => TransactionStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => TransactionStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
