<?php

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

function catalogPage(): StorePage
{
    $seller = Seller::factory()->create();

    return StorePage::factory()->for($seller)->create(['theme' => 'store', 'status' => 'published']);
}

it('lists only active products on the storefront', function () {
    $page = catalogPage();

    // Draft products must never be buyable.
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Ankara Print', 'status' => 'active']);
    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Hidden Draft',
        'status' => 'draft',
    ]);

    get("/{$page->slug}/products")
        ->assertOk()
        ->assertSee('Ankara Print')
        ->assertDontSee('Hidden Draft');
});

it('filters products by category and search', function () {
    $page = catalogPage();

    $fabrics = Category::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Fabrics',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Ankara Print',
        'category_id' => $fabrics->id,
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Linked Item',
        'slug' => 'linked-item',
        'status' => 'active',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Lace Gown',
        'slug' => 'lace-gown',
        'status' => 'active',
    ]);

    get("/{$page->slug}/products?category={$fabrics->id}")
        ->assertOk()
        ->assertSee('Ankara Print')
        ->assertDontSee('Lace Gown');

    get("/{$page->slug}/products?q=Lace")
        ->assertOk()
        ->assertSee('Lace Gown')
        ->assertDontSee('Ankara Print');
});

it('paginates the product listing and links its categories', function () {
    $page = catalogPage();

    $fabrics = Category::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Fabrics',
    ]);

    Product::factory()->count(16)->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'category_id' => $fabrics->id,
        'status' => 'active',
    ]);

    $response = get("/{$page->slug}/products")->assertOk()->assertSee('Fabrics');

    // 16 products with a page size of 12 must produce a second page.
    $paginator = $response->viewData('products');

    expect($paginator->total())->toBe(16);
    expect($paginator->perPage())->toBe(12);
    expect($paginator->hasMorePages())->toBeTrue();
});

it('serves a product detail page by slug with its price', function () {
    $page = catalogPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Test Product',
        'slug' => 'test-e1tp',
        'price_kobo' => 1200000,
        'status' => 'active',
    ]);

    get("/{$page->slug}/products/test-e1tp")
        ->assertOk()
        ->assertSee('Test Product')
        ->assertSee('₦12,000');
});

it('keeps a draft store previewable on product pages after the signed url', function () {
    $seller = Seller::factory()->create();

    $page = StorePage::factory()->for($seller)->create([
        'slug' => 'draftcatalog',
        'theme' => 'store',
        'status' => 'draft',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Draft Product',
        'status' => 'active',
    ]);

    $url = URL::temporarySignedRoute('storefront.show', now()->addHour(), ['slug' => $page->slug]);

    get($url)->assertOk();

    // The preview grant is carried by a cookie, so deep links keep working.
    $this->withUnencryptedCookie('preview_draftcatalog', '127.0.0.1');

    get("/{$page->slug}/products")->assertOk()->assertSee('Draft Product');
    get("/{$page->slug}/products/{$product->slug}")->assertOk()->assertSee('Draft Product');
});

it('serves cart and checkout for a store page but not the booking route', function () {
    $page = catalogPage();

    get("/{$page->slug}/cart")->assertOk();
    get("/{$page->slug}/checkout")->assertOk();
    get("/{$page->slug}/book")->assertNotFound();
});

it('exposes add-to-cart controls backed by browser storage', function () {
    $page = catalogPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Cartable Item',
        'slug' => 'cartable-item',
        'status' => 'active',
    ]);

    // The cart itself is an Alpine store persisted to localStorage, which lives
    // in the bundled app.js rather than the HTML. What the server owes the buyer
    // is the add control and a way to reach the cart it fills.
    $html = get("/{$page->slug}/products")->assertOk()->getContent();

    expect($html)->toContain('Add to cart')
        ->toContain('$store.cart.add')
        ->toContain(route('storefront.cart', ['slug' => $page->slug]));
});

it('shows a cart link with a live item count in the storefront nav', function () {
    $page = catalogPage();

    $html = get("/{$page->slug}/products")->assertOk()->getContent();

    expect($html)->toContain(route('storefront.cart', ['slug' => $page->slug]))
        ->toContain('$store.cart.count');
});

it('shows the product sku, and still renders a product stored without one', function () {
    $page = catalogPage();

    // The SKU a seller generated must be visible on the public page.
    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Sku Item',
        'slug' => 'sku-item',
        'sku' => 'SKU-ABC12345',
        'status' => 'active',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'No Sku Item',
        'slug' => 'no-sku-item',
        'sku' => null,
        'status' => 'active',
    ]);

    get("/{$page->slug}/products/sku-item")->assertOk()->assertSee('SKU-ABC12345');

    // A row with no sku must not break the page.
    get("/{$page->slug}/products/no-sku-item")->assertOk()->assertSee('No Sku Item');
});

it('marks an out of stock product on the listing', function () {
    $page = catalogPage();

    // Tracked stock at zero must be visible to the buyer.
    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Sold Out Item',
        'slug' => 'sold-out-item',
        'status' => 'active',
        'track_stock' => true,
        'stock_qty' => 0,
    ]);

    get("/{$page->slug}/products")->assertOk()->assertSee('Sold Out Item')->assertSee('Out of stock');
});

