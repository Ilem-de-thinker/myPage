<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Models\StoreSection;
use App\Domains\Seller\Services\SlugResolver;
use App\Shared\Traits\BelongsToSeller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;

function publishedPage(string $slug, string $theme = 'store'): StorePage
{
    $page = StorePage::factory()
        ->published()
        ->create(['slug' => $slug, 'theme' => $theme, 'name' => ucfirst($slug).' Shop']);

    StoreSection::factory()->create(['store_page_id' => $page->id, 'page' => 'home', 'key' => 'hero', 'position' => 0]);
    StoreSection::factory()->create(['store_page_id' => $page->id, 'page' => 'home', 'key' => 'products', 'position' => 1]);

    return $page;
}

it('serves a published store page for its slug', function () {
    publishedPage('demohome');

    get('/demohome')->assertOk()->assertSee('Demohome Shop');
});

it('404s for unknown slugs', function () {
    publishedPage('knownexisting');

    get('/definitely-not-a-store')->assertNotFound();
});

it('404s for draft store pages', function () {
    StorePage::factory()->create(['slug' => 'draftshop', 'status' => 'draft']);

    get('/draftshop')->assertNotFound();
    get('/draftshop/products')->assertNotFound();
});

it('404s for suspended store pages', function () {
    StorePage::factory()->create(['slug' => 'suspendedshop', 'status' => 'suspended']);

    get('/suspendedshop')->assertNotFound();
    get('/suspendedshop/contact')->assertNotFound();
});

it('404s store routes that do not belong to the page theme', function () {
    publishedPage('service-shop', 'service');

    get('/service-shop/services')->assertOk();
    get('/service-shop/products')->assertNotFound();
});

it('hides draft and archived products from the storefront', function () {
    $page = publishedPage('catalogshop');
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'slug' => 'draft-item', 'name' => 'Draft Item', 'status' => 'draft']);
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'slug' => 'archived-item', 'name' => 'Archived Item', 'status' => 'archived']);
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'slug' => 'live-item', 'name' => 'Live Item']);

    get('/catalogshop/products/draft-item')->assertNotFound();
    get('/catalogshop/products/archived-item')->assertNotFound();
    get('/catalogshop/products/live-item')->assertOk()->assertSee('Live Item');
    get('/catalogshop/products')->assertOk()->assertSee('Live Item')->assertDontSee('Draft Item');
});

it('lets the owner preview a draft via a signed URL', function () {
    $page = StorePage::factory()->create(['slug' => 'previewshop', 'status' => 'draft', 'theme' => 'service']);

    $url = URL::temporarySignedRoute('storefront.show', now()->addHour(), ['slug' => $page->slug]);

    get($url)->assertOk()->assertSee($page->name);
    get($url.'&tampered=1')->assertNotFound();
});

it('carries the preview grant to deep links via a cookie', function () {
    StorePage::factory()->create(['slug' => 'previewdeep', 'status' => 'draft', 'theme' => 'store']);

    $response = get(URL::temporarySignedRoute('storefront.show', now()->addHour(), ['slug' => 'previewdeep']))->assertOk();

    // The preview cookie is what authorises the following unsigned requests.
    expect($response->getCookie('preview_previewdeep'))->not->toBeNull();

    $this->withUnencryptedCookie('preview_previewdeep', '127.0.0.1');

    get('/previewdeep/about')->assertOk();
    get('/previewdeep/contact')->assertOk();
});

it('issues the preview cookie only for an unpublished page', function () {
    $draft = StorePage::factory()->create(['slug' => 'cookie-draft', 'status' => 'draft', 'theme' => 'store']);
    $live = StorePage::factory()->published()->create(['slug' => 'cookie-live', 'theme' => 'store']);

    $draftResponse = get(URL::temporarySignedRoute('storefront.show', now()->addHour(), ['slug' => $draft->slug]));
    $liveResponse = get(URL::temporarySignedRoute('storefront.show', now()->addHour(), ['slug' => $live->slug]));

    expect($draftResponse->getCookie('preview_cookie-draft'))->not->toBeNull();
    expect($liveResponse->getCookie('preview_cookie-live'))->toBeNull();
});

it('does not grant preview access without a signed url', function () {
    StorePage::factory()->create(['slug' => 'previewother', 'status' => 'draft', 'theme' => 'store']);

    get('/previewother')->assertNotFound();
    get('/previewother/about')->assertNotFound();
});

it('does not honour a preview cookie minted for another visitor', function () {
    StorePage::factory()->create(['slug' => 'previewip', 'status' => 'draft', 'theme' => 'store']);

    $this->withUnencryptedCookie('preview_previewip', '203.0.113.9');

    get('/previewip')->assertNotFound();
});

