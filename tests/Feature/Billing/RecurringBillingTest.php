<?php

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Notifications\PlanGatewayMismatch;
use App\Domains\Billing\Notifications\SubscriptionCardExpiring;
use App\Domains\Payments\Services\Gateways\FlutterwaveGateway;
use App\Domains\Payments\Services\Gateways\PaystackGateway;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

/**
 * Recurring billing tests.
 *
 * These cover the part where the gateway, not this application, decides when
 * money moves: a plan checkout that becomes a subscription, the events that
 * follow each cycle, and the cancellation that has to reach the gateway before
 * it can be called done.
 */

/**
 * Deliver a signed Paystack subscription event to the webhook endpoint.
 *
 * @param  array<string, mixed>  $data
 */
function deliverSubscriptionEvent(string $event, array $data, int $eventId = 4242): mixed
{
    $payload = json_encode(['event' => $event, 'data' => ['id' => $eventId, ...$data]]);

    return test()->call(
        'POST',
        '/webhooks/paystack',
        [],
        [],
        [],
        ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, (string) config('services.paystack.secret'))],
        $payload,
    );
}

/**
 * A seller whose subscription the gateway knows about, as a real signup leaves it.
 *
 * @return array{0: Seller, 1: Subscription}
 */
function subscribedSeller(string $subscriptionCode = 'SUB_test123', ?Carbon $periodEndsAt = null): array
{
    [$seller] = sellerWithPage();

    // Reuses the plan beforeEach seeded rather than making a second one: slug is
    // unique, and the subscription only needs something to point at.
    $plan = Plan::query()->where('slug', 'growth')->sole();

    $subscription = Subscription::create([
        'seller_id' => $seller->getKey(),
        'plan_id' => $plan->getKey(),
        'status' => SubscriptionStatus::Active,
        'price_kobo' => 950000,
        'current_period_starts_at' => now()->subMonth(),
        'current_period_ends_at' => $periodEndsAt ?? now()->addDays(20),
        'payment_method' => 'gateway',
        'gateway_subscription_code' => $subscriptionCode,
    ]);

    return [$seller, $subscription];
}

beforeEach(function () {
    config([
        'services.paystack.secret' => 'sk_test_secret',
        'mypage.gateway' => 'paystack',
        'billing.grace_days' => 7,
    ]);

    $this->plan = Plan::factory()->create([
        'slug' => 'growth',
        'name' => 'Growth',
        'price_kobo' => 950000,
        'trial_days' => 14,
    ]);
});

it('turns a plan checkout into a subscription signup', function () {
    $this->plan->update(['gateway_plan_code' => 'PLN_growth999']);

    fakePaystack();

    [$seller] = sellerWithPage();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth'])
        ->assertRedirect('https://checkout.paystack.com/test-checkout');

    // The plan travels with the charge, which is what makes the gateway keep
    // billing the card instead of stopping after this one period.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'transaction/initialize') && ($request->data()['plan'] ?? null) === 'PLN_growth999');
});

it('sends no plan when the plan has none registered on the gateway', function () {
    fakePaystack();

    [$seller] = sellerWithPage();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth'])
        ->assertRedirect();

    // No plan code means the seller pays one period at a time, which still works
    // and is recoverable — as opposed to sending an empty plan to the gateway.
    Http::assertSent(fn ($request) => ! array_key_exists('plan', $request->data()));
});

it('records the subscription the gateway announces, and the date it will charge', function () {
    [, $subscription] = subscribedSeller();

    deliverSubscriptionEvent('subscription.create', [
        'subscription_code' => 'SUB_test123',
        'email_token' => 'tok_abc123',
        'status' => 'active',
        'next_payment_date' => '2026-11-22T00:00:00.000Z',
        'customer' => ['customer_code' => 'CUS_xyz789'],
    ])->assertOk();

    $subscription->refresh();

    expect($subscription->gateway_subscription_code)->toBe('SUB_test123')
        ->and($subscription->gateway_customer_code)->toBe('CUS_xyz789')
        ->and($subscription->email_token)->toBe('tok_abc123')
        ->and($subscription->next_payment_date?->toDateString())->toBe('2026-11-22');
});

it('is not rolled backwards by a replayed subscription event', function () {
    [, $subscription] = subscribedSeller();

    $data = [
        'subscription_code' => 'SUB_test123',
        'status' => 'active',
        'next_payment_date' => '2026-12-22T00:00:00.000Z',
    ];

    deliverSubscriptionEvent('subscription.create', $data, 1);
    deliverSubscriptionEvent('subscription.create', $data, 2);

    expect($subscription->fresh()->next_payment_date?->toDateString())->toBe('2026-12-22');
});

it('gives a declined recurring charge a grace period instead of expiring on the spot', function () {
    [, $subscription] = subscribedSeller();

    // A subscription that has stopped working is 'attention' at Paystack: it is
    // still alive and will be retried, so it must not be recorded as dead.
    deliverSubscriptionEvent('invoice.payment_failed', [
        'subscription' => ['subscription_code' => 'SUB_test123'],
        'most_recent_invoice' => ['description' => 'Insufficient Funds'],
    ])->assertOk();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->grace_ends_at)->not->toBeNull();
});

it('keeps a non-renewing subscription alive until the period it paid for ends', function () {
    [, $subscription] = subscribedSeller();

    deliverSubscriptionEvent('subscription.not_renew', ['subscription_code' => 'SUB_test123'])->assertOk();

    $subscription->refresh();

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->cancelled_at)->not->toBeNull();
});

