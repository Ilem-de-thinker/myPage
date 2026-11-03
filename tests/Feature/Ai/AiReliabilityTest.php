<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use App\Shared\Support\Money;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Exceptions\PrismRateLimitedException;

use function Pest\Laravel\actingAs;

it('parses human naira amounts into kobo', function () {
    expect(Money::toKobo('15,000 - 20,000'))->toBe(1_500_000)
        ->and(Money::toKobo('15k'))->toBe(1_500_000)
        ->and(Money::toKobo('₦1.5m'))->toBe(150_000_000)
        ->and(Money::toKobo(2500))->toBe(250_000)
        ->and(Money::toKobo(null))->toBe(0);
});

it('uses the first amount from a price range when approving a draft', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();

    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_from_photo',
        'version' => 1,
        'status' => 'pending',
        'payload' => [
            '_feature' => 'products',
            'name' => 'Ankara tote',
            'description' => 'Handmade bag',
            'price_hint' => '15,000 - 20,000',
        ],
    ]);

    actingAs($seller)->postJson(route('dashboard.ai.drafts.approve', $draft))->assertOk();

    expect(Product::query()->where('store_page_id', $page->id)->firstOrFail()->price_kobo)->toBe(1_500_000);
});

it('releases a rate limited item instead of failing it', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);

    $operation = AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'status' => 'queued',
        'input' => ['feature' => 'products'],
    ]);

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldReceive('run')->once()->andThrow(PrismRateLimitedException::make([], 20));

    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('attempts')->andReturn(1);
    $queueJob->shouldReceive('release')->once()->with(22);

    $job = new RunAiOperation($operation);
    $job->job = $queueJob;
    $job->handle($ai);

    expect($operation->fresh()->status)->toBe('queued')
        ->and($operation->fresh()->error)->toContain('rate limit');
});

it('does not create a second draft when the same job is delivered twice', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);

    $operation = AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_from_photo',
        'status' => 'queued',
        'input' => ['feature' => 'products'],
    ]);

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldReceive('run')->twice()->andReturn([
        'name' => 'Ankara tote',
        'description' => 'Handmade bag',
        'price_hint' => '15000',
    ]);

    (new RunAiOperation($operation))->handle($ai);
    $draftId = $operation->fresh()->output['draft_id'];

    $operation->update(['status' => 'queued']);
    (new RunAiOperation($operation->fresh()))->handle($ai);

    expect(AiDraft::count())->toBe(1)
        ->and($operation->fresh()->output['draft_id'])->toBe($draftId);
});

it('creates one tracking task for a bulk copy batch', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();

    actingAs($seller)->postJson(route('dashboard.ai.batch'), [
        'feature' => 'products',
        'confirmation' => 'yes',
        'items' => [
            ['text' => 'Ankara tote bag'],
            ['text' => 'Beaded necklace'],
        ],
    ])->assertAccepted()->assertJsonCount(2, 'operations')->assertJsonStructure(['task_id']);

    $task = AiTask::query()->firstOrFail();

    expect($task->type)->toBe('products')
        ->and($task->total)->toBe(2)
        ->and(AiOperation::query()->where('ai_task_id', $task->id)->count())->toBe(2);
});

it('keeps draft media when regenerating a listing', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);

    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_from_photo',
        'version' => 1,
        'status' => 'pending',
        'payload' => [
            '_feature' => 'products',
            'name' => 'Ankara tote',
            'description' => 'Handmade bag',
            '_media_relative_paths' => ['ai/1/staging/tote.jpg'],
        ],
    ]);

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldReceive('run')->once()->withArgs(function (string $task, array $input, $page): bool {
        return $task === 'listing_copy'
            && $input['media_relative_paths'] === ['ai/1/staging/tote.jpg'];
    })->andReturn(['name' => 'Ankara tote v2', 'description' => 'Second pass', 'price_hint' => '17000']);

    $this->instance(AiManager::class, $ai);

    actingAs($seller)->postJson(route('dashboard.ai.drafts.regenerate', $draft))->assertOk();

    $newDraft = AiDraft::query()->where('id', '!=', $draft->id)->firstOrFail();

    expect($newDraft->payload['_media_relative_paths'])->toBe(['ai/1/staging/tote.jpg'])
        ->and($newDraft->payload['_feature'])->toBe('products');
});
