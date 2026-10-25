<?php

use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\AbandonedCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Events\StorePagePublished;
use App\Domains\Seller\Events\StorePageUnpublished;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Top a draft page up to the point where the publish gate lets it through: a
 * description, a WhatsApp number, a live product and at least one section.
 *
 * Works on the page the test already has, because the dashboard and every
 * dashboard controller resolve a seller's single page with firstOrFail().
 */
function makePublishable(StorePage $page, array $attributes = []): StorePage
{
    $page->update(array_merge([
        'theme' => 'store',
        'status' => 'draft',
        'bio' => 'We make Ankara for weddings.',
        'whatsapp_number' => '+2348031234567',
    ], $attributes));

    if ($page->blocks()->count() === 0) {
        $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);
    }

    Product::factory()->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'status' => 'active',
    ]);

    return $page->fresh();
}

it('publishes a page that has everything it needs', function () {
    Event::fake([StorePagePublished::class]);

    [$seller, $page] = sellerWithPage();
    $page = makePublishable($page);

    actingAs($seller)->post(route('page.publish'))->assertRedirect(route('dashboard'));

    $page->refresh();

    expect($page->status)->toBe(StoreStatus::Published)
        ->and($page->published_at)->not->toBeNull()
        ->and($page->isPublished())->toBeTrue();

    Event::assertDispatched(StorePagePublished::class);
});

it('makes the published page reachable on the storefront right away', function () {
    [$seller, $page] = sellerWithPage();
    $page = makePublishable($page);

    // A draft page 404s for ordinary visitors.
    get(storeUrl($page->slug, ''))->assertNotFound();

    actingAs($seller)->post(route('page.publish'))->assertRedirect();

    // Publishing must bust the cached lookup, or the page stays dark.
    get(storeUrl($page->slug, ''))->assertOk();
});

it('refuses to publish a page with nothing to sell', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'store',
        'status' => 'draft',
        'bio' => 'We make Ankara.',
        'whatsapp_number' => '+2348031234567',
    ]);

    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);

    actingAs($seller)->post(route('page.publish'));

    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('refuses to publish a page missing a description or a whatsapp number', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'store',
        'status' => 'draft',
        'bio' => null,
        'tagline' => null,
        'whatsapp_number' => null,
    ]);

    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);
    Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'status' => 'active']);

    actingAs($seller)->post(route('page.publish'));

    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('refuses to publish a page whose blocks are all empty', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'store',
        'status' => 'draft',
        'bio' => 'We make Ankara.',
        'whatsapp_number' => '+2348031234567',
    ]);

    $page->blocks()->where('key', 'hero')->update(['content' => []]);

    Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'status' => 'active']);

    actingAs($seller)->post(route('page.publish'));

    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('does not count a draft product as something to publish', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'store',
        'status' => 'draft',
        'bio' => 'We make Ankara.',
        'whatsapp_number' => '+2348031234567',
    ]);

    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);
    Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'status' => 'draft']);

    actingAs($seller)->post(route('page.publish'));

    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('never publishes another seller\'s page', function () {
    [$seller, $page] = sellerWithPage();
    [$foreignSeller, $foreignPage] = sellerWithPage();
    $foreignPage = makePublishable($foreignPage);

    actingAs($seller)->post(route('page.publish'))->assertRedirect();

    expect($foreignPage->fresh()->status)->toBe(StoreStatus::Draft);
});

it('sends a seller back to their checklist instead of an error page', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'draft']);

    actingAs($seller)->post(route('page.publish'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('publish_blockers');

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Almost there');
});

it('lets a seller take a live page back down without losing their data', function () {
    Event::fake([StorePageUnpublished::class]);

    [$seller, $page] = sellerWithPage();
    $page = makePublishable($page);

    actingAs($seller)->post(route('page.publish'));
    actingAs($seller)->post(route('page.unpublish'))->assertRedirect();

    $page->refresh();

    expect($page->status)->toBe(StoreStatus::Draft);

    // published_at is deliberately kept so the seller can see how long it was live.
    expect($page->published_at)->not->toBeNull();

    Event::assertDispatched(StorePageUnpublished::class);
});

it('shows real numbers on the dashboard instead of zeroes', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    Order::factory()->paid()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => 250000,
        'created_at' => now()->startOfWeek()->addHour(),
    ]);

    // Placed this week but never paid, so it must not inflate revenue.
    Order::factory()->platform()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => 900000,
        'created_at' => now()->startOfWeek()->addHours(2),
    ]);

    // Last week, so it belongs in the comparison and nowhere else.
    Order::factory()->paid()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => 100000,
        'created_at' => now()->startOfWeek()->subWeek()->addDay(),
    ]);

    AbandonedCart::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'total_kobo' => 75000,
    ]);

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Orders This Week')
        ->assertSee('Revenue This Week')
        // Two orders this week against one last week.
        ->assertSee('+1 vs last week')
        ->assertSee('₦2,500')
        // The unpaid ₦9,000 must not be counted as money.
        ->assertDontSee('₦11,500')
        ->assertSee('₦750');
});

it('never shows another seller\'s numbers on the dashboard', function () {
    [$seller, $page] = sellerWithPage(['theme' => 'store', 'status' => 'published']);

    [$otherSeller, $otherPage] = sellerWithPage();

    Order::factory()->paid()->create([
        'seller_id' => $otherSeller->id,
        'store_page_id' => $otherPage->id,
        'total_kobo' => 750000,
    ]);

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('₦7,500');
});

it('shows the checklist and links straight to whatever is missing', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'store',
        'status' => 'draft',
        'bio' => 'We make Ankara for weddings.',
        'whatsapp_number' => null,
    ]);

    // Sections on, but no product and no WhatsApp number.
    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get started')
        ->assertSee('Add your WhatsApp number')
        ->assertSee('Add a product')
        ->assertSee('Fix this');
});

it('hides the fix-it links once a seller has finished the checklist', function () {
    [$seller, $page] = sellerWithPage();
    makePublishable($page);

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Add a product')
        // The label stays so the seller can see it was handled.
        ->assertDontSee('Fix this');
});

it('refuses to publish a service page with no opening hours', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'draft',
        'bio' => 'A Lagos barber shop.',
        'whatsapp_number' => '+2348031234567',
    ]);

    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);

    Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    // A service, a description, a number and sections, and still not ready.
    actingAs($seller)->post(route('page.publish'));

    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('refuses to publish a service page with no services listed', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'draft',
        'bio' => 'A Lagos barber shop.',
        'whatsapp_number' => '+2348031234567',
    ]);

    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);

    // Nothing to book yet, so the page would go live with an empty shop front.
    actingAs($seller)->post(route('page.publish'));

    expect($page->fresh()->status)->toBe(StoreStatus::Draft);
});

it('publishes a service page once it has a live service', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => 'service',
        'status' => 'draft',
        'bio' => 'A Lagos barber shop.',
        'whatsapp_number' => '+2348031234567',
    ]);

    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => fake()->sentence()]]);

    Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'is_active' => true,
    ]);

    // Opening hours are required too, or the booking widget can never return a slot.
    WorkingHour::create([
        'store_page_id' => $page->id,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '17:00',
    ]);

    actingAs($seller)->post(route('page.publish'))->assertRedirect(route('dashboard'));

    expect($page->fresh()->status)->toBe(StoreStatus::Published);
});
