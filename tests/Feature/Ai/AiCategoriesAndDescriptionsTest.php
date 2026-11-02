<?php

use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

it('queues a category proposal without creating a raw draft', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();
    Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'name' => 'Ankara bag']);

    actingAs($seller)->post(route('categories.generate-ai'))
        ->assertRedirect(route('categories.index', ['task' => AiTask::query()->first()->id]));

    $task = AiTask::query()->firstOrFail();
    $operation = AiOperation::query()->firstOrFail();

    expect($task->type)->toBe('categories')
        ->and($operation->input['_no_draft'])->toBeTrue()
        ->and($operation->input['item_ids'])->toHaveCount(1);

    Queue::assertPushed(RunAiOperation::class);
});

it('applies and undoes an AI category proposal', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $bags = Category::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'name' => 'Bags']);
    $first = Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'name' => 'Ankara bag']);
    $second = Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'name' => 'Leather shoe']);

    $task = AiTask::create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'type' => 'categories', 'status' => 'completed', 'total' => 1, 'completed' => 1]);
    AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'ai_task_id' => $task->id,
        'task' => 'category_structure',
        'status' => 'succeeded',
        'input' => ['item_ids' => [$first->id, $second->id]],
        'output' => [
            'summary' => 'Two groups',
            'categories' => [['name' => 'Bags'], ['name' => 'Shoes']],
            'assignments' => [
                ['item_index' => '0', 'category_name' => 'Bags'],
                ['item_index' => '1', 'category_name' => 'Shoes'],
            ],
        ],
    ]);

    actingAs($seller)->post(route('categories.apply-ai', $task))->assertRedirect();

    expect($first->fresh()->category_id)->toBe($bags->id)
        ->and($second->fresh()->category->name)->toBe('Shoes');

    $shoes = Category::query()->where('store_page_id', $page->id)->where('name', 'Shoes')->firstOrFail();
    expect($task->fresh()->summary['previous_assignments'])->toHaveKey($first->id);

    actingAs($seller)->post(route('categories.undo-ai', $task))->assertRedirect();

    expect($first->fresh()->category_id)->toBeNull()
        ->and($second->fresh()->category_id)->toBeNull()
        ->and(Category::query()->whereKey($shoes->id)->exists())->toBeFalse();
});

it('assigns a category to an approved AI product draft', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'version' => 1,
        'status' => 'pending',
        'payload' => [
            '_feature' => 'products',
            'name' => 'Ankara tote',
            'description' => 'Handmade bag',
            'category' => 'Ankara bags',
            'price_hint' => '15000',
        ],
    ]);

    actingAs($seller)->postJson(route('dashboard.ai.drafts.approve', $draft))->assertOk();

    $product = Product::query()->where('store_page_id', $page->id)->firstOrFail();

    expect($product->category?->name)->toBe('Ankara bags');
});

it('queues bulk description generation', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();
    $product = Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);

    actingAs($seller)->post(route('products.generate-descriptions'), [
        'ids' => [$product->id],
        'tone' => 'friendly',
        'length' => 'short',
        'language' => 'English',
        'overwrite' => true,
    ])->assertRedirect();

    $task = AiTask::query()->firstOrFail();
    $operation = AiOperation::query()->firstOrFail();

    expect($task->type)->toBe('descriptions')
        ->and($operation->input['_no_draft'])->toBeTrue()
        ->and($operation->input['product_id'])->toBe($product->id)
        ->and($operation->input['options']['tone'])->toBe('friendly');

    Queue::assertPushed(RunAiOperation::class);
});

it('applies and undoes generated descriptions with SEO metadata', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $product = Product::factory()->create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'description' => 'Old copy',
        'ai_metadata' => ['keep' => 'me'],
    ]);

    $task = AiTask::create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'type' => 'descriptions', 'status' => 'completed', 'total' => 1, 'completed' => 1]);
    AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'ai_task_id' => $task->id,
        'task' => 'description_generation',
        'status' => 'succeeded',
        'input' => ['product_id' => $product->id, 'options' => ['overwrite' => true]],
        'output' => [
            'description' => 'New copy',
            'seo_title' => 'Ankara bag',
            'seo_description' => 'Buy the Ankara bag.',
            'insufficient_info' => 'Material is missing.',
            'flags' => ['Check material'],
        ],
    ]);

    actingAs($seller)->post(route('products.apply-descriptions', $task))->assertRedirect();

    $product->refresh();
    expect($product->description)->toBe('New copy')
        ->and($product->ai_metadata['keep'])->toBe('me')
        ->and($product->ai_metadata['seo']['title'])->toBe('Ankara bag')
        ->and($product->ai_metadata['ai_description']['flags'])->toBe(['Check material']);

    actingAs($seller)->post(route('products.undo-descriptions', $task))->assertRedirect();

    $product->refresh();
    expect($product->description)->toBe('Old copy')
        ->and($product->ai_metadata)->toBe(['keep' => 'me']);
});

it('renders the category preview and bulk description controls', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Product::factory()->create(['seller_id' => $seller->id, 'store_page_id' => $page->id]);
    $task = AiTask::create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'type' => 'categories', 'status' => 'processing', 'total' => 1]);
    AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'ai_task_id' => $task->id,
        'task' => 'category_structure',
        'status' => 'running',
        'input' => ['item_ids' => []],
    ]);

    actingAs($seller)->get(route('categories.index', ['task' => $task->id]))
        ->assertOk()
        ->assertSee('Proposed categories')
        ->assertSee('categoryAiPreview');

    actingAs($seller)->get(route('products.index'))
        ->assertOk()
        ->assertSee('Generate descriptions');
});
