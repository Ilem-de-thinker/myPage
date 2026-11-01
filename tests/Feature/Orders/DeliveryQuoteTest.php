<?php

use App\Domains\Catalog\Enums\DeliveryMode;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Actions\QuoteDelivery;
use App\Domains\Orders\Enums\FulfilmentMode;
use App\Domains\Seller\Models\DeliveryProfile;
use App\Domains\Seller\Models\DeliveryZone;
use App\Domains\Seller\Models\StorePage;

/**
 * A priced line as PriceCart hands it to checkout. The quote reads its own
 * product settings from the database, so only the id and line total matter here.
 *
 * @return array{product_id: int, line_kobo: int}
 */
function deliveryLine(Product $product, ?int $lineKobo = null): array
{
    return [
        'product_id' => $product->getKey(),
        'line_kobo' => $lineKobo ?? $product->price_kobo,
    ];
}

function productFor(StorePage $page, array $attributes = []): Product
{
    return Product::factory()->create(array_merge([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
    ], $attributes));
}

function deliveryZoneFor(DeliveryProfile $profile, array $attributes = []): DeliveryZone
{
    return DeliveryZone::factory()->create(array_merge([
        'delivery_profile_id' => $profile->id,
    ], $attributes));
}

it('charges the zone fee for a delivery order', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    $zone = deliveryZoneFor($profile, ['name' => 'Lekki', 'fee_kobo' => 150_000]);
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)], $zone->id);

    expect($quote)->toMatchArray([
        'mode' => FulfilmentMode::Delivery,
        'fee_kobo' => 150_000,
        'zone' => 'Lekki',
        'free' => false,
    ]);
});

it('makes delivery free once the basket reaches the threshold', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->freeOver(500_000)->create(['store_page_id' => $page->id]);
    $zone = deliveryZoneFor($profile, ['fee_kobo' => 150_000]);
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product, 600_000)], $zone->id);

    expect($quote['fee_kobo'])->toBe(0)
        ->and($quote['free'])->toBeTrue();
});

it('never charges less than the product delivery fee', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    $zone = deliveryZoneFor($profile, ['fee_kobo' => 100_000]);
    $product = productFor($page, ['delivery_fee_kobo' => 250_000]);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)], $zone->id);

    expect($quote['fee_kobo'])->toBe(250_000);
});

it('keeps the zone fee when a product override is cheaper', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    $zone = deliveryZoneFor($profile, ['fee_kobo' => 100_000]);
    $product = productFor($page, ['delivery_fee_kobo' => 40_000]);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)], $zone->id);

    expect($quote['fee_kobo'])->toBe(100_000);
});

it('treats a pickup-only product as a pickup order', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    $zone = deliveryZoneFor($profile);
    $product = productFor($page, ['delivery_mode' => DeliveryMode::Pickup]);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)], $zone->id);

    expect($quote['mode'])->toBe(FulfilmentMode::Pickup)
        ->and($quote['fee_kobo'])->toBe(0);
});

it('honours a buyer who chooses pickup', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    $zone = deliveryZoneFor($profile, ['fee_kobo' => 150_000]);
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)], $zone->id, FulfilmentMode::Pickup);

    expect($quote['mode'])->toBe(FulfilmentMode::Pickup)
        ->and($quote['fee_kobo'])->toBe(0);
});

it('falls back to pickup when the shop does not deliver', function () {
    [, $page] = sellerWithPage();
    DeliveryProfile::factory()->pickupOnly()->create(['store_page_id' => $page->id]);
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)]);

    expect($quote['mode'])->toBe(FulfilmentMode::Pickup);
});

it('returns no fulfilment when the shop offers neither delivery nor pickup', function () {
    [, $page] = sellerWithPage();
    DeliveryProfile::factory()->create([
        'store_page_id' => $page->id,
        'offers_delivery' => false,
        'offers_pickup' => false,
    ]);
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)]);

    expect($quote['mode'])->toBe(FulfilmentMode::None)
        ->and($quote['fee_kobo'])->toBe(0);
});

it('falls back to the shop own first area for an unknown zone', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    deliveryZoneFor($profile, ['name' => 'Ikeja', 'fee_kobo' => 120_000, 'position' => 0]);
    deliveryZoneFor($profile, ['name' => 'Lekki', 'fee_kobo' => 180_000, 'position' => 1]);
    $product = productFor($page);

    $otherProfile = DeliveryProfile::factory()->create();
    $foreignZone = deliveryZoneFor($otherProfile);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)], $foreignZone->id);

    expect($quote['zone'])->toBe('Ikeja')
        ->and($quote['fee_kobo'])->toBe(120_000);
});

it('falls back to the first area when the buyer sends none', function () {
    [, $page] = sellerWithPage();
    $profile = DeliveryProfile::factory()->create(['store_page_id' => $page->id]);
    deliveryZoneFor($profile, ['name' => 'Ikeja', 'fee_kobo' => 120_000]);
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)]);

    expect($quote['zone'])->toBe('Ikeja')
        ->and($quote['fee_kobo'])->toBe(120_000);
});

it('keeps shops without a profile on free WhatsApp delivery', function () {
    [, $page] = sellerWithPage();
    $product = productFor($page);

    $quote = app(QuoteDelivery::class)->handle($page, [deliveryLine($product)]);

    expect($quote)->toMatchArray([
        'mode' => FulfilmentMode::Delivery,
        'fee_kobo' => 0,
        'zone' => null,
        'free' => true,
    ]);
});
