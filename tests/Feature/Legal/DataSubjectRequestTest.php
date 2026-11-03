<?php

use App\Domains\Auth\Models\DataSubjectRequest;
use App\Domains\Auth\Notifications\DataSubjectRequestSubmitted;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('records a data request with a one-month deadline and tells the admins', function () {
    Notification::fake();
    $admin = Seller::factory()->coreAdmin()->create();

    post(route('legal.data-request.store'), [
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'request_type' => 'access',
        'details' => 'Please send me everything you hold.',
    ])->assertRedirect();

    $request = DataSubjectRequest::query()->sole();

    expect($request->status)->toBe('pending')
        ->and($request->verified_at)->toBeNull()
        ->and($request->due_at->isSameDay(now()->addDays(30)))->toBeTrue();

    Notification::assertSentTo($admin, DataSubjectRequestSubmitted::class);
});

it('verifies a request that comes from the signed-in seller account', function () {
    Notification::fake();

    $seller = Seller::factory()->create(['email' => 'owner@shop.ng']);

    actingAs($seller)->post(route('legal.data-request.store'), [
        'name' => 'Owner',
        'email' => 'OWNER@shop.ng',
        'request_type' => 'deletion',
    ])->assertRedirect();

    $request = DataSubjectRequest::query()->sole();

    expect($request->seller_id)->toBe($seller->id)
        ->and($request->verified_at)->not->toBeNull();
});

it('lets an admin work the request through the manual inbox', function () {
    $admin = Seller::factory()->coreAdmin()->create();
    $request = DataSubjectRequest::create([
        'name' => 'Ada Buyer',
        'email' => 'ada@example.com',
        'request_type' => 'access',
        'status' => 'pending',
        'requested_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    actingAs($admin)->get(route('admin.data-requests'))
        ->assertOk()
        ->assertSee('Ada Buyer');

    actingAs($admin)->post(route('admin.data-requests.update', $request), [
        'status' => 'resolved',
        'resolution_note' => 'Exported the data and emailed it.',
        'verified' => '1',
    ])->assertRedirect();

    $request->refresh();

    expect($request->status)->toBe('resolved')
        ->and($request->verified_at)->not->toBeNull()
        ->and($request->resolved_at)->not->toBeNull()
        ->and($request->resolution_note)->toBe('Exported the data and emailed it.');
});

it('hard deletes a verified seller catalogue for a deletion request', function () {
    Storage::fake('public');

    [$seller, $page] = sellerWithPage();
    $product = Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);
    $path = "products/{$product->id}/photo.webp";
    Storage::disk('public')->put($path, 'img');
    $product->images()->create(['path' => $path, 'position' => 0]);

    $admin = Seller::factory()->coreAdmin()->create();
    $request = DataSubjectRequest::create([
        'seller_id' => $seller->id,
        'name' => $seller->name,
        'email' => $seller->email,
        'request_type' => 'deletion',
        'status' => 'pending',
        'verified_at' => now(),
        'requested_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    actingAs($admin)->post(route('admin.data-requests.purge', $request))->assertRedirect();

    expect(Product::withTrashed()->whereKey($product->id)->exists())->toBeFalse()
        ->and($request->fresh()->status)->toBe('resolved');
    Storage::disk('public')->assertMissing($path);
});

it('refuses to purge an unverified or non-deletion request', function () {
    [$seller, $page] = sellerWithPage();
    Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    $admin = Seller::factory()->coreAdmin()->create();
    $request = DataSubjectRequest::create([
        'seller_id' => $seller->id,
        'name' => $seller->name,
        'email' => $seller->email,
        'request_type' => 'access',
        'status' => 'pending',
        'verified_at' => now(),
        'requested_at' => now(),
        'due_at' => now()->addDays(30),
    ]);

    actingAs($admin)->post(route('admin.data-requests.purge', $request))->assertStatus(422);
    expect(Product::query()->where('seller_id', $seller->id)->count())->toBe(1);
});
