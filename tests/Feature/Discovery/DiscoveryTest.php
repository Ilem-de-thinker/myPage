<?php

use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\get;

beforeEach(function () {
    config(['mypage.features.discovery' => true]);
});

/**
 * A page that has genuinely opted in: published, dated, and the seller said yes.
 *
 * Written by hand rather than with a factory state so each test says which of the
 * three conditions it is varying. A helper that always sets all three makes a
 * test that is really about opt-in read as though it were about publishing.
 */
function discovered(array $attributes = []): StorePage
{
    $attributes['seller_id'] ??= Seller::factory()->create()->id;

    return StorePage::withoutGlobalScopes()->create(array_merge([
        'name' => 'Test Shop',
        'slug' => 'test-shop-'.Str::random(6),
        'theme' => Theme::Store,
        'status' => StoreStatus::Published,
        'city' => 'Lagos',
        'state' => 'Lagos',
        'tagline' => 'Quality fabric, cut and stitched to measure.',
        'published_at' => now(),
        'is_directory_opted_in' => true,
    ], $attributes));
}

it('lists a business that opted in', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada-textiles']);

    get('/discover')
        ->assertOk()
        ->assertSee('Ada Textiles')
        ->assertSee('1 business is selling here');
});

it('never lists a page that has not opted in', function () {
    // The one that matters most: publishing a page is not consent to be listed.
    discovered([
        'name' => 'Quiet Shop',
        'slug' => 'quiet-shop',
        'is_directory_opted_in' => false,
    ]);

    get('/discover')->assertOk()->assertDontSee('Quiet Shop');
});

it('never lists a draft, even when it is opted in', function () {
    discovered([
        'name' => 'Unfinished Shop',
        'slug' => 'unfinished-shop',
        'status' => StoreStatus::Draft,
        'published_at' => null,
    ]);

    get('/discover')->assertOk()->assertDontSee('Unfinished Shop');
});

it('never lists a suspended page', function () {
    // Suspension means under dispute. A stranger must not find it.
    discovered([
        'name' => 'Suspended Shop',
        'slug' => 'suspended-shop',
        'status' => StoreStatus::Suspended,
    ]);

    get('/discover')->assertOk()->assertDontSee('Suspended Shop');
});

it('never lists a page whose status says published but was never dated', function () {
    // A half-finished publish: the status flipped, the date never landed.
    discovered([
        'name' => 'Half Published',
        'slug' => 'half-published',
        'published_at' => null,
    ]);

    get('/discover')->assertOk()->assertDontSee('Half Published');
});

it('hides the hub entirely while the feature is off', function () {
    config(['mypage.features.discovery' => false]);

    discovered(['name' => 'Ada Textiles', 'slug' => 'ada-textiles']);

    // 404 rather than a coming-soon page: an empty hub looks broken rather than
    // absent, and a soft-launch flag should not advertise what it is holding back.
    get('/discover')->assertNotFound();
});

it('hides the hub when the panic switch is on', function () {
    config(['mypage.features_locked' => true]);

    discovered();

    get('/discover')->assertNotFound();
});

it('separates stores from service providers', function () {
    discovered(['name' => 'Fabric Shop', 'slug' => 'fabric-shop', 'theme' => Theme::Store]);
    discovered(['name' => 'Braids Studio', 'slug' => 'braids-studio', 'theme' => Theme::Service]);

    get('/discover?type=store')
        ->assertOk()
        ->assertSee('Fabric Shop')
        ->assertDontSee('Braids Studio');

    get('/discover?type=service')
        ->assertOk()
        ->assertSee('Braids Studio')
        ->assertDontSee('Fabric Shop');
});

it('filters by city', function () {
    discovered(['name' => 'Lagos Shop', 'slug' => 'lagos-shop', 'city' => 'Lagos']);
    discovered(['name' => 'Enugu Shop', 'slug' => 'enugu-shop', 'city' => 'Enugu']);

    get('/discover?city=Lagos')->assertOk()->assertSee('Lagos Shop')->assertDontSee('Enugu Shop');
});

it('filters by price tier', function () {
    discovered(['name' => 'Budget Shop', 'slug' => 'budget-shop', 'price_tier' => 1]);
    discovered(['name' => 'Luxury Shop', 'slug' => 'luxury-shop', 'price_tier' => 4]);

    get('/discover?tier=4')->assertOk()->assertSee('Luxury Shop')->assertDontSee('Budget Shop');
});

it('searches the name, the tagline and the seller\'s own words', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada', 'tagline' => 'Ankara and lace.']);
    discovered(['name' => 'Bright Books', 'slug' => 'bright', 'tagline' => 'Secondhand novels.']);
    discovered([
        'name' => 'Kola Electricals',
        'slug' => 'kola',
        'tagline' => 'Generators and inverters',
        'bio' => 'We service diesel generators across the mainland.',
    ]);

    get('/discover?q=ankara')->assertOk()->assertSee('Ada Textiles')->assertDontSee('Bright Books');
    get('/discover?q=novels')->assertOk()->assertSee('Bright Books')->assertDontSee('Ada Textiles');

    // The bio is where a hub keyword actually lives: a buyer types the thing they
    // need, not the seller's chosen name.
    get('/discover?q=diesel')->assertOk()->assertSee('Kola Electricals')->assertDontSee('Ada Textiles');
});

it('is not fooled by a wildcard in a search term', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada']);
    discovered(['name' => 'Bright Books', 'slug' => 'bright']);

    // An unescaped % matches everything, which turns the filter into a no-op.
    get('/discover?q=%25')->assertOk()->assertDontSee('Bright Books');
});

