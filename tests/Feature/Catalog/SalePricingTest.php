<?php

use App\Domains\Ai\Services\StoreGrounding;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Directory\Services\StructuredData;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use App\Shared\Support\WhatsApp;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function saleStorePage(array $attributes = []): StorePage
{
    return StorePage::factory()->create(array_merge([
        'theme' => 'store',
        'status' => 'published',
        'published_at' => now(),
        'whatsapp_number' => '08031234567',
    ], $attributes));
}

function saleServicePage(array $attributes = []): StorePage
{
    return StorePage::factory()->create(array_merge([
        'theme' => 'service',
        'status' => 'published',
        'published_at' => now(),
    ], $attributes));
}

function saleProduct(StorePage $page, array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'status' => 'active',
        'name' => 'Ankara Sale Piece',
        'slug' => 'ankara-sale-piece',
        'price_kobo' => 15_000,
        'compare_at_kobo' => 20_000,
        'sale_starts_at' => now()->subDay(),
        'sale_ends_at' => now()->addDays(3),
    ], $attributes));
}

function saleService(StorePage $page, array $attributes = []): Service
{
    return Service::withoutGlobalScopes()->create(array_merge([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'is_active' => true,
        'name' => 'Sale Braids',
        'slug' => 'sale-braids',
        'duration_min' => 60,
        'price_kobo' => 50_000,
        'compare_at_kobo' => 80_000,
        'sale_starts_at' => now()->subDay(),
        'sale_ends_at' => now()->addDays(3),
    ], $attributes));
}

it('activates a sale only inside its window and only above the charged price', function () {
    $page = saleStorePage();
    $product = saleProduct($page);

    expect($product->isOnSale())->toBeTrue()
        ->and($product->effectivePriceKobo())->toBe(15_000)
        ->and($product->originalPriceKobo())->toBe(20_000)
        ->and($product->discountPercent())->toBe(25)
        ->and($product->saleLabel())->toBe('Sale')
        ->and($product->saleEndsLabel())->toBe('Sale ends in 3 days');

    $product->update(['sale_starts_at' => now()->addDay()]);

    expect($product->fresh()->isOnSale())->toBeFalse()
        ->and($product->fresh()->originalPriceKobo())->toBeNull();

    $product->update(['sale_starts_at' => now()->subDay(), 'sale_ends_at' => now()->subSecond()]);

    expect($product->fresh()->isOnSale())->toBeFalse()
        ->and($product->fresh()->saleEndsAt())->toBeNull();

    $product->update([
        'sale_ends_at' => now()->addDay(),
        'compare_at_kobo' => 15_000,
    ]);

    expect($product->fresh()->isOnSale())->toBeFalse()
        ->and($product->fresh()->discountPercent())->toBeNull();
});

it('applies the same window rules to services', function () {
    $page = saleServicePage();
    $service = saleService($page);

    expect($service->isOnSale())->toBeTrue()
        ->and($service->effectivePriceKobo())->toBe(50_000)
        ->and($service->originalPriceKobo())->toBe(80_000)
        ->and($service->discountPercent())->toBe(38);

    $service->update(['sale_starts_at' => now()->addHour()]);

    expect($service->fresh()->isOnSale())->toBeFalse();
});

it('renders the sale price and struck-through original on the product page', function () {
    $page = saleStorePage();
    $product = saleProduct($page);

    $html = get(storeUrl($page->slug, '/products/'.$product->slug))
        ->assertOk()
        ->assertSee('Sale −25%')
        ->assertSee(Money::format(15_000))
        ->assertSee(Money::format(20_000))
        ->getContent();

    expect($html)->toContain('<del');
});

it('does not advertise an expired sale', function () {
    $page = saleStorePage();
    $product = saleProduct($page, [
        'sale_starts_at' => now()->subDays(5),
        'sale_ends_at' => now()->subDay(),
    ]);

    get(storeUrl($page->slug, '/products/'.$product->slug))
        ->assertOk()
        ->assertDontSee('Sale −');
});

it('keeps the deposit and amount due based on the charged sale price', function () {
    $page = saleServicePage();
    $service = saleService($page, [
        'deposit_type' => 'percent',
        'deposit_value' => 20,
    ]);

    expect($service->amountDueKobo())->toBe(10_000)
        ->and($service->priceLabel())->toBe(Money::format(50_000));
});

it('shows service sale pricing in the booking widget', function () {
    $page = saleServicePage();
    $service = saleService($page);
    $service->storePage->update(['name' => 'Sale Studio']);

    $html = get(storeUrl($page->slug, '/book'))
        ->assertOk()
        ->assertSee(Money::format(50_000))
        ->assertSee(Money::format(80_000))
        ->getContent();

    expect($html)->toContain('<del');
});

