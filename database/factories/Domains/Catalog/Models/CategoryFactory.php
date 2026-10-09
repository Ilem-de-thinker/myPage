<?php

namespace Database\Factories\Domains\Catalog\Models;

use App\Domains\Catalog\Models\Category;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'store_page_id' => StorePage::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => 0,
        ];
    }
}
