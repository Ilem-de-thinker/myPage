<?php

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Directory\Services\DirectoryQuery;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\get;

function directoryPage(array $attributes = []): StorePage
{
    // withoutGlobalScopes() skips BelongsToSeller's creating hook, which is
    // what normally fills seller_id. Set it here or the insert is rejected.
    $attributes['seller_id'] ??= Seller::factory()->create()->id;

    return StorePage::withoutGlobalScopes()->create(array_merge([
        'name' => 'Test Shop',
        'slug' => 'test-shop-'.Str::random(6),
        'theme' => Theme::Store,
        'status' => StoreStatus::Published,
        'city' => 'Lagos',
        'state' => 'Lagos',
        'tagline' => 'Quality fabric, cut and stitched to measure.',
        // DirectoryQuery requires a published_at as well as the status: a page
        // whose status says published but which was never actually dated is a
        // half-finished publish, and it stays out.
        'published_at' => now(),
    ], $attributes));
}

beforeEach(function () {
    config(['mypage.features.directory' => true]);
});

it('lists a published page', function () {
    directoryPage(['name' => 'Ada Textiles', 'slug' => 'ada-textiles']);

    get('/directory')
        ->assertOk()
        ->assertSee('Ada Textiles')
        ->assertSee('1 page');
});

it('never lists a draft', function () {
    // The important one: a draft is someone's unfinished work.
    directoryPage([
        'name' => 'Secret Draft',
        'slug' => 'secret-draft',
        'status' => StoreStatus::Draft,
        'published_at' => null,
    ]);

    get('/directory')
        ->assertOk()
        ->assertDontSee('Secret Draft');
});

it('never lists a suspended page', function () {
    // Suspended means under dispute. A stranger must not find it.
    directoryPage([
        'name' => 'Suspended Shop',
        'slug' => 'suspended-shop',
        'status' => StoreStatus::Suspended,
    ]);

    get('/directory')
        ->assertOk()
        ->assertDontSee('Suspended Shop');
});

it('never lists a page that was published then suspended', function () {
    $page = directoryPage(['name' => 'Was Live', 'slug' => 'was-live']);

    // Suspension has to win over a published_at that is still sitting there.
    $page->update(['status' => StoreStatus::Suspended]);

    get('/directory')->assertOk()->assertDontSee('Was Live');
});

it('hides the directory entirely while the feature is off', function () {
    config(['mypage.features.directory' => false]);

    directoryPage(['name' => 'Ada Textiles', 'slug' => 'ada-textiles']);

    // 404 rather than a coming-soon page: an empty directory looks broken, and
    // a soft-launch flag should not advertise what it is holding back.
    get('/directory')->assertNotFound();
});

it('hides the directory when the panic switch is on', function () {
    config(['mypage.features_locked' => true]);

    directoryPage();

    get('/directory')->assertNotFound();
});

it('filters by theme', function () {
    directoryPage(['name' => 'Fabric Shop', 'slug' => 'fabric-shop', 'theme' => Theme::Store]);
    directoryPage(['name' => 'Braids Studio', 'slug' => 'braids-studio', 'theme' => Theme::Service]);

    get('/directory?theme=store')->assertOk()->assertSee('Fabric Shop')->assertDontSee('Braids Studio');
    get('/directory?theme=service')->assertOk()->assertSee('Braids Studio')->assertDontSee('Fabric Shop');
});

it('filters by state', function () {
    directoryPage(['name' => 'Lagos Shop', 'slug' => 'lagos-shop', 'state' => 'Lagos']);
    directoryPage(['name' => 'Enugu Shop', 'slug' => 'enugu-shop', 'state' => 'Enugu']);

    get('/directory?state=Lagos')->assertOk()->assertSee('Lagos Shop')->assertDontSee('Enugu Shop');
});