it('closes a subscription the gateway has stopped once the paid period is over', function () {
    // Paystack disables on the next payment date, which is also the day the
    // period they paid for ends.
    [, $subscription] = subscribedSeller('SUB_test123', now()->subDay());

    deliverSubscriptionEvent('subscription.disable', [
        'subscription_code' => 'SUB_test123',
        'status' => 'cancelled',
    ])->assertOk();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

it('closes a subscription that simply ran out of cycles', function () {
    [, $subscription] = subscribedSeller('SUB_test123', now()->subDay());

    deliverSubscriptionEvent('subscription.disable', [
        'subscription_code' => 'SUB_test123',
        'status' => 'complete',
    ])->assertOk();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled);
});

it('ignores a subscription event for a code it never issued', function () {
    [, $subscription] = subscribedSeller();

    deliverSubscriptionEvent('subscription.disable', ['subscription_code' => 'SUB_someoneelse'])
        ->assertOk();

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('tells the gateway to stop charging before recording a cancellation', function () {
    [, $subscription] = subscribedSeller();

    Http::fake(['api.paystack.co/*' => Http::response(['status' => true, 'data' => []])]);

    actingAs($subscription->seller)->post(route('billing.cancel'))->assertRedirect(route('billing.index'));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/subscription/SUB_test123/disable'));

    // Still Active, because the period is paid for and a cancellation never
    // rewrites history — but flagged, so nothing renews it again.
    expect($subscription->fresh()->cancelled_at)->not->toBeNull()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('will not record a cancellation it could not give to the gateway', function () {
    [, $subscription] = subscribedSeller();

    Http::fake(['api.paystack.co/*' => Http::failedConnection()]);

    actingAs($subscription->seller)->post(route('billing.cancel'))->assertSessionHasErrors();

    // The local record saying "cancelled" while the gateway keeps debiting is
    // the one outcome worse than an error message.
    expect($subscription->fresh()->cancelled_at)->toBeNull()
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
});

it('sends a seller with a failing card to the gateway to replace it', function () {
    [, $subscription] = subscribedSeller();

    Http::fake(['api.paystack.co/subscription/*/manage/link' => Http::response([
        'status' => true,
        'data' => ['link' => 'https://paystack.com/manage/subscriptions/abc'],
    ])]);

    actingAs($subscription->seller)->get(route('billing.manage-card'))
        ->assertRedirect('https://paystack.com/manage/subscriptions/abc');
});

it('has no card page for a seller the gateway does not bill', function () {
    [, $subscription] = subscribedSeller('SUB_test123');

    $subscription->update(['gateway_subscription_code' => null]);

    actingAs($subscription->seller)->get(route('billing.manage-card'))->assertNotFound();
});

it('speaks Paystack intervals rather than its own', function () {
    $gateway = app(PaystackGateway::class);

    Http::fake(['api.paystack.co/plan' => Http::response([
        'status' => true,
        'data' => ['plan_code' => 'PLN_abc', 'name' => 'Pro', 'interval' => 'annually', 'amount' => 2500000],
    ])]);

    $gateway->createPlan('Pro', 'yearly', 2500000);

    // This application says yearly; Paystack only understands annually, and an
    // unknown interval is rejected outright.
    Http::assertSent(fn ($request) => $request->data()['interval'] === 'annually');
});

it('refuses recurring billing on a gateway that has none, instead of pretending', function () {
    $gateway = app(FlutterwaveGateway::class);

    expect(fn () => $gateway->manageLink('SUB_abc'))
        ->toThrow(RuntimeException::class, 'not implemented for the Flutterwave gateway');
});

it('never touches the payment gateway while cancelling a manual transfer', function () {
    [$seller, $page] = sellerWithPage();

    $subscription = Subscription::create([
        'seller_id' => $seller->getKey(),
        'plan_id' => $this->plan->getKey(),
        'status' => SubscriptionStatus::Active,
        'price_kobo' => 950000,
        'current_period_starts_at' => now(),
        'current_period_ends_at' => now()->addMonth(),
        'payment_method' => 'manual',
    ]);

    Http::fake();

    actingAs($seller)->post(route('billing.cancel'))->assertRedirect(route('billing.index'));

    // A manual transfer was never a subscription at the gateway, so there is
    // nothing there to switch off.
    Http::assertNothingSent();
    expect($subscription->fresh()->cancelled_at)->not->toBeNull();
});

it('notifies the seller in-app and by email when the card is expiring', function () {
    Notification::fake();
    [$seller] = subscribedSeller();

    deliverSubscriptionEvent('subscription.expiring_cards', [
        'subscription_code' => 'SUB_test123',
        'next_payment_date' => '2026-11-01T00:00:00.000Z',
    ])->assertOk();

    Notification::assertSentTo(
        $seller,
        SubscriptionCardExpiring::class,
        fn (SubscriptionCardExpiring $notification, array $channels): bool => in_array('mail', $channels, true)
            && in_array('database', $channels, true),
    );

    deliverSubscriptionEvent('subscription.expiring_cards', [
        'subscription_code' => 'SUB_test123',
        'next_payment_date' => '2026-11-01T00:00:00.000Z',
    ], 4243)->assertOk();

    Notification::assertSentToTimes($seller, SubscriptionCardExpiring::class, 1);
});

it('refuses checkout when the gateway plan charges a different amount than we quote', function () {
    $this->plan->update(['gateway_plan_code' => 'PLN_growth999']);

    $admin = Seller::factory()->admin()->create(['email_verified_at' => now()]);

    Http::fake([
        'api.paystack.co/plan/PLN_growth999' => Http::response(['data' => ['name' => 'Growth', 'amount' => 100, 'interval' => 'monthly']]),
    ]);

    Notification::fake();

    [$seller] = sellerWithPage();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth'])
        ->assertSessionHasErrors('plan');

    expect(Subscription::where('seller_id', $seller->id)->exists())->toBeFalse();

    Notification::assertSentTo($admin, PlanGatewayMismatch::class);
});
