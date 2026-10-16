<?php

namespace Database\Factories\Domains\Booking\Models;

use App\Domains\Booking\Models\StaffMember;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffMember> */
class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    public function definition(): array
    {
        return [
            'store_page_id' => StorePage::factory(),
            'seller_id' => fn (array $attributes) => StorePage::find($attributes['store_page_id'])?->seller_id,
            'name' => fake()->name(),
            'bio' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