it('filters by search term', function () {
    directoryPage(['name' => 'Ada Textiles', 'slug' => 'ada-textiles', 'tagline' => 'Ankara and lace.']);
    directoryPage(['name' => 'Bright Books', 'slug' => 'bright-books', 'tagline' => 'Secondhand novels.']);

    get('/directory?q=ankara')->assertOk()->assertSee('Ada Textiles')->assertDontSee('Bright Books');
    get('/directory?q=bright')->assertOk()->assertSee('Bright Books')->assertDontSee('Ada Textiles');
});

it('is not fooled by a wildcard in a search term', function () {
    directoryPage(['name' => 'Ada Textiles', 'slug' => 'ada-textiles']);
    directoryPage(['name' => 'Bright Books', 'slug' => 'bright-books']);

    // An unescaped % matches everything, which turns the filter into a no-op.
    get('/directory?q=%25')->assertOk()->assertDontSee('Bright Books');
});

it('only offers states that have a published page', function () {
    directoryPage(['state' => 'Lagos']);
    directoryPage([
        'name' => 'Hidden Draft', 'slug' => 'hidden-draft',
        'theme' => Theme::Store, 'status' => StoreStatus::Draft, 'state' => 'Rivers',
        'published_at' => null,
    ]);

    $states = app(DirectoryQuery::class)->states();

    expect($states)->toContain('Lagos')
        ->and($states)->not->toContain('Rivers');
});

it('resolves a clean state url to the stored name', function () {
    directoryPage(['state' => 'Cross River']);

    get('/directory/cross-river')->assertOk()->assertSee('Cross River');
});

it('offers a category that a published page actually carries', function () {
    $page = directoryPage(['name' => 'Ada Textiles', 'slug' => 'ada-textiles']);

    $category = Category::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Ankara',
        'slug' => 'ankara',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'category_id' => $category->id,
    ]);

    $slugs = app(DirectoryQuery::class)->categories()->pluck('slug');

    expect($slugs)->toContain('ankara');
});

it('does not mix a category into a page that does not sell it', function () {
    $tailor = directoryPage(['name' => 'Tailor', 'slug' => 'tailor']);
    $salon = directoryPage(['name' => 'Salon', 'slug' => 'salon']);

    $category = Category::factory()->create([
        'store_page_id' => $tailor->id, 'seller_id' => $tailor->seller_id,
        'name' => 'Ankara', 'slug' => 'ankwa',
    ]);

    Product::factory()->create([
        'store_page_id' => $tailor->id, 'seller_id' => $tailor->seller_id, 'category_id' => $category->id,
    ]);

    get('/directory?category=ankwa')
        ->assertOk()
        ->assertSee('Tailor')
        ->assertDontSee('Salon');
});

it('puts a verified page above an unverified one', function () {
    // Explicit about the unverified one: the factory seeds a verified_at for some
    // pages, and a null here has to actually win.
    $plain = directoryPage(['name' => 'Plain Shop', 'slug' => 'plain-shop']);
    $plain->forceFill(['verified_at' => null])->save();

    $trusted = directoryPage(['name' => 'Trusted Shop', 'slug' => 'trusted-shop']);
    $trusted->forceFill(['verified_at' => now()])->save();

    $html = get('/directory')->assertOk()->getContent();

    // The reason a buyer trusts the list is at the top of it.
    expect(strpos($html, 'Trusted Shop'))->toBeLessThan(strpos($html, 'Plain Shop'));
});

it('counts items for a shop but not a booking page', function () {
    $shop = directoryPage(['theme' => Theme::Store]);
    $studio = directoryPage(['theme' => Theme::Service]);

    Product::factory()->count(3)->create([
        'store_page_id' => $shop->id, 'seller_id' => $shop->seller_id,
    ]);

    $query = app(DirectoryQuery::class);

    expect($query->itemCount($shop))->toBe(3)
        ->and($query->itemCount($studio))->toBe(0);
});

it('does not count a suspended page towards the total', function () {
    directoryPage(['name' => 'Live Shop', 'slug' => 'live-shop']);
    directoryPage(['name' => 'Dead Shop', 'slug' => 'dead-shop', 'status' => StoreStatus::Suspended]);

    get('/directory')->assertOk()->assertSee('1 page');
});
