<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Models\StoreSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StoreSection> */
class StoreSectionFactory extends Factory
{
    protected $model = StoreSection::class;

    public function definition(): array
    {
        return [
            'store_page_id' => StorePage::factory(),
            'page' => 'home',
            'key' => 'hero',
            'position' => 0,
            'is_enabled' => true,
        ];
    }
}
