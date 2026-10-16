<?php

use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\get;

/**
 * A published store page, so a storefront route resolves it.
 */
function filteredStorePage(): StorePage
{
    return StorePage::factory()->create([
        'theme' => 'store',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function filteredServicePage(): StorePage
{
    return StorePage::factory()->create([
        'theme' => 'service',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

function productOn(StorePage $page, array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'status' => 'active',
    ], $attributes));
}

function serviceOn(StorePage $page, array $attributes = []): Service
{
    return Service::withoutGlobalScopes()->create(array_merge([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'is_active' => true,
    ], $attributes));
}

/**
 * The names on the current page of results, in the order they were returned.
 *
 * Ordering and membership are asserted through the paginator rather than through
 * `assertSee`/`assertDontSee` on the rendered HTML, because these pages carry a
 * JSON-LD block listing every item in the document head. That block is correct —
 * a search engine should see the whole catalogue — but it means an item that was
 * filtered out of the visible grid is still present somewhere in the response, and
 * `assertDontSee` would fail for a reason that has nothing to do with the filter.
 */
function resultNames(TestResponse $response): array
{
    return $response->viewData('services')->pluck('name')->all();
}

function productNames(TestResponse $response): array
{
    return $response->viewData('products')->pluck('name')->all();
}

it('searches product names, descriptions and SKUs', function () {
    $page = filteredStorePage();

    productOn($page, ['name' => 'Ankara Print', 'slug' => 'ankara', 'sku' => 'ANK-001']);
    productOn($page, ['name' => 'Lace Gown', 'slug' => 'lace', 'description' => 'Floor length with a matching blouse.']);
    productOn($page, ['name' => 'Plain Tee', 'slug' => 'tee', 'sku' => 'TEE-900']);

    // The SKU is searchable because a seller prints it on the label and a buyer
    // reads it off the thing they already own.
    expect(productNames(get("/{$page->slug}/products?q=TEE-900")->assertOk()))->toBe(['Plain Tee'])
        ->and(productNames(get("/{$page->slug}/products?q=floor length")->assertOk()))->toBe(['Lace Gown'])
        ->and(productNames(get("/{$page->slug}/products?q=ankara")->assertOk()))->toBe(['Ankara Print']);
});

it('is not fooled by a wildcard in a product search term', function () {
    $page = filteredStorePage();

    productOn($page, ['name' => 'Ankara Print', 'slug' => 'ankara']);
    productOn($page, ['name' => 'Lace Gown', 'slug' => 'lace']);

    // An unescaped % matches every row, which turns the search into a no-op and
    // looks to a buyer like "search is broken" rather than "you found everything".
    expect(productNames(get("/{$page->slug}/products?q=%25")->assertOk()))->toBe([]);
});

it('filters by several categories at once', function () {
    $page = filteredStorePage();

    $fabrics = Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Fabrics',
    ]);
    $beads = Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Beads',
    ]);

    productOn($page, ['name' => 'Ankara Print', 'slug' => 'ankara', 'category_id' => $fabrics->id]);
    productOn($page, ['name' => 'Glass Beads', 'slug' => 'beads', 'category_id' => $beads->id]);
    productOn($page, ['name' => 'Lace Gown', 'slug' => 'lace']);

    // The multi-select is the array form; a single id from an older shared link
    // is still understood.
    expect(productNames(get("/{$page->slug}/products?category[]={$fabrics->id}&category[]={$beads->id}")->assertOk()))
        ->toEqualCanonicalizing(['Ankara Print', 'Glass Beads'])
        ->and(productNames(get("/{$page->slug}/products?category={$fabrics->id}")->assertOk()))
        ->toBe(['Ankara Print']);
});

it('filters products by a price band', function () {
    $page = filteredStorePage();

    productOn($page, ['name' => 'Cheap Item', 'slug' => 'cheap', 'price_kobo' => 50000]);
    productOn($page, ['name' => 'Mid Item', 'slug' => 'mid', 'price_kobo' => 500000]);
    productOn($page, ['name' => 'Dear Item', 'slug' => 'dear', 'price_kobo' => 9000000]);

    // Naira on the wire, kobo in the query.
    expect(productNames(get("/{$page->slug}/products?min=2000&max=10000")->assertOk()))
        ->toBe(['Mid Item']);
});

