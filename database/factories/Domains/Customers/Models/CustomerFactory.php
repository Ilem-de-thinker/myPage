<?php

namespace Database\Factories\Domains\Customers\Models;

use App\Domains\Customers\Models\Customer;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phone = '+23480'.fake()->numerify('########');

        return [
            'seller_id' => Seller::factory(),
            'identity_key' => 'p:'.$phone,
            'name' => fake()->name(),
            // Not unique(): the database enforces seller_id + identity_key, and the
            // shared faker pool runs out long before a suite this size needs a new one.
            'email' => fake()->safeEmail(),
            'phone' => $phone,
            'opted_in' => false,
            'order_count' => 0,
            'booking_count' => 0,
            'lifetime_value_kobo' => 0,
            'last_activity_at' => null,
        ];
    }

    public function optedIn(): static
    {
        return $this->state(fn () => ['opted_in' => true]);
    }

    public function forSeller(Seller $seller): static
    {
        return $this->state(fn () => ['seller_id' => $seller->getKey()]);
    }
}
