<?php

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiUsageSummary;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

/**
 * Start a real subscription so the resolver sees the same plan a merchant sees.
 */
function subscribeAiPlan(Seller $seller, string $slug, string $method = 'manual'): void
{
    $plan = Plan::query()->where('slug', $slug)->sole();

    $manager = app(SubscriptionManager::class);
    $manager->renew($manager->start($seller, $plan, $method));
}

/**
 * Write a ledger row in the current month. This mirrors the shape PrismAiManager
 * records after a successful provider call.
 */
function recordAiUsage(Seller $seller, StorePage $page, string $task, int $count = 1): void
{
    foreach (range(1, $count) as $ignored) {
        AiUsageLedger::create([
            'seller_id' => $seller->id,
            'store_page_id' => $page->id,
            'task' => $task,
            'provider' => 'groq',
            'model' => 'test',
            'tokens_in' => 1,
            'tokens_out' => 1,
        ]);
    }
}

/**
 * A summary shape for rendering the meter without setting up every fixture.
 *
 * @return array<string, mixed>
 */
function usageMeterSummary(array $overrides = []): array
{
    return array_merge([
        'plan' => 'growth',
        'billing_enabled' => true,
        'period_start' => Carbon::now()->startOfMonth()->toIso8601String(),
        'period_end' => Carbon::now()->startOfMonth()->addMonth()->toIso8601String(),
        'limit' => 10,
        'used' => 5,
        'remaining' => 5,
        'percent' => 50,
        'unlimited' => false,
        'image_limit' => 12,
        'state' => 'normal',
        'tasks' => [],
    ], $overrides);
}