it('sorts products by price in both directions', function () {
    $page = filteredStorePage();

    productOn($page, ['name' => 'Cheap Item', 'slug' => 'cheap', 'price_kobo' => 50000]);
    productOn($page, ['name' => 'Dear Item', 'slug' => 'dear', 'price_kobo' => 9000000]);
    productOn($page, ['name' => 'Mid Item', 'slug' => 'mid', 'price_kobo' => 500000]);

    expect(productNames(get("/{$page->slug}/products?sort=price_low")->assertOk()))
        ->toBe(['Cheap Item', 'Mid Item', 'Dear Item'])
        ->and(productNames(get("/{$page->slug}/products?sort=price_high")->assertOk()))
        ->toBe(['Dear Item', 'Mid Item', 'Cheap Item']);
});

it('sorts products by units actually sold, ignoring cancelled orders', function () {
    $page = filteredStorePage();

    $popular = productOn($page, ['name' => 'Sold Item', 'slug' => 'sold', 'view_count' => 0]);
    $disputed = productOn($page, ['name' => 'Cancelled Item', 'slug' => 'cancelled', 'view_count' => 0]);

    Order::factory()->create(['store_page_id' => $page->id, 'status' => OrderStatus::Delivered])
        ->items()->create(['product_id' => $popular->id, 'name' => 'Sold Item', 'unit_kobo' => 100, 'quantity' => 5, 'line_kobo' => 500]);

    // A cancelled line is not a sale. Sorting it as one puts a disputed product
    // at the top of the shop.
    Order::factory()->create(['store_page_id' => $page->id, 'status' => OrderStatus::Cancelled])
        ->items()->create(['product_id' => $disputed->id, 'name' => 'Cancelled Item', 'unit_kobo' => 100, 'quantity' => 9, 'line_kobo' => 900]);

    $response = get("/{$page->slug}/products?sort=popular")->assertOk();

    expect(productNames($response))->toBe(['Sold Item', 'Cancelled Item'])
        // The count is shown so "best seller" is a claim a buyer can weigh.
        ->and($response->assertSee('Sold 5 times'));
});

it('falls back to a real ordering when the sort key is nonsense', function () {
    $page = filteredStorePage();

    productOn($page, ['name' => 'Ankara Print', 'slug' => 'ankara']);

    // A stale bookmark or a hand-edited URL must land on a page, not an error.
    get("/{$page->slug}/products?sort=cheapest_ever")->assertOk()->assertSee('Ankara Print');
});

it('keeps every active filter on the pagination links', function () {
    $page = filteredStorePage();

    $fabrics = Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Fabrics',
    ]);

    productOn($page, [
        'category_id' => $fabrics->id,
        'name' => 'Ankara Print',
        'slug' => 'ankara',
        'price_kobo' => 50000,
    ]);

    Product::factory()->count(16)->create([
        'seller_id' => $page->seller_id,
        'store_page_id' => $page->id,
        'status' => 'active',
        'category_id' => $fabrics->id,
        'price_kobo' => 60000,
    ]);

    // Without withQueryString() page two is the whole catalogue again, which is
    // the single most common bug in a filtered list.
    $html = get("/{$page->slug}/products?q=ankara&category[]={$fabrics->id}&sort=price_low&page=2")
        ->assertOk()
        ->getContent();

    // Asserted by what the link has to survive, not by how PHP happens to encode
    // it. http_build_query always indexes array values, so a round trip turns
    // `category[]=1` into `category[0]=1` — the same filter, spelled the other
    // way. Pinning the literal would fail the moment PHP changed a bracket.
    expect($html)
        ->toContain('q=ankara')
        ->toContain('sort=price_low')
        ->toMatch('/category(%5B|\[)0?(%5D|\])=\d+/');
});

