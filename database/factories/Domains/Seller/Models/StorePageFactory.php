<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StorePage> */
class StorePageFactory extends Factory
{
    protected $model = StorePage::class;

    public function definition(): array
    {
        return [
            'seller_id' => Seller::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company(),
            'theme' => Theme::Store,
            'status' => 'draft',
            'tagline' => fake()->sentence(),
            'bio' => fake()->paragraph(),
            'whatsapp_number' => '+2348030000000',
            'city' => 'Lagos',
            'state' => 'Lagos',
            'brand_color' => '#16a34a',
            'font_pair' => 'modern',
        ];
    }

    public function storeTheme(): static
    {
        return $this->state(fn () => ['theme' => Theme::Store]);
    }

    public function serviceTheme(): static
    {
        return $this->state(fn () => ['theme' => Theme::Service]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }
}
