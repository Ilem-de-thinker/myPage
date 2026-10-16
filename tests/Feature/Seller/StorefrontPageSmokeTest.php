<?php

use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

/**
 * Every page a visitor can reach, for both themes.
 *
 * The block refactor changed how each page decides what to render, and a page
 * that quietly renders nothing still returns 200. These assert on content, so a
 * blank page fails instead of passing as "no errors".
 */
function storePage(): StorePage
{
    $seller = Seller::factory()->create();

    $page = StorePage::factory()->for($seller)->withContent()->create([
        'theme' => 'store',
        'status' => 'published',
        'slug' => 'smoke-store',
        'name' => 'Smoke Store',
    ]);

    Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Smoke Product',
        'status' => 'active',
    ]);

    return $page;
}

function servicePage(): StorePage
{
    $seller = Seller::factory()->create();

    $page = StorePage::factory()->for($seller)->withContent()->create([
        'theme' => 'service',
        'status' => 'published',
        'slug' => 'smoke-service',
        'name' => 'Smoke Salon',
        'settings' => ['timezone' => 'Africa/Lagos'],
    ]);

    Service::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'name' => 'Smoke Service',
        'slug' => 'smoke-service-item',
        'is_active' => true,
    ]);

    return $page;
}

it('renders every store page with content on it', function (string $path, string $expected) {
    $page = storePage();

    get($path === '' ? "/{$page->slug}" : "/{$page->slug}{$path}")
        ->assertOk()
        ->assertSee($expected);
})->with([
    'home' => ['', 'Smoke Store'],
    'about' => ['/about', 'Smoke Store'],
    'contact' => ['/contact', 'Contact'],
    'products' => ['/products', 'Smoke Product'],
    'cart' => ['/cart', 'Order Bag & Escrow Receipt'],
    'checkout' => ['/checkout', 'Checkout'],
    'track order' => ['/track-order', 'Track your order'],
]);

it('renders every service page with content on it', function (string $path, string $expected) {
    $page = servicePage();

    get("/{$page->slug}{$path}")
        ->assertOk()
        ->assertSee($expected);
})->with([
    'home' => ['/', 'Smoke Salon'],
    'about' => ['/about', 'Smoke Salon'],
    'contact' => ['/contact', 'Contact'],
    'services' => ['/services', 'Smoke Service'],
    'service detail' => ['/services/smoke-service-item', 'Smoke Service'],
    'booking' => ['/book', 'Smoke Service'],
]);

/**
 * The factory writes filler content into every block, which hides bugs where a
 * page depends on a block existing. These delete the blocks first, so the page
 * has to stand on the real rows alone.
 */
it('never leaks a Blade comment into the rendered page', function () {
    // A comment written as {-- ... --} or closed with a single brace is not a
    // comment to Blade, so it prints on the page in front of the customer.
    expect(file_get_contents(resource_path('views/themes/store/contact.blade.php')))
        ->toStartWith('{{--')
        ->toContain('--}}')
        ->and(file_get_contents(resource_path('views/themes/service/contact.blade.php')))
        ->toStartWith('{{--')
        ->toContain('--}}');

    expect(get('/'.storePage()->slug.'/contact')->assertOk()->getContent())->not->toContain('{{--');
    expect(get('/'.servicePage()->slug.'/contact')->assertOk()->getContent())->not->toContain('{{--');
});

it('renders a service detail page even when the seller wrote no introduction', function () {
    $page = servicePage();

    $page->blocks()->where('page', 'service-show')->delete();

    $html = get("/{$page->slug}/services/smoke-service-item")->assertOk()->getContent();

    expect($html)
        ->toContain('Smoke Service')
        ->toContain('Reserve a slot')
        ->toContain('Duration');
});

it('renders the services listing even when the seller wrote no introduction', function () {
    $page = servicePage();

    $page->blocks()->where('page', 'services')->delete();

    get("/{$page->slug}/services")
        ->assertOk()
        ->assertSee('Smoke Service');
});

