<?php

use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Data\GatewayCredentials;
use App\Domains\Payments\Jobs\ProcessPaymentWebhook;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Models\WebhookEvent;
use App\Domains\Payments\Services\Gateways\FlutterwaveGateway;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

/**
 * The webhook is the path that cannot be raced by a slow browser. These tests
 * exist because the first payment used to land unactivated, and every renewal
 * used to fall off the end of the world.
 */
beforeEach(function () {
    $this->plan = Plan::factory()->withoutTrial()->create(['slug' => 'growth', 'price_kobo' => 950000]);

    config([
        'services.paystack.secret' => 'sk_test_secret',
        'mypage.gateway' => 'paystack',
    ]);
});

function subscriptionClaim(Seller $seller, Plan $plan): Subscription
{
    return app(SubscriptionManager::class)->start($seller, $plan, 'gateway');
}

function deliverEvent(array $payload): void
{
    $event = WebhookEvent::create([
        'gateway' => 'paystack',
        'event_id' => (string) fake()->unique()->uuid(),
        'payload' => $payload,
    ]);

    app()->call([new ProcessPaymentWebhook($event->id), 'handle']);
}

it('activates a subscription when charge.success arrives before the browser callback', function () {
    [$seller] = sellerWithPage();
    fakePaystack();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth']);

    $subscription = Subscription::where('seller_id', $seller->id)->sole();
    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Pending);

    deliverEvent([
        'event' => 'charge.success',
        'data' => ['reference' => $transaction->reference, 'id' => 12345],
    ]);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($transaction->fresh()->status->value)->toBe('paid');
});

it('does not double-activate when both the webhook and the browser callback confirm', function () {
    [$seller] = sellerWithPage();
    fakePaystack();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth']);

    $subscription = Subscription::where('seller_id', $seller->id)->sole();
    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    deliverEvent([
        'event' => 'charge.success',
        'data' => ['reference' => $transaction->reference, 'id' => 12345],
    ]);

    $ends = $subscription->fresh()->current_period_ends_at;

    app(ConfirmTransaction::class)->handle($transaction->reference);

    $confirmed = Subscription::where('seller_id', $seller->id)->sole()->current_period_ends_at;

    // The browser callback does not renew subscription transactions at all.
    expect($confirmed->equalTo($ends))->toBeTrue();
});

it('renews a recurring charge with no local transaction via the subscription code', function () {
    [$seller] = sellerWithPage();
    fakePaystack();

    $manager = app(SubscriptionManager::class);
    $subscription = $manager->renew($manager->start($seller, $this->plan, 'gateway'));
    $subscription->forceFill(['gateway_subscription_code' => 'SUB_live123'])->save();

    // A recurring charge: the reference exists only at the gateway.
    Http::fake([
        'api.paystack.co/transaction/verify/MP-RENEW-1' => Http::response([
            'data' => [
                'status' => 'success',
                'amount' => 950000,
                'currency' => 'NGN',
                'reference' => 'MP-RENEW-1',
                'paid_at' => now()->toIso8601String(),
            ],
        ]),
    ]);

    $subscription->update(['current_period_ends_at' => now()->subDay()]);

    deliverEvent([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'MP-RENEW-1',
            'id' => 999,
            'subscription' => ['subscription_code' => 'SUB_live123'],
        ],
    ]);

    $renewed = $subscription->fresh();

    expect($renewed->status)->toBe(SubscriptionStatus::Active)
        ->and($renewed->current_period_ends_at->isFuture())->toBeTrue();

    $ends = $renewed->current_period_ends_at;

    // Same delivery replayed: the period must not extend again.
    deliverEvent([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'MP-RENEW-1',
            'id' => 1000,
            'subscription' => ['subscription_code' => 'SUB_live123'],
        ],
    ]);

    expect($subscription->fresh()->current_period_ends_at->equalTo($ends))->toBeTrue();
});

it('marks a non-NGN charge as failed instead of confirming it', function () {
    [$seller] = sellerWithPage();
    fakePaystack(['currency' => 'USD']);

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth']);

    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    deliverEvent([
        'event' => 'charge.success',
        'data' => ['reference' => $transaction->reference, 'id' => 12345],
    ]);

    expect($transaction->fresh()->status->value)->toBe('failed')
        ->and(Subscription::where('seller_id', $seller->id)->sole()->status)->toBe(SubscriptionStatus::Pending);
});

it('verifies Flutterwave verif-hash verbatim rather than as an HMAC', function () {
    $credentials = new GatewayCredentials(
        gateway: 'flutterwave',
        environment: 'test',
        publicKey: null,
        secretKey: 'sk_test_key',
        webhookSecret: 'my-static-verif-hash',
    );

    $gateway = new FlutterwaveGateway($credentials);

    expect($gateway->sign('{"any":"body"}'))->toBe('my-static-verif-hash');
});