it('offers price bounds taken from the catalogue, not from the filter', function () {
    $page = filteredStorePage();

    productOn($page, ['name' => 'Cheap Item', 'slug' => 'cheap', 'price_kobo' => 50000]);
    productOn($page, ['name' => 'Dear Item', 'slug' => 'dear', 'price_kobo' => 9000000]);

    // Bounds derived from the filtered set would collapse to one value the moment
    // a narrow filter was applied, and the buyer could no longer widen the range
    // without first clearing the filter they were using.
    $html = get("/{$page->slug}/products?q=cheap")->assertOk()->getContent();

    expect($html)->toContain('₦500')
        ->and($html)->toContain('₦90,000');
});

it('does not offer a category with nothing in it', function () {
    $page = filteredStorePage();

    $full = Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Fabrics',
    ]);
    Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Empty Shelf',
    ]);

    productOn($page, ['name' => 'Ankara Print', 'slug' => 'ankara', 'category_id' => $full->id]);

    $html = get("/{$page->slug}/products")->assertOk()->getContent();

    expect($html)->toContain('Fabrics')->not->toContain('Empty Shelf');
});

it('keeps counting the other categories while one is selected', function () {
    $page = filteredStorePage();

    $fabrics = Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Fabrics',
    ]);
    $beads = Category::factory()->create([
        'store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'name' => 'Beads',
    ]);

    productOn($page, ['category_id' => $fabrics->id, 'name' => 'Ankara Print', 'slug' => 'ankara']);
    productOn($page, ['category_id' => $beads->id, 'name' => 'Glass Beads', 'slug' => 'beads']);

    // A panel that counted only what is already selected shows every other
    // category as empty, which reads as "there is nothing else here" and buries
    // the way out of the filter the buyer is currently in.
    $html = get("/{$page->slug}/products?category[]={$fabrics->id}")->assertOk()->getContent();

    expect($html)->toContain('Beads');
});

it('tells a shopper who is browsing an empty shop apart from one whose filter missed', function () {
    $page = filteredStorePage();

    // Nothing listed at all: no filter would help, so offering "Clear all
    // filters" would be offering a fix for a problem the shopper does not have.
    get("/{$page->slug}/products")
        ->assertOk()
        ->assertSee('Nothing listed yet')
        ->assertDontSee('Clear all filters');

    productOn($page, ['name' => 'Ankara Print', 'slug' => 'ankara']);

    // Something listed, but not what was asked for: the filter is the problem.
    get("/{$page->slug}/products?q=zzzznothing")
        ->assertOk()
        ->assertSee('Nothing matched')
        ->assertSee('Clear all filters');
});

it('shows the strikethrough only when the compare-at price is genuinely higher', function () {
    $page = filteredStorePage();

    productOn($page, [
        'name' => 'Real Discount', 'slug' => 'discount',
        'price_kobo' => 700000, 'compare_at_kobo' => 1000000,
    ]);

    // A seller who typed the two the wrong way round gets a plain price, not a
    // negative "save".
    productOn($page, [
        'name' => 'Mistyped Compare', 'slug' => 'mistyped',
        'price_kobo' => 1000000, 'compare_at_kobo' => 700000,
    ]);

    $html = get("/{$page->slug}/products")->assertOk()->getContent();

    expect($html)->toContain('−30%')->not->toContain('−-30%');
});

it('searches services and keeps a billing unit on the price', function () {
    $page = filteredServicePage();

    serviceOn($page, [
        'name' => 'Bridal Makeup', 'slug' => 'bridal',
        'description' => 'Full day bridal trial and application.',
        'price_kobo' => 8500000, 'billing_unit' => BillingUnit::PerProject,
    ]);
    serviceOn($page, ['name' => 'Deep Tissue Massage', 'slug' => 'massage', 'price_kobo' => 3000000]);

    expect(resultNames(get("/{$page->slug}/services?q=bridal")->assertOk()))
        ->toBe(['Bridal Makeup']);

    // A price with no unit is a number a buyer has to email about.
    get("/{$page->slug}/services")->assertOk()->assertSee('/project');
});