it('renders the service home even when every block is empty', function () {
    $page = servicePage();

    $page->blocks()->delete();

    get("/{$page->slug}")
        ->assertOk()
        ->assertSee('Smoke Service');
});

it('shows an empty service list rather than an error when there is nothing to book', function () {
    $seller = Seller::factory()->create();
    $page = StorePage::factory()->for($seller)->withContent()->create([
        'theme' => 'service',
        'status' => 'published',
        'slug' => 'smoke-empty-service',
    ]);

    get("/{$page->slug}/services")
        ->assertOk()
        ->assertSee('has not listed any services yet');

    get("/{$page->slug}/book")
        ->assertOk()
        ->assertSee('Bookings open soon.');
});

it('hides an inactive service from the listing and from its own page', function () {
    $page = servicePage();

    Service::withoutGlobalScopes()->where('store_page_id', $page->id)->update(['is_active' => false]);

    get("/{$page->slug}/services")
        ->assertOk()
        ->assertDontSee('Smoke Service');

    get("/{$page->slug}/services/smoke-service-item")
        ->assertNotFound();
});

it('renders the store home from its blocks in theme order', function () {
    $page = storePage();

    // Give the two blocks distinct words so their positions are unambiguous.
    $page->blocks()->where('key', 'hero')->update(['content' => ['headline' => 'FIRSTWORDHERO']]);
    $page->blocks()->where('key', 'about')->update(['content' => ['body' => 'LASTWORDABOUT']]);

    $html = get("/{$page->slug}")->assertOk()->getContent();

    // The hero comes before the about block because the theme says so, not
    // because of anything the seller typed.
    expect(strpos($html, 'FIRSTWORDHERO'))->toBeGreaterThan(-1)
        ->and(strpos($html, 'LASTWORDABOUT'))->toBeGreaterThan(-1)
        ->and(strpos($html, 'FIRSTWORDHERO'))->toBeLessThan(strpos($html, 'LASTWORDABOUT'));
});

it('shows the uploaded logo and cover photo on the public page', function () {
    Storage::fake('public');

    $page = storePage();

    $page->update([
        'logo_path' => UploadedFile::fake()->image('logo.jpg')->store('storefront/logo', 'public'),
        'cover_path' => UploadedFile::fake()->image('cover.jpg')->store('storefront/cover', 'public'),
    ]);

    $html = get("/{$page->slug}")->assertOk()->getContent();

    expect($html)
        ->toContain(Storage::disk('public')->url($page->logo_path))
        ->toContain(Storage::disk('public')->url($page->cover_path));
});

it('uses the brand colour the seller chose', function () {
    $page = storePage();
    $page->update(['brand_color' => '#7c3aed']);

    get("/{$page->slug}")
        ->assertOk()
        ->assertSee('--brand: #7c3aed', escape: false);
});

it('shows the catalogue blocks on a store with products and hides them on one without', function () {
    $page = storePage();

    // No heading typed on either block: the catalogue is the content.
    $page->blocks()->where('key', 'products')->update(['content' => []]);
    $page->blocks()->where('key', 'categories')->update(['content' => []]);

    get("/{$page->slug}")
        ->assertOk()
        ->assertSee('Smoke Product');

    Product::withoutGlobalScopes()->where('store_page_id', $page->id)->delete();

    $html = get("/{$page->slug}")->assertOk()->getContent();

    // Nothing left to list, so the block renders nothing rather than an empty grid.
    expect($html)->not->toContain('Smoke Product');
});

/**
 * The service home blocks show real rows, so each one has to prove it appears
 * when there is something to show and disappears when there is not.
 */
