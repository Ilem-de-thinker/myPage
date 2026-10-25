<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Support\AiFeatureRegistry;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

use function Pest\Laravel\actingAs;

it('allows product AI only for store-capable businesses', function () {
    $service = StorePage::factory()->create(['theme' => Theme::Service]);
    $store = StorePage::factory()->create(['theme' => Theme::Store]);

    expect(AiFeatureRegistry::allowed($service, 'products'))->toBeFalse()
        ->and(AiFeatureRegistry::allowed($store, 'products'))->toBeTrue();
});

it('allows service AI only for service-capable businesses', function () {
    $service = StorePage::factory()->create(['theme' => Theme::Service]);
    $store = StorePage::factory()->create(['theme' => Theme::Store]);

    expect(AiFeatureRegistry::allowed($service, 'services'))->toBeTrue()
        ->and(AiFeatureRegistry::allowed($store, 'services'))->toBeFalse();
});

it('allows both contextual listing features for a both business', function () {
    $page = StorePage::factory()->create([
        'theme' => Theme::Store,
        'settings' => ['business_type' => 'both'],
    ]);

    expect(AiFeatureRegistry::allowed($page, 'products'))->toBeTrue()
        ->and(AiFeatureRegistry::allowed($page, 'services'))->toBeTrue();
});

it('blocks the wrong contextual AI endpoint on the server', function () {
    [$serviceSeller] = sellerWithPage(['theme' => Theme::Service]);
    [$storeSeller] = sellerWithPage(['theme' => Theme::Store]);

    actingAs($serviceSeller)->postJson('/ai/contextual', ['feature' => 'products', 'input' => ['text' => 'bag']])->assertForbidden();
    actingAs($storeSeller)->postJson('/ai/contextual', ['feature' => 'services', 'input' => ['text' => 'consulting']])->assertForbidden();
});

it('does not expose product routes to a service-only business', function () {
    [$seller] = sellerWithPage(['theme' => Theme::Service]);

    actingAs($seller)->get('/products')->assertNotFound();
});

it('queues one independent operation per item in a ten-item batch', function () {
    [$seller] = sellerWithPage(['theme' => Theme::Store]);
    Queue::fake();

    $items = collect(range(1, 10))->map(fn (int $number): array => ['text' => "Product {$number}, 1000"])->all();

    actingAs($seller)->postJson('/ai/batch', ['feature' => 'products', 'items' => $items])
        ->assertAccepted()
        ->assertJsonCount(10, 'operations');

    expect(AiOperation::count())->toBe(10);
    Queue::assertPushed(RunAiOperation::class, 10);
});

it('confirms only selected drafts', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $drafts = collect(range(1, 2))->map(fn (int $number): AiDraft => AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'version' => $number,
        'status' => 'pending',
        'payload' => ['_feature' => 'products', 'name' => "Draft {$number}", 'description' => 'Draft description'],
    ]));

    actingAs($seller)->postJson('/ai/drafts/confirm-selected', ['draft_ids' => [$drafts->first()->id]])
        ->assertOk()
        ->assertJsonCount(1, 'confirmed');

    expect(Product::where('store_page_id', $page->id)->count())->toBe(1)
        ->and($drafts->first()->fresh()->status)->toBe('approved')
        ->and($drafts->last()->fresh()->status)->toBe('pending');
});

it('routes content translation through the translation task and waits for approval', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Prism::fake([
        StructuredResponseFake::make()->withStructured(['text' => 'Ka yi maraba']),
        StructuredResponseFake::make()->withStructured(['text' => 'Ka yi maraba']),
    ]);

    actingAs($seller)->postJson('/ai/contextual', [
        'feature' => 'content',
        'input' => ['text' => 'Welcome', 'mode' => 'translate', 'language' => 'Yoruba'],
    ])->assertOk()->assertJsonPath('result.text', 'Ka yi maraba');

    expect(AiDraft::where('store_page_id', $page->id)->latest()->value('task'))->toBe('translate');
});

it('suggests independent photo groups and queues a retry for failed work', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    Prism::fake([StructuredResponseFake::make()->withStructured(['groups' => [['1', '2'], ['3']], 'reason' => 'Two angles show the same item.'])]);

    actingAs($seller)->postJson('/ai/photo-groups', [
        'feature' => 'products',
        'photos' => [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg'), UploadedFile::fake()->image('three.jpg')],
    ])->assertOk()->assertJsonPath('groups.0.0', 1);

    Queue::fake();
    $operation = AiOperation::create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'task' => 'listing_copy', 'status' => 'failed', 'input' => ['feature' => 'products', 'text' => 'Bag']]);
    actingAs($seller)->postJson("/ai/operations/{$operation->id}/retry")->assertAccepted();
    expect($operation->fresh()->status)->toBe('queued');
    Queue::assertPushed(RunAiOperation::class);
});

