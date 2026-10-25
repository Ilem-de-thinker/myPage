<?php

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Directory\Services\StructuredData;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\get;

function seoPage(array $attributes = []): StorePage
{
    $attributes['seller_id'] ??= Seller::factory()->create()->id;

    return StorePage::withoutGlobalScopes()->create(array_merge([
        'name' => 'Ada Textiles',
        'slug' => 'ada-textiles',
        'theme' => Theme::Store,
        'status' => StoreStatus::Published,
        'tagline' => 'Ankara and lace, cut to measure.',
        'state' => 'Lagos',
        'city' => 'Lagos',
        'published_at' => now(),
    ], $attributes));
}

beforeEach(function () {
    // Captured before anything is overridden, because afterEach has to put back
    // the real root rather than this test's stand-in.
    $this->originalUrl = config('app.url');

    config([
        'app.url' => 'https://mypage.ng',
        // Pinned rather than inherited: .env turns the directory on locally, and
        // a sitemap that quietly gains or loses a URL depending on the developer's
        // machine is not a test anyone can trust.
        'mypage.features.directory' => false,
    ]);

    // route() caches its root from the first request it sees, so config alone
    // is not enough. forceRootUrl sets the host; the scheme still comes from the
    // test request, so the assertions below match on the host+path and leave the
    // scheme out of it.
    URL::forceRootUrl('https://mypage.ng');

    File::deleteDirectory(public_path('sitemaps'));
});

afterEach(function () {
    // The URL generator is process-global. Left forced, every later test file
    // gets this test's host and sitemap origin.
    URL::forceRootUrl($this->originalUrl);

    File::deleteDirectory(public_path('sitemaps'));
});

it('lists a published page in the sitemap', function () {
    $page = seoPage();

    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = File::get(public_path('sitemaps/sitemap.xml'));

    expect($xml)->toContain(parse_url(StoreUrl::home($page), PHP_URL_HOST))
        ->and($xml)->toContain('mypage.ng');
});

it('never lists a draft or suspended page in the sitemap', function () {
    seoPage(['name' => 'Live Shop', 'slug' => 'live-shop']);
    seoPage(['name' => 'Hidden Draft', 'slug' => 'hidden-draft', 'status' => StoreStatus::Draft, 'published_at' => null]);
    seoPage(['name' => 'Suspended Shop', 'slug' => 'suspended-shop', 'status' => StoreStatus::Suspended]);

    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = File::get(public_path('sitemaps/sitemap.xml'));

    expect($xml)->toContain('live-shop')
        ->and($xml)->not->toContain('hidden-draft')
        ->and($xml)->not->toContain('suspended-shop');
});

it('writes a sitemap that parses as valid XML', function () {
    seoPage();

    $this->artisan('sitemap:generate')->assertSuccessful();

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string(File::get(public_path('sitemaps/sitemap.xml')));
    libxml_use_internal_errors($previous);

    expect($xml)->not->toBeFalse()
        ->and($xml->getName())->toBe('urlset');
});

it('escapes a seller name that would otherwise break the xml', function () {
    // The classic sitemap failure: an unescaped ampersand makes the whole file
    // unparseable, and search engines then discard every URL in it.
    seoPage(['name' => 'Bread & Butter', 'slug' => 'bread-butter']);

    $this->artisan('sitemap:generate')->assertSuccessful();

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string(File::get(public_path('sitemaps/sitemap.xml')));
    libxml_use_internal_errors($previous);

    expect($xml)->not->toBeFalse();
});

it('gives a verified page a higher priority', function () {
    // verified_at is not fillable, so passing it to create() is silently
    // dropped and every page would come out unverified.
    $trusted = seoPage(['name' => 'Trusted Shop', 'slug' => 'trusted-shop']);
    $trusted->forceFill(['verified_at' => now()])->save();

    seoPage(['name' => 'Plain Shop', 'slug' => 'plain-shop']);

    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = File::get(public_path('sitemaps/sitemap.xml'));

    expect($xml)->toContain('<priority>0.8</priority>')
        ->and($xml)->toContain('<priority>0.6</priority>');
});

it('still writes a sitemap when no page is published', function () {
    // The landing page is always worth advertising, so an empty platform is not
    // a failure: the command writes one URL rather than nothing at all.
    $this->artisan('sitemap:generate')->assertSuccessful();

    $xml = File::get(public_path('sitemaps/sitemap.xml'));

    expect(substr_count($xml, '<url>'))->toBe(1)
        ->and($xml)->not->toContain('ada-textiles');
});

it('tells crawlers to stay out of the private areas', function () {
    get('/robots.txt')
        ->assertOk()
        ->assertSee('User-agent: *', false)
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /dashboard')
        ->assertSee('Disallow: /login')
        ->assertSee('Allow: /');
});

