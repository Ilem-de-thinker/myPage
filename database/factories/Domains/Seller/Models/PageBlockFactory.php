<?php

namespace Database\Factories\Domains\Seller\Models;

use App\Domains\Seller\Models\PageBlock;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageBlock> */
class PageBlockFactory extends Factory
{
    protected $model = PageBlock::class;

    public function definition(): array
    {
        return [
            'store_page_id' => StorePage::factory(),
            'page' => 'home',
            'key' => 'about',
            'content' => [],
        ];
    }

    public function home(string $key, array $content = []): static
    {
        return $this->state(fn () => [
            'page' => 'home',
            'key' => $key,
            'content' => $content,
        ]);
    }
}
