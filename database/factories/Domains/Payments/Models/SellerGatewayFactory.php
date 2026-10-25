<?php

namespace Database\Factories\Domains\Payments\Models;

use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SellerGateway>
 */
class SellerGatewayFactory extends Factory
{
    protected $model = SellerGateway::class;

    public function definition(): array
    {
        $secret = 'sk_test_'.Str::random(24);

        return [
            'seller_id' => Seller::factory(),
            'gateway' => 'paystack',
            'environment' => 'test',
            'public_key' => 'pk_test_'.Str::lower(Str::random(24)),
            // Factories run unguarded, so this can set a column no request can.
            'secret_key' => $secret,
            'secret_key_last4' => Str::substr($secret, -4),
            'verified_at' => now(),
        ];
    }

    public function live(): static
    {
        $secret = 'sk_live_'.Str::random(24);

        return $this->state(fn () => [
            'environment' => 'live',
            'public_key' => 'pk_live_'.Str::lower(Str::random(24)),
            'secret_key' => $secret,
            'secret_key_last4' => Str::substr($secret, -4),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'verified_at' => null,
        ]);
    }

    public function broken(): static
    {
        return $this->state(fn () => [
            'last_failed_at' => now(),
        ]);
    }
}
