<?php

use App\Domains\Chat\Models\ChatThread;

it('does not let a guest chat token cross store tenants', function () {
    [, $pageA] = sellerWithPage(['slug' => 'chat-shop-a', 'status' => 'published']);
    [, $pageB] = sellerWithPage(['slug' => 'chat-shop-b', 'status' => 'published']);

    $opened = $this->postJson(storeUrl($pageA->slug, '/chat'), [
        'name' => 'Buyer',
        'email' => 'buyer@example.com',
        'subject' => 'Question about my order',
        'body' => 'Can you confirm the delivery window?',
    ])->assertCreated()->json();

    expect(ChatThread::query()->where('store_page_id', $pageA->id)->exists())->toBeTrue();

    $this->getJson(storeUrl($pageB->slug, '/chat/'.$opened['thread_id'].'?token='.$opened['token']))
        ->assertNotFound();
});