it('links products to their own detail page and never to another store', function () {
    $page = catalogPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Linked Item',
        'slug' => 'linked-item',
        'status' => 'active',
    ]);

    $foreignPage = StorePage::factory()->create(['theme' => 'store', 'status' => 'published']);

    Product::factory()->create([
        'store_page_id' => $foreignPage->id,
        'seller_id' => $foreignPage->seller_id,
        'name' => 'Other Item',
        'slug' => 'other-store-item',
        'status' => 'active',
    ]);

    $html = get("/{$page->slug}/products")->assertOk()->getContent();

    expect($html)->toContain("/{$page->slug}/products/linked-item");
    expect($html)->not->toContain('other-store-item');
});

it('never leaks another store\'s products', function () {
    $page = catalogPage();

    $foreignPage = StorePage::factory()->create(['theme' => 'store', 'status' => 'published']);

    $secret = Product::factory()->create([
        'store_page_id' => $foreignPage->id,
        'seller_id' => $foreignPage->seller_id,
        'name' => 'Secret Product',
        'slug' => 'secret-product',
        'status' => 'active',
    ]);

    get("/{$page->slug}/products")
        ->assertOk()
        ->assertDontSee('Secret Product');

    get("/{$page->slug}/products/{$secret->slug}")->assertNotFound();
});

it('re-prices the cart server side and refuses tampered or unavailable items', function () {
    $page = catalogPage();

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'slug' => 'repriced-item',
        'price_kobo' => 500000,
        'track_stock' => true,
        'stock_qty' => 5,
        'status' => 'active',
    ]);

    $foreignPage = StorePage::factory()->create(['theme' => 'store', 'status' => 'published']);

    // Another store's product must be unorderable through this storefront.
    $foreign = Product::factory()->create([
        'store_page_id' => $foreignPage->id,
        'seller_id' => $foreignPage->seller_id,
        'slug' => 'foreign-item',
        'status' => 'active',
    ]);

    // The client only sends ids and quantities; a tampered price must be ignored.
    $response = postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $product->id, 'qty' => 2, 'price_kobo' => 1]],
    ])->assertOk()->assertJsonPath('total_kobo', 1000000);

    expect($response->json('total'))->toBe('₦10,000');
    expect($response->json('reference'))->toStartWith('MP');
    expect($response->json('url'))->toStartWith('https://wa.me/');

    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['id' => $foreign->id, 'qty' => 1]]])->assertStatus(422);

    // Ordering more than the tracked stock allows is refused too.
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['id' => $product->id, 'qty' => 99]]])->assertStatus(422);
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => []])->assertStatus(422);
});

it('rejects a cart payload with invalid quantities or a missing item list', function () {
    $page = catalogPage();

    // Validation must fail before any pricing or stock logic runs.
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['id' => 1, 'qty' => 0]]])->assertStatus(422);
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['id' => 1, 'qty' => 'two']]])->assertStatus(422);
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => 'not-an-array'])->assertStatus(422);
    postJson("/{$page->slug}/checkout/whatsapp", [])->assertStatus(422);
});

it('rejects a cart payload with a bad or unknown product id', function () {
    $page = catalogPage();

    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['id' => 'abc', 'qty' => 1]]])->assertStatus(422);
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['qty' => 1]]])->assertStatus(422);
    postJson("/{$page->slug}/checkout/whatsapp", ['items' => [['id' => 999999, 'qty' => 1]]])->assertStatus(422);
});

it('ignores extra client fields such as a price or a spoofed name', function () {
    $page = catalogPage();

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'slug' => 'extra-field-item',
        'price_kobo' => 300000,
        'status' => 'active',
    ]);

    $response = postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [[
            'id' => $product->id,
            'qty' => 1,
            'name' => 'Hacked Name',
            'price_kobo' => 1,
            'slug' => 'hacked-slug',
        ]],
    ])->assertOk()->assertJsonPath('total_kobo', 300000);

    expect($response->json('total'))->toBe('₦3,000');
});

it('builds a whatsapp order for a single product', function () {
    $page = catalogPage();

    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Sole Item',
        'slug' => 'sole-item',
        'price_kobo' => 250000,
        'status' => 'active',
    ]);

    $response = postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $product->id, 'qty' => 1]],
    ])->assertOk();

    expect($response->json('total'))->toBe('₦2,500');
    expect($response->json('total_kobo'))->toBe(250000);
    expect($response->json('reference'))->toStartWith('MP');
});

it('totals every cart line server side', function () {
    $page = catalogPage();

    $first = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'First Line',
        'slug' => 'first-line',
        'price_kobo' => 100000,
        'stock_qty' => 10,
        'status' => 'active',
    ]);

    $second = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Second Line',
        'slug' => 'second-line',
        'price_kobo' => 200000,
        'stock_qty' => 10,
        'status' => 'active',
    ]);

    $response = postJson("/{$page->slug}/checkout/whatsapp", [
        'items' => [['id' => $first->id, 'qty' => 1], ['id' => $second->id, 'qty' => 2]],
    ])->assertOk()->assertJsonPath('total_kobo', 500000);

    expect($response->json('total'))->toBe('₦5,000');
});
