<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Jobs\ReleaseStaleAiReservations;
use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiUsageReservations;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Models\Plan;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

/**
 * Start a real subscription so the credit gate sees the merchant's plan.
 */
function subscribeUsagePlan(Seller $seller, string $slug, int $credits): void
{
    Plan::query()->where('slug', $slug)->sole()->update(['ai_monthly_credits' => $credits]);
    $plan = Plan::query()->where('slug', $slug)->sole();

    $manager = app(SubscriptionManager::class);
    $manager->renew($manager->start($seller, $plan, 'manual'));
}

/**
 * @return array{0: Seller, 1: StorePage}
 */
function usageStore(array $attributes = []): array
{
    return sellerWithPage($attributes);
}

function queuedOperation(int $sellerId, int $pageId, array $attributes = []): AiOperation
{
    return AiOperation::create([
        'seller_id' => $sellerId,
        'store_page_id' => $pageId,
        'task' => 'listing_copy',
        'status' => 'queued',
        'input' => ['feature' => 'products'],
        ...$attributes,
    ]);
}

it('counts a pending reservation against the monthly allowance before the provider call', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 1);

    $operation = queuedOperation($seller->id, $page->id);

    app(AiUsageReservations::class)->reserve($operation);

    $resume = app(AiCreditService::class)->estimate($page, 1, 'listing_copy');

    expect(AiUsageLedger::query()->where('operation_id', $operation->id)->sole()->status)->toBe('pending')
        ->and($resume['remaining'])->toBe(0)
        ->and($resume['allowed'])->toBe(0);
});

it('blocks a second reservation when the plan limit is already pending', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 1);

    app(AiUsageReservations::class)->reserve(queuedOperation($seller->id, $page->id));

    $second = queuedOperation($seller->id, $page->id);

    try {
        app(AiUsageReservations::class)->reserve($second);
        $this->fail('Expected the reservation to be rejected.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(429);
    }

    expect(AiUsageLedger::query()->where('operation_id', $second->id)->count())->toBe(0);
});

it('converts a pending reservation to used instead of recording a second row', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 5);

    $operation = queuedOperation($seller->id, $page->id);
    $reservations = app(AiUsageReservations::class);

    $reservations->reserve($operation);
    $reservations->markUsed($operation->id, $page, 'listing_copy', 'groq', 'test-model', 120, 80);

    $ledger = AiUsageLedger::query();

    expect($ledger->count())->toBe(1)
        ->and($ledger->sole()->status)->toBe('used')
        ->and($ledger->sole()->tokens_in)->toBe(120)
        ->and($ledger->sole()->tokens_out)->toBe(80)
        ->and($ledger->sole()->operation_id)->toBe($operation->id);

    // The allowance reflects the single used row, not a double count.
    expect(app(AiCreditService::class)->estimate($page, 1)['remaining'])->toBe(4);
});

it('restores the allowance when a reservation is released', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 1);

    $operation = queuedOperation($seller->id, $page->id);

    app(AiUsageReservations::class)->reserve($operation);
    expect(app(AiCreditService::class)->estimate($page, 1)['allowed'])->toBe(0);

    app(AiUsageReservations::class)->release($operation);

    expect(AiUsageLedger::query()->count())->toBe(0)
        ->and(app(AiCreditService::class)->estimate($page, 1)['allowed'])->toBe(1);
});

it('releases the pending reservation when the operation fails permanently', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 1);
    Queue::fake();

    $operation = queuedOperation($seller->id, $page->id);
    app(AiUsageReservations::class)->reserve($operation);

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldReceive('run')->once()->andThrow(new RuntimeException('This item could not be generated.'));

    try {
        (new RunAiOperation($operation))->handle($ai);
    } catch (RuntimeException) {
        // The queue records the final failure and rethrows.
    }

    expect($operation->fresh()->status)->toBe('failed')
        ->and(AiUsageLedger::query()->count())->toBe(0);
});

