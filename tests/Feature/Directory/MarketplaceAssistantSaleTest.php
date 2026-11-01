<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use Mockery\MockInterface;

use function Pest\Laravel\postJson;

function assistantPage(): StorePage
{
    return StorePage::factory()->create([
        'name' => 'Assistant Fabric Shop',
        'slug' => 'assistant-fabric-shop',
        'theme' => 'store',
        'status' => 'published',
        'published_at' => now(),
        'is_directory_opted_in' => true,
        'city' => 'Lagos',
        'state' => 'Lagos',
    ]);
}

it('returns the effective price and original price from help me buy', function () {
    config(['mypage.features.directory' => true]);

    $page = assistantPage();
    $product = Product::factory()->create([
        'store_page_id' => $page->id,
        'seller_id' => $page->seller_id,
        'status' => 'active',
        'is_directory_listed' => true,
        'name' => 'Ankara Bag',
        'price_kobo' => 15_000,
        'compare_at_kobo' => 20_000,
        'sale_starts_at' => now()->subDay(),
        'sale_ends_at' => now()->addDay(),
    ]);

    $this->mock(AiManager::class, function (MockInterface $mock) use ($product): void {
        $mock->shouldReceive('run')
            ->with('marketplace_intent', Mockery::type('array'))
            ->once()
            ->andReturn([
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
            ]);

        $mock->shouldReceive('run')
            ->with('marketplace_rank', Mockery::type('array'))
            ->once()
            ->andReturn([
                'results' => [
                    ['id' => 'product:'.$product->id, 'reason' => 'Matches the requested bag.'],
                ],
            ]);
    });

    $json = postJson(route('directory.assist'), ['message' => 'ankara bag'])
        ->assertOk()
        ->json();

    expect($json['results'])->toHaveCount(1)
        ->and($json['results'][0]['price'])->toBe(Money::format(15_000))
        ->and($json['results'][0]['originalPrice'])->toBe(Money::format(20_000))
        ->and($json['results'][0]['priceValue'])->toBe(15_000)
        ->and($json['results'][0]['originalPriceValue'])->toBe(20_000);
});
