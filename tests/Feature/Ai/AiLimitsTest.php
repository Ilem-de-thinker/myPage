<?php

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('rate limits AI requests per business', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    config(['ai.limits.requests_per_minute' => 1]);
    RateLimiter::clear('ai:limits:'.$seller->id);

    actingAs($seller)->postJson('/ai/estimate', ['feature' => 'products', 'item_count' => 1])
        ->assertOk();

    actingAs($seller)->postJson('/ai/estimate', ['feature' => 'products', 'item_count' => 1])
        ->assertTooManyRequests();
});

it('rejects a batch above the configured item cap', function () {
    [$seller] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();
    config(['ai.limits.max_batch_items' => 2]);

    $items = collect(range(1, 3))->map(fn (int $number): array => ['text' => "Product {$number}"])->all();

    actingAs($seller)->postJson('/ai/batch', ['feature' => 'products', 'items' => $items])
        ->assertUnprocessable();
});

it('rejects new work when the active operation cap is reached', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();
    config(['ai.limits.max_active_operations' => 1]);

    AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'status' => 'queued',
        'input' => ['feature' => 'products'],
    ]);

    actingAs($seller)->postJson('/ai/batch', ['feature' => 'products', 'items' => [['text' => 'One']]])
        ->assertTooManyRequests();
});

it('rejects a photo batch above the configured photo cap', function () {
    [$seller] = sellerWithPage(['theme' => Theme::Store]);
    Storage::fake('public');
    Queue::fake();
    config(['ai.limits.max_photo_items' => 1]);

    actingAs($seller)->postJson(route('dashboard.ai.photo-listing'), [
        'feature' => 'products',
        'photos' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ],
    ])->assertUnprocessable();
});