it('releases queued reservations when the merchant cancels the task', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 2);

    $task = AiTask::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'type' => 'products',
        'status' => 'processing',
        'total' => 2,
    ]);
    $operations = collect(range(1, 2))->map(fn () => queuedOperation($seller->id, $page->id, ['ai_task_id' => $task->id]));
    foreach ($operations as $operation) {
        app(AiUsageReservations::class)->reserve($operation);
    }

    actingAs($seller)->postJson(route('dashboard.ai.tasks.cancel', $task))->assertOk();

    expect(AiUsageLedger::query()->count())->toBe(0)
        ->and(AiOperation::query()->where('ai_task_id', $task->id)->pluck('status')->all())->toBe(['cancelled', 'cancelled']);
});

it('sweeps stale pending reservations through the scheduled job', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 2);

    $operation = queuedOperation($seller->id, $page->id);
    app(AiUsageReservations::class)->reserve($operation);

    AiUsageLedger::query()->where('operation_id', $operation->id)->update(['reserved_at' => Carbon::now()->subHours(2)]);

    (new ReleaseStaleAiReservations)->handle(app(AiUsageReservations::class));

    expect(AiUsageLedger::query()->count())->toBe(0);
});

it('keeps fresh pending reservations when the sweep runs', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 2);

    $operation = queuedOperation($seller->id, $page->id);
    app(AiUsageReservations::class)->reserve($operation);

    (new ReleaseStaleAiReservations)->handle(app(AiUsageReservations::class));

    expect(AiUsageLedger::query()->count())->toBe(1);
});

it('reserves credits for each operation queued through the batch endpoint', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 250);
    Queue::fake();

    actingAs($seller)->postJson(route('dashboard.ai.batch'), [
        'feature' => 'products',
        'confirmation' => 'yes',
        'items' => [
            ['text' => 'Ankara tote bag'],
            ['text' => 'Beaded necklace'],
        ],
    ])->assertAccepted()->assertJsonCount(2, 'operations');

    expect(AiUsageLedger::query()->where('status', 'pending')->count())->toBe(2);
});

it('reserves and settles a credit for work that runs inside the request', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 2);

    Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'name' => 'Ankara tote bag',
            'description' => 'A structured ankara tote bag for everyday use.',
            'category' => 'Bags',
            'tags' => ['ankara', 'tote'],
            'price_hint' => '',
            'seo_title' => 'Ankara tote bag',
            'seo_description' => 'A structured ankara tote bag for everyday use.',
            'variants' => [],
        ])->withUsage(new Usage(11, 7)),
    ]);

    app(AiManager::class)->run('listing_copy', ['text' => 'Ankara tote bag'], $page);

    $ledger = AiUsageLedger::query()->sole();

    expect($ledger->status)->toBe('used')
        ->and($ledger->operation_id)->toBeNull()
        ->and($ledger->tokens_in)->toBe(11)
        ->and(app(AiCreditService::class)->estimate($page, 1)['remaining'])->toBe(1);
});

it('gives the credit back when synchronous work fails before the provider answers', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 1);

    Prism::fake([
        StructuredResponseFake::make()->withStructured(['theme_key' => 'catalog']),
        StructuredResponseFake::make()->withStructured(['theme_key' => 'catalog']),
    ]);

    try {
        app(AiManager::class)->run('theme_and_color_preset', ['description' => 'A shop.'], $page);
        $this->fail('Expected invalid structured output to be rejected.');
    } catch (ValidationException) {
        // The reservation must not survive an unusable provider response.
    }

    expect(AiUsageLedger::query()->count())->toBe(0)
        ->and(app(AiCreditService::class)->estimate($page, 1)['allowed'])->toBe(1);
});

it('does not charge a cancelled operation even when the provider already answered', function () {
    [$seller, $page] = usageStore();
    subscribeUsagePlan($seller, 'growth', 3);

    $operation = queuedOperation($seller->id, $page->id, ['status' => 'cancelled']);
    $reservations = app(AiUsageReservations::class);

    AiUsageLedger::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'operation_id' => $operation->id,
        'task' => 'listing_copy',
        'status' => 'pending',
        'provider' => 'pending',
        'model' => 'pending',
        'tokens_in' => 0,
        'tokens_out' => 0,
        'estimated_cost' => 0,
        'reserved_at' => now(),
    ]);

    $reservations->markUsed($operation->id, $page, 'listing_copy', 'groq', 'test-model', 50, 40);

    expect(AiUsageLedger::query()->count())->toBe(0)
        ->and(app(AiCreditService::class)->estimate($page, 1)['remaining'])->toBe(3);
});
