<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Jobs\ProcessProductImage;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('stores uploaded listing photos and records their relative paths on the operation', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Storage::fake('public');
    Queue::fake();

    $response = actingAs($seller)->postJson(route('dashboard.ai.photo-listing'), [
        'feature' => 'products',
        'photos' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
        ],
    ]);

    $response->assertAccepted()->assertJsonCount(2, 'operations');

    $paths = AiOperation::query()->orderBy('id')->get()
        ->flatMap(fn (AiOperation $operation): array => $operation->input['media_relative_paths']);

    expect($paths)->toHaveCount(2);

    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
    }

    expect(AiTask::query()->first()->total)->toBe(2);
});

it('carries uploaded photo paths into the generated draft', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Storage::fake('public');
    Storage::disk('public')->put('ai/listing-inputs/product.jpg', 'image-bytes');

    $operation = AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_from_photo',
        'status' => 'queued',
        'input' => [
            'feature' => 'products',
            'media_relative_paths' => ['ai/listing-inputs/product.jpg'],
        ],
    ]);

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldReceive('run')->once()->andReturn([
        'name' => 'Ankara tote',
        'description' => 'Handmade bag',
        'price_hint' => '15000',
    ]);

    (new RunAiOperation($operation))->handle($ai);

    $draft = AiDraft::query()->firstOrFail();

    expect($draft->payload['_media_relative_paths'])->toBe(['ai/listing-inputs/product.jpg'])
        ->and($draft->payload['_feature'])->toBe('products')
        ->and($operation->fresh()->status)->toBe('succeeded');
});

it('attaches draft photos to an approved product and keeps the files', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Storage::fake('public');
    Queue::fake();
    Storage::disk('public')->put('ai/listing-inputs/first.jpg', 'first');
    Storage::disk('public')->put('ai/listing-inputs/second.jpg', 'second');

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
            'price_hint' => '15000',
            '_media_relative_paths' => [
                'ai/listing-inputs/first.jpg',
                'ai/listing-inputs/second.jpg',
                'ai/listing-inputs/missing.jpg',
            ],
        ],
    ]);

    actingAs($seller)->postJson(route('dashboard.ai.drafts.approve', $draft))->assertOk();

    $product = Product::query()->where('store_page_id', $page->id)->firstOrFail();
    $images = $product->images()->orderBy('position')->get();

    $paths = $images->pluck('path')->all();

    expect($images)->toHaveCount(2)
        ->and($paths[0])->toStartWith("products/{$product->id}/first-")
        ->and($paths[1])->toStartWith("products/{$product->id}/second-")
        ->and($images->pluck('position')->all())->toBe([0, 1]);

    Queue::assertPushed(ProcessProductImage::class, 2);
    // Staging is kept so the draft preview still has a file after the product
    // copy is converted to WebP by ProcessProductImage.
    Storage::disk('public')->assertExists('ai/listing-inputs/first.jpg');
    Storage::disk('public')->assertExists('ai/listing-inputs/second.jpg');
    Storage::disk('public')->assertMissing('ai/listing-inputs/missing.jpg');
});

it('uses the first draft photo as an approved service image', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Service]);
    Storage::fake('public');
    Storage::disk('public')->put('ai/listing-inputs/service.jpg', 'service');

    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'service_listing',
        'version' => 1,
        'status' => 'pending',
        'payload' => [
            '_feature' => 'services',
            'name' => 'Braiding session',
            'description' => 'A full braiding appointment',
            'price_hint' => '25000',
            '_media_relative_paths' => ['ai/listing-inputs/service.jpg'],
        ],
    ]);

    actingAs($seller)->postJson(route('dashboard.ai.drafts.approve', $draft))->assertOk();

    $service = Service::query()->where('store_page_id', $page->id)->firstOrFail();

    expect($service->image_path)->toStartWith("booking/{$page->id}/services/service-")
        ->and($service->is_active)->toBeFalse();
    Storage::disk('public')->assertExists('ai/listing-inputs/service.jpg');
});
