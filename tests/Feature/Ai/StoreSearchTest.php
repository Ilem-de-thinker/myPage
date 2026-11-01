<?php

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;

use function Pest\Laravel\postJson;

/**
 * A complete marketplace_intent result; overrides keep each test focused on
 * the one field it exercises.
 */
function storeSearchIntent(array $overrides = []): array
{
    return array_merge([
        'intent' => 'Buy an ankara bag',
        'search_term' => 'ankara bag',
        'kind' => 'product',
        'category' => '',
        'budget_min' => '',
        'budget_max' => '',
        'location' => '',
        'urgency' => '',
        'preferences' => [],
        'questions' => [],
    ], $overrides);
}

function searchableStorePage(): StorePage
{
    return StorePage::factory()->create([
        'theme' => 'store',
        'status' => 'published',
        'published_at' => now(),
    ]);
}

it('returns the parsed kind in applied so the overlay can show it', function () {
    $page = searchableStorePage();
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'status' => 'active', 'name' => 'Ankara Bag']);
    Service::withoutGlobalScopes()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'is_active' => true, 'is_directory_listed' => true, 'name' => 'Ankara weaving class', 'slug' => 'weaving-class']);

    Prism::fake([StructuredResponseFake::make()->withStructured(storeSearchIntent())]);

    $json = postJson(storeUrl($page->slug, '/search/ai'), ['message' => 'ankara bag'])->assertOk()->json();

    expect($json['mode'])->toBe('ai')
        ->and($json['applied'])->toMatchArray(['q' => 'ankara bag', 'kind' => 'product'])
        ->and($json['results'])->toHaveCount(1)
        ->and($json['results'][0]['type'])->toBe('product');
});

it('omits kind from applied when the intent is ambiguous', function () {
    $page = searchableStorePage();
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'status' => 'active', 'name' => 'Ankara Bag']);

    Prism::fake([StructuredResponseFake::make()->withStructured(storeSearchIntent(['kind' => 'any']))]);

    $json = postJson(storeUrl($page->slug, '/search/ai'), ['message' => 'ankara'])->assertOk()->json();

    expect($json['applied'])->not->toHaveKey('kind')
        ->and($json['results'])->toHaveCount(1);
});

it('degrades to keyword search without a kind when the provider fails', function () {
    $page = searchableStorePage();
    Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'status' => 'active', 'name' => 'Ankara Print']);

    Prism::fake([
        StructuredResponseFake::make()->withFinishReason(FinishReason::Error),
        StructuredResponseFake::make()->withFinishReason(FinishReason::Error),
    ]);

    $json = postJson(storeUrl($page->slug, '/search/ai'), ['message' => 'ankara'])->assertOk()->json();

    expect($json['mode'])->toBe('keyword')
        ->and($json['applied'])->not->toHaveKey('kind')
        ->and($json['results'])->toHaveCount(1);
});