it('ignores an unknown type rather than erroring on a bad shared link', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada']);

    get('/discover?type=warehouse')->assertOk()->assertSee('Ada Textiles');
});

it('only offers cities that have a discoverable business', function () {
    discovered(['city' => 'Lagos']);

    discovered([
        'name' => 'Quiet Shop', 'slug' => 'quiet-'.Str::random(4),
        'city' => 'Rivers', 'is_directory_opted_in' => false,
    ]);

    $html = get('/discover')->assertOk()->getContent();

    expect($html)->toContain('Lagos')->not->toContain('Rivers');
});

it('only offers a tier that is actually in use', function () {
    discovered(['price_tier' => 2]);

    $html = get('/discover')->assertOk()->getContent();

    expect($html)->toContain('Standard')->not->toContain('Luxury');
});

it('splits the marquee into three columns', function () {
    foreach (range(1, 7) as $index) {
        discovered(['name' => "Shop {$index}", 'slug' => "shop-{$index}"]);
    }

    $response = get('/discover')->assertOk();

    /** @var array<int, array<int, StorePage>> $columns */
    $columns = $response->viewData('columns');

    expect($columns)->toHaveCount(3);

    // Round-robin, so each column gets a near-equal share rather than the first
    // column taking every third business in a block.
    expect(array_map('count', $columns))->toEqual([3, 2, 2]);

    $seen = collect($columns)->flatten()->pluck('id')->all();

    expect($seen)->toHaveCount(7)
        ->and(array_unique($seen))->toHaveCount(7);
});

it('drops empty columns rather than leaving a gap', function () {
    discovered(['name' => 'Only Shop', 'slug' => 'only-shop']);

    $columns = get('/discover')->assertOk()->viewData('columns');

    expect($columns)->toHaveCount(1);
});

it('renders each business card exactly once for assistive technology', function () {
    // The marquee renders every card twice so the loop has no seam. The second
    // copy must be inert: a screen reader announcing every shop twice is worse
    // than not having a marquee.
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada']);

    $html = get('/discover')->assertOk()->getContent();

    expect(substr_count($html, 'Ada Textiles'))->toBeGreaterThan(1)
        ->and($html)->toContain('inert');
});

it('links a card straight to the storefront', function () {
    $page = discovered(['slug' => 'ada-textiles']);

    get('/discover')
        ->assertOk()
        ->assertSee(route('storefront.show', ['slug' => $page->slug]), escape: false);
});

it('distinguishes a store from a bookable business on the card', function () {
    discovered(['name' => 'Fabric Shop', 'slug' => 'fabric', 'theme' => Theme::Store]);
    discovered(['name' => 'Braids Studio', 'slug' => 'braids', 'theme' => Theme::Service]);

    $html = get('/discover')->assertOk()->getContent();

    // Emoji, not colour alone: a moving list is not a place a subtle shade
    // difference survives being read at speed.
    expect($html)->toContain('🛍️')->toContain('📅');
});

it('returns the results fragment for a live filter request', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada']);

    $response = get('/discover?q=ada', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();

    expect($response->viewData())->not->toHaveKey('columns')
        ->and($response->viewData())->toHaveKey('results');
});

it('returns the marquee fragment when an unfiltered request is live', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada']);

    $response = get('/discover', ['X-Partial' => 'results'])->assertOk();

    expect($response->viewData())->toHaveKey('columns')
        ->and($response->viewData())->not->toHaveKey('results');
});

it('renders the whole page for an ordinary request', function () {
    // The opposite of the fragment cases: a crawler, a preview bot and curl must
    // all get a real document. Treating any unknown client as wanting a fragment
    // would leave the hub's only indexable surface as the one nobody receives.
    discovered();

    $html = get('/discover')->assertOk()->getContent();

    // #discover-results is the root of the results fragment. Its absence means
    // this is a page, not a swapped-in grid.
    expect($html)->toContain('<!doctype html>')
        ->and($html)->toContain('<main id="main-content">')
        ->and($html)->not->toContain('id="discover-results"');
});

it('paginates filtered results and carries the filter forward', function () {
    foreach (range(1, 26) as $index) {
        discovered(['name' => "Lagos Shop {$index}", 'slug' => "shop-{$index}"]);
    }

    $response = get('/discover?city=Lagos')->assertOk();

    $results = $response->viewData('results');

    expect($results->total())->toBe(26)
        ->and($results->hasMorePages())->toBeTrue();

    // Page two must still be the same city, not the whole hub again.
    $html = get('/discover?city=Lagos&page=2')->assertOk()->getContent();

    expect($html)->toContain('city=Lagos');
});

it('offers an empty state with a way out', function () {
    discovered(['name' => 'Ada Textiles', 'slug' => 'ada']);

    $html = get('/discover?q=zzzznothing')->assertOk()->getContent();

    expect($html)->toContain('No verified merchants found')
        ->and($html)->toContain('Clear all');
});

it('says plainly when nobody has opted in yet', function () {
    // An empty grid with no explanation reads as a broken page.
    get('/discover')
        ->assertOk()
        ->assertSee('Nobody has joined the directory yet.');
});

it('puts a featured business above an unfeatured one', function () {
    // The plain page is explicit about its rating: the factory does not seed one,
    // and a null here has to actually lose.
    discovered(['name' => 'Plain Shop', 'slug' => 'plain', 'rating' => 5.0, 'review_count' => 50]);
    discovered(['name' => 'Featured Shop', 'slug' => 'featured', 'is_featured' => true, 'rating' => 1.0, 'review_count' => 1]);

    $html = get('/discover')->assertOk()->getContent();

    expect(strpos($html, 'Featured Shop'))->toBeLessThan(strpos($html, 'Plain Shop'));
});