it('filters by the charged sale price rather than the original price', function () {
    $page = saleStorePage();
    saleProduct($page, ['name' => 'Inside Budget']);
    saleProduct($page, [
        'name' => 'Outside Budget',
        'slug' => 'outside-budget',
        'price_kobo' => 25_000,
        'compare_at_kobo' => 30_000,
    ]);

    get(storeUrl($page->slug, '/products?min=100&max=160'))
        ->assertOk()
        ->assertSee('Inside Budget')
        ->assertDontSee('Outside Budget');
});

it('returns effective and original prices in storefront search', function () {
    $page = saleStorePage();
    saleProduct($page);

    $json = get(storeUrl($page->slug, '/search?q=Ankara'))
        ->assertOk()
        ->json();

    expect($json['mode'])->toBe('keyword_fallback')
        ->and($json['results'][0]['price'])->toBe(Money::format(15_000))
        ->and($json['results'][0]['original_price'])->toBe(Money::format(20_000));
});

it('grounds store AI in the sale price rather than the comparison price', function () {
    $page = saleStorePage();
    saleProduct($page);
    saleService(saleServicePage(['seller_id' => $page->seller_id]), [
        'store_page_id' => $page->id,
    ]);

    $grounding = app(StoreGrounding::class)->forPage($page);

    expect($grounding['products'][0])
        ->toMatchArray([
            'price_kobo' => 15_000,
            'original_price_kobo' => 20_000,
        ])
        ->and($grounding['products'][0]['sale_ends_at'])->not->toBeNull()
        ->and($grounding['services'][0])
        ->toMatchArray([
            'price_kobo' => 50_000,
            'original_price_kobo' => 80_000,
        ]);
});

it('publishes the charged price and sale end in structured data', function () {
    $page = saleStorePage();
    $product = saleProduct($page);

    $data = StructuredData::forPage($page->fresh());
    $offer = collect($data['makesOffer'])->firstWhere('name', $product->name);

    expect($offer)->not->toBeNull()
        ->and($offer['price'])->toBe('150.00')
        ->and($offer['priceValidUntil'])->toBe(Carbon::parse($product->sale_ends_at)->toDateString());

    $servicePage = saleServicePage();
    $service = saleService($servicePage);

    $serviceData = StructuredData::forPage($servicePage->fresh());
    $serviceOffer = $serviceData['hasOfferCatalog']['itemListElement'][0];

    expect($serviceOffer['price'])->toBe('500.00')
        ->and($serviceOffer['priceValidUntil'])->toBe(Carbon::parse($service->sale_ends_at)->toDateString());
});

it('mentions the original price in the product WhatsApp handoff', function () {
    $page = saleStorePage();
    $product = saleProduct($page);

    $url = WhatsApp::productLink($page, $product);

    expect(urldecode($url))
        ->toContain(Money::format(15_000))
        ->toContain('was '.Money::format(20_000));
});

it('validates product sale prices and windows', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->post('/products', [
        'name' => 'Bad Sale',
        'price' => '100',
        'compare_at' => '100',
        'status' => 'active',
    ])->assertSessionHasErrors('compare_at');

    actingAs($seller)->post('/products', [
        'name' => 'Bad Window',
        'price' => '100',
        'compare_at' => '150',
        'sale_starts_at' => now()->addDays(2)->toDateTimeString(),
        'sale_ends_at' => now()->addDay()->toDateTimeString(),
        'status' => 'active',
    ])->assertSessionHasErrors('sale_ends_at');

    actingAs($seller)->post('/products', [
        'name' => 'Good Sale',
        'price' => '100',
        'compare_at' => '150',
        'status' => 'active',
    ])->assertRedirect();

    $product = Product::withoutGlobalScopes()->where('name', 'Good Sale')->sole();

    expect($product->price_kobo)->toBe(10_000)
        ->and($product->compare_at_kobo)->toBe(15_000)
        ->and($product->sale_starts_at)->toBeNull()
        ->and($product->sale_ends_at)->toBeNull();
});

it('validates service sale prices and clears dates without a comparison price', function () {
    [$seller] = sellerWithPage(['theme' => 'service', 'status' => 'published']);

    actingAs($seller)->post(route('services.store'), [
        'name' => 'Bad Service Sale',
        'duration_min' => 60,
        'price' => '200',
        'compare_at' => '200',
        'deposit_type' => 'none',
    ])->assertSessionHasErrors('compare_at');

    actingAs($seller)->post(route('services.store'), [
        'name' => 'Good Service Sale',
        'duration_min' => 60,
        'price' => '200',
        'compare_at' => '250',
        'deposit_type' => 'none',
        'is_active' => '1',
    ])->assertRedirect(route('services.index'));

    $service = Service::withoutGlobalScopes()->where('name', 'Good Service Sale')->sole();

    expect($service->price_kobo)->toBe(20_000)
        ->and($service->compare_at_kobo)->toBe(25_000)
        ->and($service->sale_starts_at)->toBeNull()
        ->and($service->sale_ends_at)->toBeNull();
});
