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

it('returns one complete storefront direction and applies it only after approval', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $direction = [
        'theme_key' => 'store',
        'brand_color' => '#112233',
        'accent_color' => '#445566',
        'surface' => 'dark',
        'radius' => 'plush',
        'hero_style' => 'split',
        'hero_alignment' => 'left',
        'hero_overlay' => 'soft',
        'carousel_autoplay' => true,
        'show_trust_strip' => false,
        'show_metrics' => true,
        'hidden_sections' => ['categories', 'not-a-section'],
        'copy' => [
            'home' => [
                'hero' => ['headline' => 'Ankara, cut for you', 'subtext' => 'Made in Surulere.', 'button_label' => 'Shop the studio'],
                'about' => ['heading' => 'The studio', 'body' => 'We cut and sew in the same room.'],
                'faq' => ['heading' => 'Good to know', 'items' => [['question' => 'Do you deliver?', 'answer' => 'Yes, nationwide.']]],
                'reviews' => ['heading' => 'What buyers say'],
            ],
            'about' => ['story' => ['heading' => 'Our story', 'body' => 'One machine and a stall.']],
            'contact' => ['intro' => ['intro' => 'Visit the studio.', 'hours' => 'Mon-Fri 9-6']],
            'unknown' => ['block' => ['field' => 'nope']],
        ],
        'reason' => 'Warm editorial direction.',
    ];
    $fake = Prism::fake([StructuredResponseFake::make()->withStructured($direction)]);

    $response = actingAs($seller)->postJson('/ai/contextual', [
        'feature' => 'appearance',
        'input' => ['text' => 'Ada Textiles sells fabrics.'],
    ])->assertOk()
        ->assertJsonPath('result.theme_key', 'editorial-luxe')
        ->assertJsonPath('result.hidden_sections', ['categories'])
        ->assertJsonPath('result.copy.home.faq.items.0.question', 'Do you deliver?')
        ->assertJsonMissingPath('result.copy.home.reviews')
        ->assertJsonMissingPath('result.copy.contact.intro.hours')
        ->assertJsonMissingPath('result.copy.unknown');

    $fake->assertRequest(function (array $requests): void {
        expect((string) $requests[0]->prompt())
            ->toContain('"key":"catalog"')
            ->toContain('"copy_registry"')
            ->not->toContain('"key":"store"');
    });

    expect($page->fresh()->brand_color)->not->toBe('#112233');

    actingAs($seller)->postJson("/ai/drafts/{$response->json('draft_id')}/approve")->assertOk();

    $page = $page->fresh();

    expect($page->brand_color)->toBe('#112233')
        ->and($page->settings['theme_preset'])->toBe('editorial-luxe')
        ->and($page->settings['surface'])->toBe('dark')
        ->and($page->settings['radius'])->toBe('plush')
        ->and($page->settings['hero_style'])->toBe('split')
        ->and($page->settings['carousel_autoplay'])->toBeTrue()
        ->and($page->settings['show_trust_strip'])->toBeFalse()
        ->and($page->settings['hidden_sections']['home'])->toBe(['categories'])
        ->and($page->block('home', 'hero')->content['headline'])->toBe('Ankara, cut for you')
        ->and($page->block('home', 'faq')->content['items'][0]['answer'])->toBe('Yes, nationwide.')
        ->and($page->block('about', 'story')->content['body'])->toBe('One machine and a stall.')
        ->and($page->block('home', 'reviews'))->toBeNull()
        ->and($page->block('contact', 'intro')->content)->not->toHaveKey('hours');
});

it('restores the page blocks when a storefront direction is undone', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $page->blocks()->create(['page' => 'home', 'key' => 'hero', 'content' => ['headline' => 'Before AI']]);

    $draft = AiDraft::create([
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
            'hero_alignment' => 'center',
            'hero_overlay' => 'deep',
            'carousel_autoplay' => false,
            'show_trust_strip' => true,
            'show_metrics' => false,
            'hidden_sections' => [],
            'copy' => ['home' => ['hero' => ['headline' => 'After AI']]],
            'reason' => 'A clean catalogue.',
        ],
    ]);

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/approve")->assertOk();
    expect($page->block('home', 'hero')->content['headline'])->toBe('After AI');

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/undo")->assertOk();
    expect($page->fresh()->block('home', 'hero')->content['headline'])->toBe('Before AI');
});

it('applies a compatible preset when an older draft carries a theme enum key', function () {
    [$seller, $page] = sellerWithPage(['theme' => Theme::Store]);
    $draft = AiDraft::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'theme_and_color_preset',
        'version' => 1,
        'status' => 'pending',
        'payload' => ['presets' => [[
            'theme_key' => 'store',
            'brand_color' => '#112233',
            'accent_color' => '#445566',
            'reason' => 'An older draft.',
        ]]],
    ]);

    actingAs($seller)->postJson("/ai/drafts/{$draft->id}/approve", ['preset' => 0])->assertOk();

    expect(in_array($page->fresh()->settings['theme_preset'], array_keys(config('themes.presets')), true))->toBeTrue()
        ->and($page->fresh()->brand_color)->toBe('#112233')
        ->and($draft->fresh()->status)->toBe('approved');
});