it('filters services by how long they take', function () {
    $page = filteredServicePage();

    serviceOn($page, ['name' => 'Quick Fix', 'slug' => 'quick', 'duration_min' => 30]);
    serviceOn($page, ['name' => 'Full Day Bridal', 'slug' => 'bridal', 'duration_min' => 300]);

    expect(resultNames(get("/{$page->slug}/services?duration=60")->assertOk()))
        ->toBe(['Quick Fix']);
});

it('filters services by billing unit', function () {
    $page = filteredServicePage();

    serviceOn($page, ['name' => 'Retainer Work', 'slug' => 'retainer', 'billing_unit' => BillingUnit::PerMonth]);
    serviceOn($page, ['name' => 'One Off Job', 'slug' => 'oneoff', 'billing_unit' => BillingUnit::PerProject]);

    expect(resultNames(get("/{$page->slug}/services?unit=per_month")->assertOk()))
        ->toBe(['Retainer Work']);
});

it('never lists a paused service', function () {
    $page = filteredServicePage();

    serviceOn($page, ['name' => 'Live Service', 'slug' => 'live']);
    serviceOn($page, ['name' => 'Paused Service', 'slug' => 'paused', 'is_active' => false]);

    expect(resultNames(get("/{$page->slug}/services")->assertOk()))
        ->toBe(['Live Service']);
});

it('puts a featured service above the rest', function () {
    $page = filteredServicePage();

    serviceOn($page, ['name' => 'Ordinary Service', 'slug' => 'ordinary', 'position' => 0]);
    serviceOn($page, ['name' => 'Showcase Service', 'slug' => 'showcase', 'position' => 5, 'is_featured' => true]);

    // Featured outranks the seller's own position: it is a promotion, and a
    // promotion that lands fifth is not one.
    expect(resultNames(get("/{$page->slug}/services?sort=featured")->assertOk()))
        ->toBe(['Showcase Service', 'Ordinary Service']);
});

it('lists the deliverables a service actually carries', function () {
    $page = filteredServicePage();

    serviceOn($page, [
        'name' => 'Bridal Makeup', 'slug' => 'bridal',
        'deliverables' => ['Trial session', 'Day-of application', 'Lash application', 'Touch-up kit', 'Photo retouching'],
        'turnaround_label' => 'Same day',
    ]);

    $html = get("/{$page->slug}/services")->assertOk()->getContent();

    expect($html)->toContain('Trial session')
        ->and($html)->toContain('Touch-up kit')
        // Four shown, the fifth counted: a card that lists every line has stopped
        // being a card.
        ->and($html)->toContain('+1 more')
        ->and($html)->toContain('Same day');
});

it('ignores an empty deliverable rather than rendering a blank tick', function () {
    $page = filteredServicePage();

    serviceOn($page, ['name' => 'Sloppy Service', 'slug' => 'sloppy', 'deliverables' => ['Real item', '', '   ']]);

    get("/{$page->slug}/services")->assertOk()->assertSee('Real item');
});

it('sorts services by price and keeps the filter on the pagination links', function () {
    $page = filteredServicePage();

    serviceOn($page, ['name' => 'Cheap Service', 'slug' => 'cheap', 'price_kobo' => 500000]);
    serviceOn($page, ['name' => 'Dear Service', 'slug' => 'dear', 'price_kobo' => 9000000]);

    expect(resultNames(get("/{$page->slug}/services?sort=price_low")->assertOk()))
        ->toBe(['Cheap Service', 'Dear Service'])
        ->and(resultNames(get("/{$page->slug}/services?sort=price_high")->assertOk()))
        ->toBe(['Dear Service', 'Cheap Service']);

    $html = get("/{$page->slug}/services?q=service&sort=price_high&page=2")->assertOk()->getContent();

    expect($html)->toContain('q=service')->and($html)->toContain('sort=price_high');
});

it('keeps the store and service routes apart', function () {
    $store = filteredStorePage();
    $service = filteredServicePage();

    // A guard that fails the wrong way leaks one theme's content into the
    // other's URL, which is how a service page starts selling products.
    get("/{$service->slug}/products")->assertNotFound();
    get("/{$store->slug}/services")->assertNotFound();
});
