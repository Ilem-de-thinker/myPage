<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

function aiTaskFor(int $sellerId, int $pageId, array $attributes = []): AiTask
{
    return AiTask::create([
        'seller_id' => $sellerId,
        'store_page_id' => $pageId,
        'type' => 'products',
        'status' => 'processing',
        'total' => 2,
        ...$attributes,
    ]);
}

function aiOperationFor(AiTask $task, string $status): AiOperation
{
    return AiOperation::create([
        'seller_id' => $task->seller_id,
        'store_page_id' => $task->store_page_id,
        'ai_task_id' => $task->id,
        'task' => 'listing_from_photo',
        'status' => $status,
        'input' => ['feature' => 'products'],
    ]);
}

it('cancels queued and running items and keeps the task cancelled when polled', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($seller->id, $page->id);
    $queued = aiOperationFor($task, 'queued');
    $running = aiOperationFor($task, 'running');

    actingAs($seller)->postJson(route('dashboard.ai.tasks.cancel', $task))
        ->assertOk()
        ->assertJsonPath('task.status', 'cancelled');

    expect($queued->fresh()->status)->toBe('cancelled')
        ->and($running->fresh()->status)->toBe('cancelled');

    actingAs($seller)->getJson(route('dashboard.ai.tasks.status', $task))
        ->assertOk()
        ->assertJsonPath('task.status', 'cancelled');
});

it('requeues failed items and resumes a cancelled task', function () {
    Queue::fake();

    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($seller->id, $page->id, ['status' => 'cancelled', 'completed' => 0, 'failed' => 2]);
    $first = aiOperationFor($task, 'failed');
    $second = aiOperationFor($task, 'failed');

    actingAs($seller)->postJson(route('dashboard.ai.tasks.retry-failed', $task))
        ->assertOk()
        ->assertJsonPath('retried', 2)
        ->assertJsonPath('task.status', 'processing');

    expect($first->fresh()->status)->toBe('queued')
        ->and($second->fresh()->status)->toBe('queued')
        ->and($task->fresh()->cancelled_at)->toBeNull();

    Queue::assertPushed(RunAiOperation::class, 2);
});

it('retries only as many failed items as the active-slot cap allows', function () {
    Queue::fake();
    config()->set('ai.limits.max_active_operations', 2);

    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($seller->id, $page->id, ['status' => 'processing', 'failed' => 2]);

    // One slot is already taken, so only one of the two retries may queue.
    aiOperationFor($task, 'running');
    $first = aiOperationFor($task, 'failed');
    $second = aiOperationFor($task, 'failed');

    actingAs($seller)->postJson(route('dashboard.ai.tasks.retry-failed', $task))
        ->assertOk()
        ->assertJsonPath('retried', 1)
        ->assertJsonPath('limit_reached', true);

    expect($first->fresh()->status)->toBe('queued')
        ->and($second->fresh()->status)->toBe('failed');

    Queue::assertPushed(RunAiOperation::class, 1);
});

it('explains when there are no failed items to retry', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($seller->id, $page->id);
    aiOperationFor($task, 'succeeded');

    actingAs($seller)->postJson(route('dashboard.ai.tasks.retry-failed', $task))
        ->assertStatus(422)
        ->assertJsonPath('retried', 0);
});

it('does not let another seller cancel or retry a task', function () {
    [$owner, $ownerPage] = sellerWithPage(['theme' => Theme::Store]);
    [$other] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($owner->id, $ownerPage->id);
    aiOperationFor($task, 'failed');

    actingAs($other)->postJson(route('dashboard.ai.tasks.cancel', $task))->assertNotFound();
    actingAs($other)->postJson(route('dashboard.ai.tasks.retry-failed', $task))->assertNotFound();

    expect($task->fresh()->status)->toBe('processing');
});

it('does not run an item that was cancelled before the worker picked it up', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($seller->id, $page->id, ['status' => 'cancelled']);
    $operation = aiOperationFor($task, 'cancelled');

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldNotReceive('run');

    (new RunAiOperation($operation))->handle($ai);

    expect($operation->fresh()->status)->toBe('cancelled')
        ->and(AiDraft::count())->toBe(0);
});

it('renders the task results page with its cancel and retry controls', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $task = aiTaskFor($seller->id, $page->id);
    aiOperationFor($task, 'failed');

    actingAs($seller)->get(route('dashboard.ai.tasks.show', $task))
        ->assertOk()
        ->assertSee('Retry failed')
        ->assertSee('Cancel')
        ->assertSee('aiTaskShow', false);
});

it('serves a review page to browsers and JSON to the badge poll', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_from_photo',
        'version' => 1,
        'status' => 'pending',
        'payload' => [
            '_feature' => 'products',
            'name' => 'Handwoven basket',
            'description' => 'Woven in Ibadan.',
            'category' => 'Home',
        ],
    ]);

    actingAs($seller)->get(route('dashboard.ai.drafts'))
        ->assertOk()
        ->assertSee('Review Mercho drafts')
        ->assertSee('Handwoven basket')
        ->assertDontSee('"seller_id"', false);

    actingAs($seller)->getJson(route('dashboard.ai.drafts'))
        ->assertOk()
        ->assertJsonPath('drafts.0.payload.name', 'Handwoven basket');
});

it('shows appearance directions as cards on the drafts page instead of raw JSON', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'theme_and_color_preset',
        'version' => 1,
        'status' => 'pending',
        'payload' => ['presets' => [[
            'theme_key' => 'catalog',
            'brand_color' => '#112233',
            'accent_color' => '#445566',
            'reason' => 'A dense catalogue suits a product-led shop.',
        ]]],
    ]);

    actingAs($seller)->get(route('dashboard.ai.drafts'))
        ->assertOk()
        ->assertSee('Apply this direction')
        ->assertSee('A dense catalogue suits a product-led shop.')
        ->assertDontSee('"theme_key"', false);
});

it('shows a storefront direction draft with its copy on the drafts page', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'storefront_direction',
        'version' => 1,
        'status' => 'pending',
        'payload' => [
            'theme_key' => 'catalog',
            'brand_color' => '#112233',
            'accent_color' => '#445566',
            'surface' => 'light',
            'radius' => 'rounded',
            'hero_style' => 'immersive',
            'reason' => 'A dense catalogue suits a product-led shop.',
            'copy' => ['home' => ['hero' => ['headline' => 'Ankara, cut for you']]],
        ],
    ]);

    actingAs($seller)->get(route('dashboard.ai.drafts'))
        ->assertOk()
        ->assertSee('Ankara, cut for you')
        ->assertSee('Home copy')
        ->assertSee('A dense catalogue suits a product-led shop.')
        ->assertDontSee('"theme_key"', false);
});

it('paginates drafts newest first', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);

    $drafts = collect(range(1, 12))->map(function (int $number) use ($seller, $page): AiDraft {
        $draft = AiDraft::create([
            'seller_id' => $seller->id,
            'store_page_id' => $page->id,
            'task' => 'copy_rewrite',
            'version' => 1,
            'status' => 'pending',
            'payload' => ['text' => "Draft {$number}"],
        ]);
        $draft->forceFill(['created_at' => now()->subMinutes(12 - $number)])->save();

        return $draft;
    });

    $response = actingAs($seller)->get(route('dashboard.ai.drafts', ['status' => 'all']));

    $response->assertOk()
        ->assertSee('Newest first')
        ->assertSee('Showing');

    $paginator = $response->viewData('drafts');

    expect($paginator->total())->toBe(12)
        ->and($paginator->count())->toBe(10)
        ->and($paginator->first()->id)->toBe($drafts->last()->id);
});
