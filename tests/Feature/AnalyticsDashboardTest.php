<?php

use App\Domains\Analytics\Models\StoreEvent;
use App\Domains\Seller\Models\StorePage;

use function Pest\Laravel\actingAs;

it('shows tenant scoped storefront analytics and an AI summary entry point on the dashboard', function () {
    [$seller, $page] = sellerWithPage();
    $otherPage = StorePage::factory()->create();

    StoreEvent::create(['store_page_id' => $page->id, 'event' => 'view', 'visitor_hash' => 'visitor-a']);
    StoreEvent::create(['store_page_id' => $page->id, 'event' => 'view', 'visitor_hash' => 'visitor-a']);
    StoreEvent::create(['store_page_id' => $page->id, 'event' => 'whatsapp_tap']);
    StoreEvent::create(['store_page_id' => $otherPage->id, 'event' => 'view', 'visitor_hash' => 'other-visitor']);

    actingAs($seller)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Storefront analytics')
        ->assertSee('2 visits from 1 unique visitors')
        ->assertSee('WhatsApp taps')
        ->assertSee('Use Mercho summary')
        ->assertSee(route('dashboard.analytics.index'))
        ->assertDontSee('other-visitor');
});

it('shows the dedicated analytics page in the seller workspace', function () {
    [$seller] = sellerWithPage();

    actingAs($seller)->get(route('dashboard.analytics.index'))
        ->assertOk()
        ->assertSee('Weekly Mercho digest')
        ->assertSee('Ask your data');
});