describe('service home blocks', function () {
    it('lists the services a customer can book', function () {
        $page = servicePage();

        $html = get("/{$page->slug}")->assertOk()->getContent();

        expect($html)
            ->toContain('Smoke Service')
            ->toContain(route('storefront.service', ['slug' => $page->slug, 'serviceSlug' => 'smoke-service-item']))
            ->toContain(route('storefront.services', ['slug' => $page->slug]));
    });

    it('hides the services block when there is nothing bookable', function () {
        $page = servicePage();
        Service::withoutGlobalScopes()->where('store_page_id', $page->id)->delete();

        get("/{$page->slug}")
            ->assertOk()
            ->assertDontSee('See every service');
    });

    it('shows the team a customer will be sitting with', function () {
        $page = servicePage();

        StaffMember::factory()->create([
            'store_page_id' => $page->id,
            'seller_id' => $page->seller_id,
            'name' => 'Smoke Stylist',
            'is_active' => true,
        ]);

        get("/{$page->slug}")
            ->assertOk()
            ->assertSee('Smoke Stylist');
    });

    it('leaves out staff the seller has paused', function () {
        $page = servicePage();

        StaffMember::factory()->create([
            'store_page_id' => $page->id,
            'seller_id' => $page->seller_id,
            'name' => 'Paused Stylist',
            'is_active' => false,
        ]);

        get("/{$page->slug}")
            ->assertOk()
            ->assertDontSee('Paused Stylist');
    });

    it('builds the gallery from service photos and hides it without any', function () {
        Storage::fake('public');

        $page = servicePage();

        get("/{$page->slug}")
            ->assertOk()
            ->assertDontSee('smoke-service-item.jpg');

        $path = UploadedFile::fake()->image('smoke-service-item.jpg')->store('services', 'public');

        Service::withoutGlobalScopes()
            ->where('store_page_id', $page->id)
            ->update(['image_path' => $path]);

        get("/{$page->slug}")
            ->assertOk()
            ->assertSee(Storage::disk('public')->url($path));
    });

    it('keeps the booking form off the landing page and on the service page', function () {
        $page = servicePage();

        // The home page offers a way in. It does not open with a booking panel:
        // that tells a visitor who just arrived what to do instead of showing
        // them the business.
        get("/{$page->slug}")
            ->assertOk()
            ->assertSee('See available times')
            ->assertSee(route('storefront.book', ['slug' => $page->slug]))
            ->assertDontSee('name="starts_at"', escape: false);

        // The choice is made on the service page, so the times are there too.
        get("/{$page->slug}/services/smoke-service-item")
            ->assertOk()
            ->assertSee('action="'.route('storefront.booking.store', ['slug' => $page->slug]).'"', escape: false)
            ->assertSee('name="starts_at"', escape: false);

        Service::withoutGlobalScopes()->where('store_page_id', $page->id)->delete();

        // Nothing bookable, nothing rendered: no form that cannot submit.
        get("/{$page->slug}")
            ->assertOk()
            ->assertDontSee('See available times')
            ->assertDontSee('name="starts_at"', escape: false);
    });

    it('sends the hero call to action at the booking page, not the product list', function () {
        $page = servicePage();

        get("/{$page->slug}")
            ->assertOk()
            ->assertSee(route('storefront.book', ['slug' => $page->slug]))
            ->assertDontSee(route('storefront.products', ['slug' => $page->slug]));
    });
});

/**
 * The home page with a catalogue behind it.
 *
 * The rest of this file renders pages for a seller with nothing on them, which
 * is how a product card that lazy-loads its category can sit here green and then
 * 500 the home page of every real shop. Lazy loading is disabled in tests for
 * exactly this reason, so this is the test that would have caught it.
 */
it('renders the home page with a populated catalogue', function () {
    $page = storePage();

    $category = Category::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'name' => 'Populated Shelf',
    ]);

    Product::factory()->count(3)->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'category_id' => $category->id,
        'status' => 'active',
    ]);

    get("/{$page->slug}")
        ->assertOk()
        ->assertSee('Populated Shelf');
});