it('rejects a tampered or expired preview signature', function () {
    $page = StorePage::factory()->create(['slug' => 'oldexpiry', 'status' => 'draft', 'theme' => 'store']);

    $expired = URL::temporarySignedRoute('storefront.show', now()->subMinute(), ['slug' => $page->slug]);
    $url = URL::temporarySignedRoute('storefront.show', now()->addHour(), ['slug' => $page->slug]);

    get($expired)->assertNotFound();
    get($url.'&expires=1')->assertNotFound();
});

it('generates storefront urls as slug paths on the apex domain', function () {
    expect(config('mypage.path_routes'))->toBeTrue();

    $url = route('storefront.show', 'anyslug');
    $productsUrl = route('storefront.products', 'anyslug');

    expect($url)->toContain('/anyslug');
    expect($url)->not->toContain(config('mypage.domain'));
    expect($productsUrl)->toContain('/anyslug/products');
});

it('resolves the store page and shares it with the view', function () {
    publishedPage('sharedpage');

    // ResolveStorePage binds the page and shares it with the view.
    $response = get('/sharedpage')->assertOk();

    expect($response->viewData('page'))->toBeInstanceOf(StorePage::class);
    expect($response->viewData('page')->slug)->toBe('sharedpage');
    expect($response->viewData('page')->sections)->not->toBeEmpty();
});

it('never leaks one sellers pages into another sellers queries', function () {
    $a = Seller::factory()->create();
    $b = Seller::factory()->create();
    StorePage::factory()->for($a)->create(['slug' => 'shop-a']);
    StorePage::factory()->for($b)->create(['slug' => 'shop-b']);

    $this->actingAs($a);

    expect(StorePage::pluck('slug')->all())->toBe(['shop-a']);
});

it('scopes store pages to the authenticated seller', function () {
    $b = Seller::factory()->create();
    StorePage::factory()->create(['slug' => 'tenant-a']);
    StorePage::factory()->for($b)->create(['slug' => 'tenant-b']);

    $this->actingAs($b);

    expect(StorePage::pluck('slug')->all())->toBe(['tenant-b']);
});

it('recovers from a stale cached store page', function () {
    publishedPage('staleslug');

    // A cache entry written by an older build does not unserialize into a
    // StorePage; it must be discarded and refetched instead of returned.
    Cache::put('store:slug:staleslug', (object) [], 300);

    expect(app(SlugResolver::class)->resolveBySlug('staleslug'))->toBeInstanceOf(StorePage::class);

    get('/staleslug')->assertOk()->assertSee('Staleslug Shop');
});

it('caches a resolved page and flushes it on demand', function () {
    publishedPage('cachedpage');

    app(SlugResolver::class)->resolveBySlug('cachedpage');
    expect(Cache::has('store:slug:cachedpage'))->toBeTrue();

    app(SlugResolver::class)->flush('cachedpage');
    expect(Cache::has('store:slug:cachedpage'))->toBeFalse();

    get('/cachedpage')->assertOk();
});

it('serves the cached page until the cache is flushed', function () {
    $page = publishedPage('refreshpage');

    get('/refreshpage')->assertOk()->assertSee('Refreshpage Shop');

    $page->update(['name' => 'Renamed Shop']);

    // Still cached: the previous name is served until it is invalidated.
    get('/refreshpage')->assertOk()->assertSee('Refreshpage Shop');

    app(SlugResolver::class)->flush('refreshpage');

    get('/refreshpage')->assertOk()->assertSee('Renamed Shop');
});

it('stops resolving a page once it is deleted', function () {
    $page = publishedPage('vanishing');

    app(SlugResolver::class)->resolveBySlug('vanishing');

    $page->delete();

    // The observer must invalidate the cache or the deleted page stays online.
    expect(app(SlugResolver::class)->resolveBySlug('vanishing'))->toBeNull();
    get('/vanishing')->assertNotFound();
});

it('does not cache a missing page indefinitely', function () {
    app(SlugResolver::class)->resolveBySlug('ghostcache');

    // A null result must not become a cached "not found", or a newly created
    // page would stay invisible.
    expect(Cache::has('store:slug:ghostcache'))->toBeFalse();
});

it('serves a page created after an earlier miss', function () {
    expect(app(SlugResolver::class)->resolveBySlug('latecomer'))->toBeNull();

    StorePage::factory()->published()->create(['slug' => 'latecomer', 'theme' => 'store', 'name' => 'Late Shop']);

    expect(app(SlugResolver::class)->resolveBySlug('latecomer'))->toBeInstanceOf(StorePage::class);

    get('/latecomer')->assertOk()->assertSee('Late Shop');
});

it('has the BelongsToSeller trait on StorePage', function () {
    expect(class_uses(StorePage::class))->toContain(BelongsToSeller::class);
});
