<?php

use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Billing\Actions\ConfirmSubscriptionPayment;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Enums\MerchantStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\Feature;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Catalog\Models\Product;
use App\Domains\Directory\Services\DirectoryPlacement;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Models\Seller;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Start the trial plan for a seller, exactly as registration does.
 */
function beginTrialFor(Seller $seller): Subscription
{
    $plan = Plan::query()->where('slug', config('billing.trial_plan'))->sole();

    return app(SubscriptionManager::class)->start($seller, $plan, 'trial');
}

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

it('starts a 14-day trial on the trial plan at registration', function () {
    $this->freezeTime();
    Notification::fake();

    post('/register', [
        'name' => 'Ada Obi',
        'email' => 'ada@example.com',
        'phone' => '0803 123 4567',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'terms_accepted' => true,
        'privacy_accepted' => true,
    ])->assertRedirect(route('verification.notice'));

    $seller = Seller::where('email', 'ada@example.com')->sole();
    $subscription = $seller->subscriptions()->sole();

    expect($subscription->plan?->slug)->toBe('trial')
        ->and($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($subscription->canWrite())->toBeTrue();
});

it('caps trial listings at ten', function () {
    [$seller, $page] = sellerWithPage();
    beginTrialFor($seller);

    Product::factory()->count(10)->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)
        ->post(route('products.store'), ['name' => 'Eleventh', 'price' => '1000'])
        ->assertForbidden();
});

it('caps trial staff at one', function () {
    [$seller, $page] = bookableServicePage();
    beginTrialFor($seller);

    StaffMember::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
    ]);

    actingAs($seller)
        ->post(route('staff.store'), ['name' => 'Second Person'])
        ->assertForbidden();
});

it('gives the trial fifty AI credits', function () {
    [$seller, $page] = sellerWithPage();
    beginTrialFor($seller);

    $estimate = app(AiCreditService::class)->estimate($page, 1);

    expect($estimate['plan'])->toBe('trial')
        ->and($estimate['limit'])->toBe(50);
});

it('does not allow featured directory placement on trial', function () {
    [$seller, $page] = sellerWithPage();
    beginTrialFor($seller);

    expect(app(DirectoryPlacement::class)->canFeature($page->fresh()))->toBeFalse();
});

it('does not allow a custom domain on trial', function () {
    config(['mypage.features.custom_domains' => true]);
    Feature::flush();

    [$seller] = sellerWithPage();
    beginTrialFor($seller);

    actingAs($seller)
        ->post(route('domains.store'), ['domain' => 'shop.example.com'])
        ->assertSessionHas('error');

    expect(DomainBinding::withoutGlobalScopes()->where('seller_id', $seller->id)->count())->toBe(0);
});

it('stops the trial dead at the end of day fourteen', function () {
    [$seller] = sellerWithPage();
    $subscription = beginTrialFor($seller);

    $this->travel(15)->days();

    expect($subscription->fresh()->merchantStatus())->toBe(MerchantStatus::Expired)
        ->and($subscription->fresh()->canWrite())->toBeFalse();

    actingAs($seller)
        ->post(route('products.store'), ['name' => 'After trial'])
        ->assertRedirect(route('billing.index'));
});

it('keeps a lapsed trial read-only until a payment is confirmed', function () {
    [$seller] = sellerWithPage();
    $subscription = beginTrialFor($seller);
    fakePaystack();

    $this->travel(15)->days();

    actingAs($seller)->post(route('billing.subscribe'), ['plan' => 'growth'])->assertRedirect();

    // Checkout alone changes nothing: the trial is still over and the shop is
    // still read-only until the money lands.
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->fresh()->canWrite())->toBeFalse();

    $transaction = Transaction::where('payable_type', 'subscription')->sole();

    app(ConfirmSubscriptionPayment::class)->handle($transaction->reference);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->trial_ends_at)->toBeNull()
        ->and($subscription->fresh()->canWrite())->toBeTrue();
});

it('does not offer the trial plan as something to buy', function () {
    [$seller] = sellerWithPage();
    beginTrialFor($seller);

    actingAs($seller)->get(route('billing.index'))
        ->assertOk()
        ->assertDontSee('Choose Trial');

    actingAs($seller)
        ->post(route('billing.subscribe'), ['plan' => 'trial'])
        ->assertStatus(422);
});
