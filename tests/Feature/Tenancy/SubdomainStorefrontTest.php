<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\get;

it('serves the storefront on the shop subdomain', function () {
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->create(['slug' => 'sub-shop', 'theme' => 'store', 'status' => 'published']);

    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $seller->id, 'name' => 'Sub Item', 'status' => 'active']);

    get(storeUrl('sub-shop'))->assertOk()->assertSee('Products');
    get(storeUrl('sub-shop', '/products'))->assertOk()->assertSee('Sub Item');
});

it('does not serve a shop from an apex slug path', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->create(['slug' => 'apex-shop', 'theme' => 'store', 'status' => 'published']);

    // The slug is the address now, not a path segment. An old link must not
    // quietly start rendering a shop under the platform's own domain.
    get('/apex-shop')->assertNotFound();
    get('/apex-shop/products')->assertNotFound();
});

it('keeps apex auth and dashboard routes reachable on the apex', function () {
    get('/')->assertOk();
    get('/register')->assertOk();
    get('/login')->assertOk();
    get('/forgot-password')->assertOk();
    get('/dashboard')->assertRedirect(route('login'));
    get('/products')->assertRedirect(route('login'));
});

it('does not answer platform routes on a tenant host', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->create(['slug' => 'tenant-shop', 'theme' => 'store', 'status' => 'published']);

    get(storeUrl('tenant-shop', '/login'))->assertNotFound();
    get(storeUrl('tenant-shop', '/dashboard'))->assertNotFound();
});

it('404s an unknown or unpublished subdomain', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->create([
        'slug' => 'draft-sub-shop',
        'theme' => 'store',
        'status' => 'draft',
    ]);

    get(storeUrl('missing-sub-shop'))->assertNotFound();
    get(storeUrl('draft-sub-shop'))->assertNotFound();
    get(storeUrl('draft-sub-shop', '/products'))->assertNotFound();
});

it('points shop navigation at the shop and platform links at the apex', function () {
    $seller = Seller::factory()->create();
    StorePage::factory()->for($seller)->create(['slug' => 'links-shop', 'theme' => 'store', 'status' => 'published']);

    $html = get(storeUrl('links-shop'))->assertOk()->getContent();

    // Internal links stay relative so they keep working on a custom domain.
    expect($html)
        ->toContain('href="/products"')
        ->toContain('href="'.config('app.url').'/login"');
});
