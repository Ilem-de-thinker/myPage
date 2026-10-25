<?php

use App\Domains\Booking\Models\Booking;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\BuyerLinks;

use function Pest\Laravel\get;

/**
 * The link a buyer keeps.
 *
 * Buyers on this platform have no account, so "where is my order" has exactly
 * one answer: a URL. These tests hold that answer to the standard it has to meet
 * — it opens the right record, it opens nothing else, and it stops working.
 */

/** @return array{0: Seller, 1: StorePage} */
function buyerLinkStore(): array
{
    $seller = Seller::factory()->create();

    $page = StorePage::factory()->for($seller)->create([
        'theme' => 'store',
        'status' => 'published',
    ]);

    return [$seller, $page];
}

/** @return array{0: Seller, 1: StorePage} */
function buyerLinkService(): array
{
    $seller = Seller::factory()->create();

    $page = StorePage::factory()->for($seller)->create([
        'theme' => 'service',
        'status' => 'published',
    ]);

    return [$seller, $page];
}

it('opens the order it was minted for', function () {
    [$seller, $page] = buyerLinkStore();

    $order = Order::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'reference' => 'NG-7788',
    ]);

    get(BuyerLinks::order($page, $order))
        ->assertOk()
        ->assertSee('NG-7788');
});

it('opens the booking it was minted for', function () {
    [$seller, $page] = buyerLinkService();

    $booking = Booking::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'reference' => 'BK-7788',
    ]);

    get(BuyerLinks::booking($page, $booking))
        ->assertOk()
        ->assertSee('BK-7788');
});

/**
 * The whole security model in one assertion: the signature is the authorisation,
 * so an unsigned or tampered URL must not open anything.
 */
it('refuses a link whose signature is not ours', function () {
    [$seller, $page] = buyerLinkStore();

    Order::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'reference' => 'NG-7788',
    ]);

    get(storeUrl($page->slug, '/o/NG-7788'))->assertForbidden();

    $signed = BuyerLinks::order($page, Order::withoutGlobalScopes()->where('reference', 'NG-7788')->first());

    // Same reference, another studio's slug: the link must not be replayable.
    $other = buyerLinkStore()[1];

    get(str_replace("//{$page->slug}.", "//{$other->slug}.", $signed))->assertForbidden();
});

it('refuses a booking link that is not signed', function () {
    [$seller, $page] = buyerLinkService();

    Booking::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'reference' => 'BK-7788',
    ]);

    get(storeUrl($page->slug, '/b/BK-7788'))->assertForbidden();
});

it('stops working once it expires', function () {
    [$seller, $page] = buyerLinkStore();

    $order = Order::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'reference' => 'NG-7788',
    ]);

    $link = BuyerLinks::order($page, $order);

    // Works today.
    get($link)->assertOk();

    // And stops working on the last day. A link that never expires is a
    // permanent public URL for somebody's address and order history.
    $this->travel(BuyerLinks::EXPIRES_DAYS + 1)->days();

    get($link)->assertForbidden();
});

it('does not ask a buyer on their own link to prove it again', function () {
    [$seller, $page] = buyerLinkStore();

    $order = Order::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $seller->id,
        'reference' => 'NG-7788',
    ]);

    $html = get(BuyerLinks::order($page, $order))->assertOk()->getContent();

    // The lookup form is there for somebody with only a reference. Somebody who
    // followed the link already has their authorisation.
    expect($html)->not->toContain('Check Status');
});

it('will not serve a store order link to a service page', function () {
    [$seller, $store] = buyerLinkStore();
    [, $service] = buyerLinkService();

    $order = Order::factory()->create([
        'store_page_id' => $store->id,
        'seller_id' => $seller->id,
        'reference' => 'NG-7788',
    ]);

    $link = BuyerLinks::order($store, $order);

    get(str_replace("//{$store->slug}.", "//{$service->slug}.", $link))->assertForbidden();
});
