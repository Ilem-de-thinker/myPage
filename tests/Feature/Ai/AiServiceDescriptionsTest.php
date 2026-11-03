<?php

use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Booking\Models\Service;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

it('queues service description generation', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Service]);
    Queue::fake();
    $service = Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    actingAs($seller)->post(route('services.generate-descriptions'), [
        'ids' => [$service->id],
        'tone' => 'friendly',
        'length' => 'short',
        'language' => 'English',
        'overwrite' => true,
    ])->assertRedirect();

    $task = AiTask::query()->firstOrFail();
    $operation = AiOperation::query()->firstOrFail();

    expect($task->type)->toBe('service_descriptions')
        ->and($operation->input['_no_draft'])->toBeTrue()
        ->and($operation->input['service_id'])->toBe($service->id)
        ->and($operation->input['options']['tone'])->toBe('friendly');

    Queue::assertPushed(RunAiOperation::class);
});

it('applies and undoes generated service descriptions with SEO metadata', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Service]);
    $service = Service::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'description' => 'Old copy',
        'ai_metadata' => ['keep' => 'me'],
    ]);

    $task = AiTask::create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'type' => 'service_descriptions', 'status' => 'completed', 'total' => 1, 'completed' => 1]);
    AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'ai_task_id' => $task->id,
        'task' => 'description_generation',
        'status' => 'succeeded',
        'input' => ['service_id' => $service->id, 'options' => ['overwrite' => true]],
        'output' => [
            'description' => 'New service copy',
            'seo_title' => 'Braiding session',
            'seo_description' => 'Book a braiding session.',
            'insufficient_info' => null,
            'flags' => ['Check duration'],
        ],
    ]);

    actingAs($seller)->post(route('services.apply-descriptions', $task))->assertRedirect();

    $service->refresh();
    expect($service->description)->toBe('New service copy')
        ->and($service->ai_metadata['keep'])->toBe('me')
        ->and($service->ai_metadata['seo']['title'])->toBe('Braiding session')
        ->and($service->ai_metadata['ai_description']['flags'])->toBe(['Check duration']);

    actingAs($seller)->post(route('services.undo-descriptions', $task))->assertRedirect();

    $service->refresh();
    expect($service->description)->toBe('Old copy')
        ->and($service->ai_metadata)->toBe(['keep' => 'me']);
});

it('offers a description action on each service row', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Service]);
    Service::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'name' => 'Braiding session']);

    actingAs($seller)->get(route('services.index'))
        ->assertOk()
        ->assertSee('Braiding session')
        ->assertSee('Write description')
        ->assertSee(route('services.generate-descriptions'), false);
});
