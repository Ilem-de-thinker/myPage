<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Ai\Services\BuyerAiBudget;
use App\Domains\Billing\Actions\SubscriptionManager;
use App\Domains\Billing\Models\Plan;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\Seller;
use Database\Seeders\PlanSeeder;

use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

function buyerStorePage(): array
{
    return sellerWithPage(['theme' => Theme::Store, 'status' => 'published', 'published_at' => now()]);
}

function subscribeBuyerPlan(string $slug): void
{
    $seller = Seller::query()->latest('id')->firstOrFail();
    $plan = Plan::query()->where('slug', $slug)->sole();
    $manager = app(SubscriptionManager::class);
    $manager->renew($manager->start($seller, $plan, 'manual'));
}

function recordBuyerRun(int $sellerId, int $pageId, string $task = 'store_assistant'): void
{
    AiRun::create([
        'seller_id' => $sellerId,
        'store_page_id' => $pageId,
        'task' => $task,
        'provider' => 'groq',
        'model' => 'test-model',
        'prompt_version' => 'v1',
        'status' => 'succeeded',
        'latency_ms' => 12,
        'tokens_in' => 10,
        'tokens_out' => 5,
    ]);
}

it('funds the storefront assistant from the platform instead of the merchant allowance', function () {
    [$seller, $page] = buyerStorePage();

    $this->mock(AiManager::class, function ($mock): void {
        $mock->shouldReceive('runPlatform')->once()->andReturn('We are open from 9am to 5pm.');
    });

    post(storeUrl($page->slug, '/ai/assistant'), ['message' => 'When do you open?'])
        ->assertOk()
        ->assertJsonPath('answer', 'We are open from 9am to 5pm.');

    expect(AiUsageLedger::query()->count())->toBe(0);
});

it('stops buyer assistant calls once the store hits its daily budget', function () {
    [$seller, $page] = buyerStorePage();
    config()->set('ai.buyer_assistant.daily_caps', ['free' => 2]);

    recordBuyerRun($seller->id, $page->id);
    recordBuyerRun($seller->id, $page->id);

    $this->mock(AiManager::class, function ($mock): void {
        $mock->shouldNotReceive('runPlatform');
    });

    post(storeUrl($page->slug, '/ai/assistant'), ['message' => 'Still there?'])
        ->assertStatus(429);
});

it('gives higher plans a bigger buyer assistant budget', function () {
    [$seller, $page] = buyerStorePage();
    subscribeBuyerPlan('trial');

    $trialCap = app(BuyerAiBudget::class)->dailyCap($page);

    subscribeBuyerPlan('pro');

    $proCap = app(BuyerAiBudget::class)->dailyCap($page->fresh());

    expect($trialCap)->toBe(40)
        ->and($proCap)->toBe(400)
        ->and($proCap)->toBeGreaterThan($trialCap);
});
