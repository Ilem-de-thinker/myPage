<?php

namespace Database\Factories\Domains\Billing\Models;

use App\Domains\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = Str::title(fake()->word());

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'description' => fake()->sentence(),
            'price_kobo' => fake()->numberBetween(350000, 2500000),
            'interval' => 'monthly',
            'trial_days' => 14,
            'max_products' => 100,
            'max_staff' => null,
            'is_default' => false,
            'is_featured' => false,
            'is_active' => true,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn () => ['max_products' => null]);
    }

    public function withoutTrial(): static
    {
        return $this->state(fn () => ['trial_days' => null]);
    }
}