it('rejects a trial upload above four photos without writing files', function () {
    [$seller, $page] = sellerWithPage();
    subscribeAiPlan($seller, 'trial', 'trial');
    Storage::fake('public');
    Queue::fake();

    actingAs($seller)->postJson(route('dashboard.ai.photo-listing'), [
        'feature' => 'products',
        'photos' => collect(range(1, 5))->map(fn (int $number) => UploadedFile::fake()->image("photo-{$number}.jpg"))->all(),
    ])->assertUnprocessable()->assertJsonValidationErrors('photos');

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('accepts the growth plan image allowance', function () {
    [$seller, $page] = sellerWithPage();
    subscribeAiPlan($seller, 'growth');
    Storage::fake('public');
    Queue::fake();

    actingAs($seller)->postJson(route('dashboard.ai.photo-listing'), [
        'feature' => 'products',
        'photos' => collect(range(1, 12))->map(fn (int $number) => UploadedFile::fake()->image("photo-{$number}.jpg"))->all(),
    ])->assertAccepted()
        ->assertJsonPath('estimate.limit', 1000)
        ->assertJsonPath('estimate.plan', 'growth');
});

it('applies the absolute image ceiling even when subscriptions are disabled', function () {
    [$seller] = sellerWithPage();
    subscribeAiPlan($seller, 'growth');
    app(SubscriptionAvailability::class)->disable();
    config(['ai.limits.absolute_max_photo_items' => 2]);
    Storage::fake('public');
    Queue::fake();

    actingAs($seller)->postJson(route('dashboard.ai.photo-listing'), [
        'feature' => 'products',
        'photos' => collect(range(1, 3))->map(fn (int $number) => UploadedFile::fake()->image("photo-{$number}.jpg"))->all(),
    ])->assertUnprocessable()->assertJsonValidationErrors('photos');

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

it('treats a null monthly credit limit as unlimited', function () {
    [$seller, $page] = sellerWithPage();
    Plan::query()->where('slug', 'growth')->sole()->update(['ai_monthly_credits' => null]);
    subscribeAiPlan($seller, 'growth');

    $estimate = app(AiCreditService::class)->estimate($page, 25);

    expect($estimate['plan'])->toBe('growth')
        ->and($estimate['limit'])->toBeNull()
        ->and($estimate['remaining'])->toBeNull()
        ->and($estimate['allowed'])->toBe(25);
});

it('resolves starter, growth, and pro from their own plan rows', function () {
    foreach (['starter' => 250, 'growth' => 1000, 'pro' => 5000] as $slug => $credits) {
        [$seller, $page] = sellerWithPage();
        subscribeAiPlan($seller, $slug);

        $estimate = app(AiCreditService::class)->estimate($page, 1);

        expect($estimate['plan'])->toBe($slug)
            ->and($estimate['limit'])->toBe($credits);
    }
});

it('does not turn an unconfigured free plan into unlimited', function () {
    [$seller, $page] = sellerWithPage();
    config([
        'ai.plans.free.monthly_credits' => null,
        'ai.limits.fallback_monthly_credits' => 7,
    ]);

    $estimate = app(AiCreditService::class)->estimate($page, 1);

    expect($estimate['plan'])->toBe('free')
        ->and($estimate['limit'])->toBe(7)
        ->and($estimate['remaining'])->toBe(7);
});

it('applies the smaller of plan credits and feature cap', function () {
    [$seller, $page] = sellerWithPage();
    Plan::query()->where('slug', 'growth')->sole()->update(['ai_monthly_credits' => 10]);
    subscribeAiPlan($seller, 'growth');
    recordAiUsage($seller, $page, 'listing_copy', 2);

    config(['ai.feature_limits' => ['listing_copy' => 4]]);

    $credits = app(AiCreditService::class);

    expect($credits->estimate($page, 10, 'listing_copy')['allowed'])->toBe(2)
        ->and($credits->estimate($page, 10, 'copy_rewrite')['allowed'])->toBe(8);
});

it('returns a complete usage summary for the current month', function () {
    [$seller, $page] = sellerWithPage();
    Plan::query()->where('slug', 'growth')->sole()->update(['ai_monthly_credits' => 10]);
    subscribeAiPlan($seller, 'growth');
    recordAiUsage($seller, $page, 'listing_copy', 3);
    recordAiUsage($seller, $page, 'translate', 2);

    $summary = app(AiUsageSummary::class)->forPage($page);

    expect($summary)->not->toBeNull()
        ->and($summary['plan'])->toBe('growth')
        ->and($summary['used'])->toBe(5)
        ->and($summary['limit'])->toBe(10)
        ->and($summary['remaining'])->toBe(5)
        ->and($summary['percent'])->toBe(50)
        ->and($summary['state'])->toBe('normal')
        ->and($summary['image_limit'])->toBe(12)
        ->and($summary['period_end'])->toBe(Carbon::now()->startOfMonth()->addMonth()->toIso8601String())
        ->and($summary['tasks'])->toHaveCount(2)
        ->and($summary['tasks'][0]['label'])->toBe('Product copy')
        ->and($summary['tasks'][0]['used'])->toBe(3)
        ->and($summary['tasks'][1]['label'])->toBe('Translation')
        ->and($summary['tasks'][1]['used'])->toBe(2);
});

it('reports warning and exhausted states', function () {
    [$seller, $page] = sellerWithPage();
    Plan::query()->where('slug', 'growth')->sole()->update(['ai_monthly_credits' => 10]);
    subscribeAiPlan($seller, 'growth');
    recordAiUsage($seller, $page, 'listing_copy', 8);

    $summary = app(AiUsageSummary::class);

    expect($summary->forPage($page)['state'])->toBe('warning')
        ->and($summary->forPage($page)['percent'])->toBe(80);

    recordAiUsage($seller, $page, 'listing_copy', 2);

    expect($summary->forPage($page)['state'])->toBe('exhausted')
        ->and($summary->forPage($page)['remaining'])->toBe(0);
});

it('renders the usage meter states', function (array $summary, array $expected) {
    $html = view('components.ai.usage-meter', ['summary' => $summary])->render();

    foreach ($expected['see'] as $text) {
        expect($html)->toContain($text);
    }

    if ($expected['progress']) {
        expect($html)->toContain('role="progressbar"');
    } else {
        expect($html)->not->toContain('role="progressbar"');
    }
})->with([
    'normal' => [
        usageMeterSummary(),
        ['see' => ['Mercho usage', '5 of 10 used', 'Resets'], 'progress' => true],
    ],
    'warning' => [
        usageMeterSummary(['used' => 8, 'remaining' => 2, 'percent' => 80, 'state' => 'warning']),
        ['see' => ["close to this month's Mercho limit"], 'progress' => true],
    ],
    'exhausted' => [
        usageMeterSummary(['used' => 10, 'remaining' => 0, 'percent' => 100, 'state' => 'exhausted']),
        ['see' => ["This month's Mercho limit is used up", 'Upgrade your plan'], 'progress' => true],
    ],
    'unlimited' => [
        usageMeterSummary(['limit' => null, 'remaining' => null, 'percent' => null, 'unlimited' => true, 'state' => 'unlimited', 'used' => 4]),
        ['see' => ['No monthly cap', '4 credits used this month'], 'progress' => false],
    ],
]);

it('rejects queued AI work when the monthly limit is exhausted', function () {
    [$seller, $page] = sellerWithPage();
    Plan::query()->where('slug', 'growth')->sole()->update(['ai_monthly_credits' => 1]);
    subscribeAiPlan($seller, 'growth');
    recordAiUsage($seller, $page, 'listing_copy');
    Queue::fake();

    actingAs($seller)->postJson(route('dashboard.ai.queue'), [
        'task' => 'listing_from_photo',
        'feature' => 'products',
        'input' => ['hint' => 'test'],
    ])->assertTooManyRequests();

    expect(AiOperation::query()->count())->toBe(0);
});
