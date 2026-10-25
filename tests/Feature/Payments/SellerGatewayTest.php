<?php

use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seller = Seller::factory()->create();
    StorePage::factory()->for($this->seller)->create([
        'name' => 'Key Shop',
        'slug' => 'key-shop',
        'theme' => 'store',
        'status' => 'published',
    ]);
});

/**
 * Confirm a password so password.confirm lets the request through.
 */
function confirmPasswordForTest(): void
{
    session(['auth.password_confirmed_at' => time()]);
}

it('shows the payment keys screen without echoing a saved secret', function () {
    $secret = 'sk_test_'.str_repeat('a', 30).'4f2a';

    SellerGateway::factory()->create([
        'seller_id' => $this->seller->id,
        'secret_key' => $secret,
        'secret_key_last4' => '4f2a',
    ]);

    $this->actingAs($this->seller)
        ->get(route('payments.gateways.index'))
        ->assertOk()
        ->assertSee('Paystack')
        ->assertSee('••••4f2a')
        ->assertDontSee($secret);
});

it('verifies a test key before saving it, encrypted', function () {
    Http::fake([
        'api.paystack.co/balance' => Http::response(['status' => true, 'data' => []]),
    ]);

    confirmPasswordForTest();

    $secret = 'sk_test_'.str_repeat('b', 30).'9c3d';

    $this->actingAs($this->seller)
        ->post(route('payments.gateways.store'), [
            'gateway' => 'paystack',
            'public_key' => 'pk_test_example',
            'secret_key' => $secret,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $raw = DB::table('seller_gateways')->first();

    expect($raw)->not->toBeNull()
        ->and($raw->secret_key)->not->toBe($secret)
        ->and($raw->environment)->toBe('test')
        ->and($raw->secret_key_last4)->toBe('9c3d')
        ->and($raw->verified_at)->not->toBeNull();

    $gateway = SellerGateway::withoutGlobalScopes()->firstOrFail();

    expect($gateway->secretKey())->toBe($secret);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/balance')
        && $request->hasHeader('Authorization', 'Bearer '.$secret));
});

it('refuses a key the gateway rejects and saves nothing', function () {
    Http::fake([
        'api.paystack.co/balance' => Http::response(['message' => 'Invalid key'], 401),
    ]);

    confirmPasswordForTest();

    $this->actingAs($this->seller)
        ->post(route('payments.gateways.store'), [
            'gateway' => 'paystack',
            'secret_key' => 'sk_test_'.str_repeat('c', 30),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('secret_key');

    expect(SellerGateway::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses to save when the gateway cannot be reached', function () {
    Http::fake([
        'api.paystack.co/balance' => Http::response('Upstream unavailable', 503),
    ]);

    confirmPasswordForTest();

    $this->actingAs($this->seller)
        ->post(route('payments.gateways.store'), [
            'gateway' => 'paystack',
            'secret_key' => 'sk_test_'.str_repeat('d', 30),
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('secret_key');

    expect(SellerGateway::withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a live key outside production and a malformed key anywhere', function (string $secret) {
    Http::fake();

    confirmPasswordForTest();

    $this->actingAs($this->seller)
        ->post(route('payments.gateways.store'), [
            'gateway' => 'paystack',
            'secret_key' => $secret,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('secret_key');

    expect(SellerGateway::withoutGlobalScopes()->count())->toBe(0);

    Http::assertNothingSent();
})->with([
    'live key' => 'sk_live_'.str_repeat('e', 30),
    'not a paystack key' => 'pk_test_'.str_repeat('f', 30),
]);

it('requires a password confirmation before writing a key', function () {
    Http::fake([
        'api.paystack.co/balance' => Http::response(['status' => true, 'data' => []]),
    ]);

    $this->actingAs($this->seller)
        ->post(route('payments.gateways.store'), [
            'gateway' => 'paystack',
            'secret_key' => 'sk_test_'.str_repeat('g', 30),
        ])
        ->assertRedirect(route('password.confirm'));

    expect(SellerGateway::withoutGlobalScopes()->count())->toBe(0);
});

it('keeps an existing webhook secret when the replacement leaves it blank', function () {
    Http::fake([
        'api.paystack.co/balance' => Http::response(['status' => true, 'data' => []]),
    ]);

    $gateway = SellerGateway::factory()->create([
        'seller_id' => $this->seller->id,
        'secret_key' => 'sk_test_'.str_repeat('h', 30),
    ]);

    $gateway->setWebhookSecret('webhook-secret-kept');
    $gateway->save();

    confirmPasswordForTest();

    $this->actingAs($this->seller)
        ->post(route('payments.gateways.store'), [
            'gateway' => 'paystack',
            'secret_key' => 'sk_test_'.str_repeat('i', 30),
        ])
        ->assertSessionHasNoErrors();

    $fresh = SellerGateway::withoutGlobalScopes()->firstOrFail();

    expect($fresh->webhook_secret)->toBe('webhook-secret-kept')
        ->and($fresh->secretKey())->toBe('sk_test_'.str_repeat('i', 30));
});

it('removes a key the seller owns and refuses another seller\'s key', function () {
    $mine = SellerGateway::factory()->create(['seller_id' => $this->seller->id]);
    $other = SellerGateway::factory()->create();

    confirmPasswordForTest();

    $this->actingAs($this->seller)
        ->delete(route('payments.gateways.destroy', 'paystack'))
        ->assertRedirect();

    expect(SellerGateway::withoutGlobalScopes()->whereKey($mine->id)->exists())->toBeFalse()
        ->and(SellerGateway::withoutGlobalScopes()->whereKey($other->id)->exists())->toBeTrue();

    $this->actingAs($this->seller)
        ->delete(route('payments.gateways.destroy', 'flutterwave'))
        ->assertNotFound();
});

it('never serialises the secret keys', function () {
    $gateway = SellerGateway::factory()->create([
        'seller_id' => $this->seller->id,
        'secret_key' => 'sk_test_'.str_repeat('j', 30),
    ]);

    $gateway->setWebhookSecret('a-webhook-secret');
    $gateway->save();

    expect($gateway->toArray())
        ->not->toHaveKey('secret_key')
        ->not->toHaveKey('webhook_secret');
});
