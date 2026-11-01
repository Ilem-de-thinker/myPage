<?php

use App\Domains\Admin\Models\SupportMessage;
use App\Domains\Admin\Models\SupportRequest;
use App\Domains\Admin\Models\SupportRequestStatus;
use App\Domains\Seller\Models\Seller;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lets a buyer send a store support request to mypage.ng', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published', 'slug' => 'support-store']);

    get(storeUrl($page->slug))
        ->assertOk()
        ->assertSee('Open support form')
        ->assertSee('Report to mypage.ng');

    $response = post(storeUrl($page->slug, '/support'), [
        'destination' => 'platform',
        'category' => 'order',
        'subject' => 'I need help with my order',
        'body' => 'The order confirmation did not arrive and I need help checking its status.',
        'requester_name' => 'Ada Buyer',
        'requester_contact' => 'ada@example.com',
    ])->assertRedirect()->assertSessionHas('support_sent');

    $request = SupportRequest::sole();

    expect($request->source)->toBe('buyer')
        ->and($request->ticket_number)->toStartWith('MP-')
        ->and($request->store_page_id)->toBe($page->id)
        ->and($request->seller_id)->toBeNull()
        ->and($request->status)->toBe(SupportRequestStatus::Open)
        ->and(SupportMessage::whereBelongsTo($request, 'supportRequest')->sole()->sender_type)->toBe('buyer');

    $ticketUrl = $response->getSession()->get('support_ticket_url');

    get(storeUrl($page->slug, $ticketUrl))->assertOk()->assertSee($request->ticket_number);

    $replyUrl = '/support/tickets/'.$request->ticket_number.'/reply?'.parse_url($ticketUrl, PHP_URL_QUERY);

    post(storeUrl($page->slug, $replyUrl), [
        'body' => 'Here is a follow-up message about the same order issue.',
    ])->assertRedirect();

    expect($request->fresh()->messages()->count())->toBe(2);
});

it('lets a seller create and review their own support requests', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published']);

    actingAs($seller)->post(route('support.store'), [
        'category' => 'technical',
        'subject' => 'My storefront is not loading',
        'body' => 'The storefront shows an error when I open it from the dashboard.',
    ])->assertRedirect()->assertSessionHas('status');

    actingAs($seller)->get(route('support.index'))
        ->assertOk()
        ->assertSee('Open support form')
        ->assertSee('My storefront is not loading');

    actingAs($seller)->get(route('support.show', SupportRequest::sole()))
        ->assertOk()
        ->assertSee('Reply to this ticket');

    expect(SupportRequest::sole()->seller_id)->toBe($seller->id);

    actingAs($seller)->post(route('support.reply', SupportRequest::sole()), [
        'body' => 'I have added another detail to help resolve this ticket.',
    ])->assertRedirect()->assertSessionHas('status');

    expect(SupportRequest::sole()->messages()->count())->toBe(2);

    post(storeUrl($page->slug, '/support'), [
        'destination' => 'store',
        'category' => 'question',
        'subject' => 'A buyer needs help from my store',
        'body' => 'Please help me answer this buyer question about the order.',
    ])->assertRedirect()->assertSessionHas('support_sent');

    actingAs($seller)->get(route('support.index'))
        ->assertSee('A buyer needs help from my store');

    expect(SupportRequest::where('source', 'store')->sole()->seller_id)->toBe($seller->id);
});

it('keeps support queue access for admins only and lets admins update requests', function () {
    [$seller, $page] = sellerWithPage(['status' => 'published']);
    $supportRequest = SupportRequest::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)->get(route('admin.support'))->assertNotFound();

    $admin = Seller::factory()->admin()->create();

    actingAs($admin)->get(route('admin.support'))
        ->assertOk()
        ->assertSee($supportRequest->subject);

    actingAs($admin)->get(route('admin.support.show', $supportRequest))
        ->assertOk()
        ->assertSee('Reply to ticket');

    actingAs($admin)->post(route('admin.support.update', $supportRequest), [
        'status' => SupportRequestStatus::Resolved->value,
        'reply' => 'Resolved after checking the account.',
    ])->assertRedirect();

    expect($supportRequest->fresh()->status)->toBe(SupportRequestStatus::Resolved)
        ->and($supportRequest->fresh()->resolved_at)->not->toBeNull();
});

it('paginates the seller support queue', function () {
    [$seller] = sellerWithPage();

    SupportRequest::factory()->count(21)->create(['seller_id' => $seller->id]);

    actingAs($seller)->get(route('support.index'))
        ->assertOk()
        ->assertSee('page=2');
});
