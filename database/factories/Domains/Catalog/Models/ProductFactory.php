<?php

namespace Database\Factories\Domains\Catalog\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'store_page_id' => StorePage::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->paragraph(),
            'price_kobo' => fake()->numberBetween(100000, 5000000),
            'sku' => 'SKU-'.strtoupper(Str::random(8)),
            'track_stock' => true,
            'stock_qty' => fake()->numberBetween(0, 50),
            'status' => 'active',
            'is_featured' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
