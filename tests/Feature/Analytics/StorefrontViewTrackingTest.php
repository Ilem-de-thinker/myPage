<?php

use App\Domains\Analytics\Models\StoreEvent;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;

/**
 * @return array{0: Seller, 1: StorePage}
 */
function trackedStorePage(string $theme = 'store'): array
{
    return sellerWithPage([
        'theme' => $theme,
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('counts one home view per visitor IP per day', function () {
    [, $page] = trackedStorePage();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get(storeUrl($page->slug))->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get(storeUrl($page->slug))->assertOk();

    expect($page->fresh()->view_count)->toBe(1)
        ->and(StoreEvent::query()->where('store_page_id', $page->id)->where('event', 'view')->count())->toBe(1);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])->get(storeUrl($page->slug))->assertOk();

    expect($page->fresh()->view_count)->toBe(2);
});

it('counts the same IP again on a new day', function () {
    [, $page] = trackedStorePage();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get(storeUrl($page->slug))->assertOk();

    $this->travelTo(now()->addDay());

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get(storeUrl($page->slug))->assertOk();

    expect($page->fresh()->view_count)->toBe(2)
        ->and(StoreEvent::query()->where('store_page_id', $page->id)->where('event', 'view')->count())->toBe(2);
});

it('does not count a logged-in seller viewing their own storefront', function () {
    [$seller, $page] = trackedStorePage();

    actingAs($seller)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->get(storeUrl($page->slug))
        ->assertOk();

    expect($page->fresh()->view_count)->toBe(0)
        ->and(StoreEvent::query()->where('store_page_id', $page->id)->count())->toBe(0);
});

it('deduplicates product views by IP and ignores the owner', function () {
    [$seller, $page] = trackedStorePage();
    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'status' => 'active',
    ]);
    $url = storeUrl($page->slug, '/products/'.$product->slug);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get($url)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get($url)->assertOk();

    expect($product->fresh()->view_count)->toBe(1)
        ->and(StoreEvent::query()->where('store_page_id', $page->id)->where('event', 'product_view')->count())->toBe(1);

    actingAs($seller)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->get($url)
        ->assertOk();

    expect($product->fresh()->view_count)->toBe(1);
});

it('deduplicates service views by IP and ignores the owner', function () {
    [$seller, $page] = trackedStorePage('service');
    $service = Service::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
    ]);
    $url = storeUrl($page->slug, '/services/'.$service->slug);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get($url)->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->get($url)->assertOk();

    expect($service->fresh()->view_count)->toBe(1)
        ->and(StoreEvent::query()->where('store_page_id', $page->id)->where('event', 'service_view')->count())->toBe(1);

    actingAs($seller)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->get($url)
        ->assertOk();

    expect($service->fresh()->view_count)->toBe(1);
});
