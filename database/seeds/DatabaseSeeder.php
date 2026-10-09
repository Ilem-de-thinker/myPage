<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $seller = Seller::factory()->create();
        $page = StorePage::factory()->for($seller)->create([
            'status' => 'published',
        ]);
        $product = Product::factory()->create([
            'store_page_id' => $page->id,
            'seller_id' => $seller->id,
            'name' => 'Handmade Adire Fabric',
            'price' => 50000,
            'price_kobo' => 5000000,
            'status' => 'active',
            'stock_qty' => 20,
            'track_stock' => true,
        ]);
        $product->variants()->create(['name' => 'Large', 'stock_qty' => 15, 'price' => 55000]);
        $product->variants()->create(['name' => 'Medium', 'stock_qty' => 10, 'price' => 50000]);
        $product->variants()->create(['name' => 'Small', 'stock_qty' => 5, 'price' => 45000]);

        $this->command->info('Database seeded with seller, product, and 3 variants.');
    }
}