it('applies logo colors only after the merchant confirms the draft', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store, 'brand_color' => '#111111', 'settings' => ['business_type' => 'store']]);
    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'theme_and_color_preset',
        'version' => 1,
        'status' => 'pending',
        'payload' => ['_logo_colors' => true, 'brand_color' => '#223344', 'accent_color' => '#aabbcc'],
    ]);

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/approve")->assertOk();

    expect($page->fresh()->brand_color)->toBe('#223344')
        ->and($page->fresh()->settings['accent_color'])->toBe('#aabbcc')
        ->and($draft->fresh()->status)->toBe('approved');
});

it('keeps a listing out of the catalogue until the merchant confirms it', function () {
    [$seller] = sellerWithPage(['theme' => Theme::Store]);
    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $seller->storePages()->first()->id,
        'task' => 'listing_copy',
        'version' => 1,
        'status' => 'pending',
        'payload' => ['_feature' => 'products', 'name' => 'Woven basket', 'description' => 'Handwoven in Ibadan'],
    ]);

    expect(Product::count())->toBe(0);

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/approve")->assertOk();
    expect(Product::count())->toBe(1);

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/undo")->assertOk();
    expect(Product::count())->toBe(0)
        ->and($draft->fresh()->status)->toBe('undone');
});

it('restores the previous appearance when an AI preset is undone', function () {
    [$seller, $page] = sellerWithPage([
        'theme' => Theme::Store,
        'brand_color' => '#111111',
        'tagline' => 'Before AI',
        'settings' => ['theme_preset' => 'catalog', 'accent_color' => '#222222'],
    ]);
    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'theme_and_color_preset',
        'version' => 1,
        'status' => 'pending',
        'payload' => ['presets' => [[
            'theme_key' => 'minimal-swiss',
            'brand_color' => '#334455',
            'accent_color' => '#ddeeff',
        ]]],
    ]);

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/approve", ['preset' => 0])->assertOk();
    expect($page->fresh()->brand_color)->toBe('#334455');

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/undo")->assertOk();
    expect($page->fresh()->brand_color)->toBe('#111111')
        ->and($page->fresh()->settings['theme_preset'])->toBe('catalog')
        ->and($page->fresh()->settings['accent_color'])->toBe('#222222');
});

it('keeps successful batch drafts when one item fails', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $operations = collect(range(0, 2))->map(fn (int $index): AiOperation => AiOperation::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'status' => 'queued',
        'input' => ['feature' => 'products', 'batch_index' => $index, 'text' => "Product {$index}"],
    ]));

    $ai = Mockery::mock(AiManager::class);
    $ai->shouldReceive('run')->andReturnUsing(function (string $task, array $input): array {
        if ($input['batch_index'] === 1) {
            throw new RuntimeException('This item could not be generated.');
        }

        return ['name' => 'Product '.$input['batch_index'], 'description' => 'Generated draft'];
    });

    foreach ($operations as $operation) {
        try {
            (new RunAiOperation($operation))->handle($ai);
        } catch (RuntimeException) {
            // The queue would retry this operation without touching the others.
        }
    }

    expect(AiDraft::count())->toBe(2)
        ->and($operations[1]->fresh()->status)->toBe('failed')
        ->and($operations[0]->fresh()->status)->toBe('succeeded')
        ->and($operations[2]->fresh()->status)->toBe('succeeded');
});

it('limits a batch to the remaining AI credits and keeps completed drafts', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    config(['ai.plans.free.monthly_credits' => 2]);
    Queue::fake();

    $existing = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'version' => 1,
        'status' => 'approved',
        'payload' => ['_feature' => 'products', 'name' => 'Already approved'],
    ]);

    actingAs($seller)->postJson('/ai/batch', [
        'feature' => 'products',
        'items' => collect(range(1, 5))->map(fn (int $number): array => ['text' => "Product {$number}"])->all(),
    ])->assertAccepted()
        ->assertJsonCount(2, 'operations')
        ->assertJsonPath('upgrade_required', true)
        ->assertJsonPath('estimate.allowed', 2);

    expect(AiOperation::count())->toBe(2)
        ->and($existing->fresh()->status)->toBe('approved');
});

it('shows the floating AI launcher only on registered seller pages', function () {
    [$seller] = sellerWithPage(['theme' => Theme::Store]);

    actingAs($seller)->get('/products')
        ->assertOk()
        ->assertSee('aiLauncher', false);

    actingAs($seller)->get('/chat')
        ->assertOk()
        ->assertDontSee('aiLauncher', false);

    actingAs($seller)->get('/ai')->assertRedirect(route('dashboard'));
});
