<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\get;

it('serves the storefront on the apex domain at the slug path', function () {
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create(['slug' => 'path-shop', 'theme' => 'store', 'status' => 'published']);

    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $seller->id, 'name' => 'Path Item', 'status' => 'active']);

    get('/path-shop')->assertOk()->assertSee('Products');
    get('/path-shop/products')->assertOk()->assertSee('Path Item');
});

it('keeps apex auth and dashboard routes reachable', function () {
    get('/no-such-store')->assertNotFound();

    // The apex marketing and auth routes must not be swallowed by the slug prefix.
    get('/')->assertOk();
    get('/register')->assertOk();
    get('/login')->assertOk();
    get('/forgot-password')->assertOk();
    get('/dashboard')->assertRedirect(route('login'));
    get('/products')->assertRedirect(route('login'));
});

it('404s an unknown or unpublished slug on the apex domain', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->create([
        'slug' => 'draft-path-shop',
        'theme' => 'store',
        'status' => 'draft',
    ]);

    get('/missing-path-shop')->assertNotFound();
    get('/draft-path-shop')->assertNotFound();
    get('/draft-path-shop/products')->assertNotFound();
});