it('does not advertise a sitemap that does not exist yet', function () {
    // Pointing a crawler at a missing file is a 404 on every single crawl.
    get('/robots.txt')->assertOk()->assertDontSee('Sitemap:');
});

it('advertises the sitemap once it has been generated', function () {
    seoPage();
    $this->artisan('sitemap:generate')->assertSuccessful();

    get('/robots.txt')->assertOk()->assertSee('Sitemap: ', false)->assertSee('mypage.ng/sitemaps/sitemap.xml');
});

it('describes a shop with its real products and prices', function () {
    $page = seoPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Ankara Gown',
        'slug' => 'ankara-gown',
        'price_kobo' => 1850000,
        'status' => 'active',
    ]);

    $data = StructuredData::forPage($page->fresh());

    expect($data['@type'])->toBe('OnlineStore')
        ->and($data['makesOffer'])->toHaveCount(1)
        ->and($data['makesOffer'][0]['name'])->toBe('Ankara Gown')
        // Kobo become a decimal string here and nowhere else.
        ->and($data['makesOffer'][0]['price'])->toBe('18500.00')
        ->and($data['makesOffer'][0]['priceCurrency'])->toBe('NGN');
});

it('does not advertise a retired product', function () {
    $page = seoPage();

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Sold Out Forever',
        'status' => 'archived',
    ]);

    expect(StructuredData::forPage($page->fresh())['makesOffer'] ?? [])->toBe([]);
});

it('describes only the products a filtered listing is showing', function () {
    $page = seoPage();

    $fabrics = Category::withoutGlobalScopes()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'name' => 'Fabrics', 'slug' => 'fabrics',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id,
        'category_id' => $fabrics->id, 'name' => 'Ankara Print', 'slug' => 'ankara-print',
        'status' => 'active',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id,
        'name' => 'Lace Gown', 'slug' => 'lace-gown', 'status' => 'active',
    ]);

    // A filtered page must not advertise what it filtered out, or the search
    // snippet describes a shop the visitor is not looking at.
    $html = get(storeUrl($page->slug, '/products?category='.$fabrics->id))->assertOk()->getContent();

    expect($html)->toContain('Ankara Print')
        ->and($html)->not->toContain('Lace Gown');
});

it('describes a booking page as a service business, not a shop', function () {
    $page = seoPage(['theme' => Theme::Service, 'name' => 'Kemi Braids', 'slug' => 'kemi-braids']);

    Service::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Box Braids',
        'slug' => 'box-braids',
        'price_kobo' => 5500000,
        'is_active' => true,
    ]);

    $data = StructuredData::forPage($page->fresh());

    expect($data['@type'])->toBe('HealthAndBeautyBusiness')
        ->and($data)->not->toHaveKey('makesOffer')
        ->and($data['hasOfferCatalog']['itemListElement'][0]['name'])->toBe('Box Braids')
        ->and($data['hasOfferCatalog']['itemListElement'][0]['price'])->toBe('55000.00');
});

it('claims a verified credential only when the page carries one', function () {
    $unverified = seoPage();

    expect(StructuredData::forPage($unverified->fresh()))->not->toHaveKey('hasCredential');

    // verified_at is not fillable, so create() would silently drop it.
    $verified = seoPage(['slug' => 'trusted']);
    $verified->forceFill(['verified_at' => now()])->save();

    expect(StructuredData::forPage($verified->fresh()))->toHaveKey('hasCredential');
});

it('puts valid structured data on a live storefront', function () {
    $page = seoPage();
    $page->update(['status' => StoreStatus::Published]);

    $html = get(storeUrl($page->slug))->assertOk()->getContent();

    expect($html)->toContain('application/ld+json');

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    $decoded = json_decode($matches[1] ?? '', true);

    expect(json_last_error())->toBe(JSON_ERROR_NONE)
        ->and($decoded['@type'])->toBe('OnlineStore')
        ->and($decoded['name'])->toBe('Ada Textiles');
});

it('puts no structured data on a suspended page', function () {
    // A suspended page must disappear from search results entirely, not merely
    // be unlisted. Anything else tells a buyer to keep trying.
    $page = seoPage(['status' => StoreStatus::Suspended]);

    expect(get(storeUrl($page->slug))->assertNotFound()->getContent())
        ->not->toContain('application/ld+json');
});

it('does not let a seller name break out of the script tag', function () {
    $page = seoPage(['name' => '</script><script>alert(1)</script>']);

    $html = get(storeUrl($page->slug))->assertOk()->getContent();

    // JSON_HEX_TAG turns the closing tag into a harmless escape sequence.
    expect($html)->not->toContain('</script><script>alert(1)')
        ->and($html)->toContain('application/ld+json');
});
