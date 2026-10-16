<?php

namespace Database\Factories\Domains\Payments\Models;

use App\Domains\Payments\Enums\PayoutStatus;
use App\Domains\Payments\Models\Payout;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payout> */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'reference' => 'PO'.Str::upper(Str::random(10)),
            'amount_kobo' => 100000,
            'status' => PayoutStatus::Pending,
            'bank_code' => '007',
            'account_number' => '0123456789',
            'account_name' => 'Test Seller',
            'gateway' => 'paystack',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PayoutStatus::Paid,
            'gateway_reference' => 'TRF_'.Str::upper(Str::random(8)),
            'processed_at' => now(),
        ]);
    }

    public function failed(string $reason = 'The bank rejected the transfer.'): static
    {
        return $this->state(fn () => [
            'status' => PayoutStatus::Failed,
            'failure_reason' => $reason,
            'processed_at' => now(),
        ]);
    }
}
