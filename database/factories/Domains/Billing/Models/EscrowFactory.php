<?php

namespace Database\Factories\Domains\Billing\Models;

use App\Domains\Billing\Models\Escrow;
use App\Domains\Billing\Models\EscrowStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Escrow> */
class EscrowFactory extends Factory
{
    protected $model = Escrow::class;

    public function definition(): array
    {
        $page = StorePage::factory()->create();
        $order = Order::factory()->create(['seller_id' => $page->seller_id]);

        return [
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->getKey(),
            'payable_type' => $order->getMorphClass(),
            'payable_id' => $order->getKey(),
            'transaction_id' => Transaction::factory()->create([
                'seller_id' => $page->seller_id,
                'payable_type' => $order->getMorphClass(),
                'payable_id' => $order->getKey(),
            ])->getKey(),
            'amount_kobo' => 500000,
            'commission_kobo' => 7500,
            'status' => EscrowStatus::Held,
            'held_at' => now(),
            'auto_releases_at' => now()->addDays(7),
        ];
    }

    public function frozen(): static
    {
        return $this->state(fn () => [
            'status' => EscrowStatus::Frozen,
            'dispute_reason' => 'Buyer says the order never arrived.',
        ]);
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'status' => EscrowStatus::Released,
            'released_at' => now(),
        ]);
    }
}
