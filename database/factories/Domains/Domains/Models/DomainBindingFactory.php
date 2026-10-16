<?php

namespace Database\Factories\Domains\Domains\Models;

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DomainBinding> */
class DomainBindingFactory extends Factory
{
    protected $model = DomainBinding::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'store_page_id' => null,
            'host' => fake()->unique()->domainWord().'.com',
            'verification_token' => strtoupper(fake()->bothify('??##????##')),
            'status' => DomainStatus::Pending,
            'verified_at' => null,
            'ssl_active_at' => null,
            'last_error' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => DomainStatus::Active,
            'verified_at' => now(),
            'ssl_active_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => DomainStatus::Suspended,
            'last_error' => 'The CNAME record no longer resolves to us.',
        ]);
    }
}
