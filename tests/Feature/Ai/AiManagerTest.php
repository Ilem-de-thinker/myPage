<?php

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiRun;
use App\Domains\Ai\Models\AiUsageLedger;
use Illuminate\Validation\ValidationException;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Symfony\Component\HttpKernel\Exception\HttpException;

function validThemePreset(): array
{
    return ['theme_key' => 'catalog', 'brand_color' => '#123456', 'accent_color' => '#abcdef', 'reason' => 'The catalog direction fits a product-led shop.'];
}

it('uses the provider and model selected in task config', function () {
    config()->set('ai.tasks.theme_and_color_preset.provider', 'gemini');
    config()->set('ai.tasks.theme_and_color_preset.model', 'gemini-test-model');
    $fake = Prism::fake([StructuredResponseFake::make()->withStructured(validThemePreset())->withUsage(new Usage(12, 8))]);

    app(AiManager::class)->run('theme_and_color_preset', ['description' => 'A modern clothing shop.']);

    $fake->assertRequest(function (array $requests): void {
        expect($requests)->toHaveCount(1);
        expect($requests[0]->provider())->toBe('gemini');
        expect($requests[0]->model())->toBe('gemini-test-model');
    });
});

it('moves to the configured fallback after a provider failure', function () {
    config()->set('ai.tasks.theme_and_color_preset.provider', 'openai');
    config()->set('ai.tasks.theme_and_color_preset.model', 'primary-model');
    config()->set('ai.tasks.theme_and_color_preset.fallback', [['provider' => 'anthropic', 'model' => 'fallback-model']]);
    $fake = Prism::fake([
        StructuredResponseFake::make()->withFinishReason(FinishReason::Error),
        StructuredResponseFake::make()->withStructured(validThemePreset()),
    ]);

    expect(app(AiManager::class)->run('theme_and_color_preset', ['description' => 'A shop.']))->toMatchArray(validThemePreset());
    $fake->assertRequest(function (array $requests): void {
        expect($requests[0]->provider())->toBe('openai');
        expect($requests[1]->provider())->toBe('anthropic');
    });
});

it('rejects invalid structured output after its correction attempt', function () {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(['theme_key' => 'catalog']),
        StructuredResponseFake::make()->withStructured(['theme_key' => 'catalog']),
    ]);

    expect(fn () => app(AiManager::class)->run('theme_and_color_preset', ['description' => 'A shop.']))
        ->toThrow(ValidationException::class);

    // The structured-output failure now leaves an audit row instead of
    // vanishing from the run history entirely.
    expect(AiRun::query()->where('task', 'theme_and_color_preset')->where('status', 'failed')->count())->toBe(1);
});

it('enforces the configured monthly credit limit', function () {
    [$seller, $page] = sellerWithPage();
    config()->set('ai.plans.free.monthly_credits', 1);
    AiUsageLedger::create(['seller_id' => $seller->id, 'store_page_id' => $page->id, 'task' => 'copy_rewrite', 'provider' => 'openai', 'model' => 'test', 'tokens_in' => 1, 'tokens_out' => 1]);

    expect(fn () => app(AiManager::class)->run('copy_rewrite', ['text' => 'Hello'], $page))
        ->toThrow(HttpException::class);
});

it('passes every structured result through the shared format guard', function () {
    $fake = Prism::fake([
        StructuredResponseFake::make()->withStructured([
            'name' => 'Ankara tote bag',
            'description' => 'A structured tote bag with a lined interior.',
            'category' => 'Bags',
            'tags' => ['ankara', 'tote'],
            'price_hint' => '',
            'seo_title' => 'Ankara tote bag',
            'seo_description' => 'A structured ankara tote bag for everyday use.',
            'variants' => [],
            'unexpected' => 'This field is not part of the schema.',
        ]),
    ]);

    $result = app(AiManager::class)->run('listing_copy', ['text' => 'Ankara tote bag']);

    expect($result)
        ->toBeArray()
        ->toHaveKeys(['name', 'description', 'category', 'tags', 'price_hint', 'seo_title', 'seo_description', 'variants'])
        ->not->toHaveKey('unexpected')
        ->and($result['price_hint'])->toBe('')
        ->and($result['tags'])->toBe(['ankara', 'tote'])
        ->and($result['variants'])->toBe([]);

    $fake->assertRequest(function (array $requests): void {
        expect($requests[0]->systemPrompts())->toHaveCount(1)
            ->and($requests[0]->systemPrompts()[0]->content)->toContain('Shared AI guardrails');
    });
});

it('rejects a non-object structured result instead of returning it', function () {
    Prism::fake([
        StructuredResponseFake::make()->withStructured(['text' => 'not a listing']),
        StructuredResponseFake::make()->withStructured(['text' => 'still not a listing']),
    ]);

    expect(fn () => app(AiManager::class)->run('listing_copy', ['text' => 'Ankara tote bag']))
        ->toThrow(ValidationException::class);
});

it('records page-less calls as platform spend', function () {
    Prism::fake([StructuredResponseFake::make()->withStructured(validThemePreset())->withUsage(new Usage(10, 5))]);

    app(AiManager::class)->run('theme_and_color_preset', ['description' => 'A shop before it exists.']);

    $row = AiUsageLedger::query()->sole();

    expect($row->seller_id)->toBeNull()
        ->and($row->store_page_id)->toBeNull()
        ->and($row->funded_by)->toBe('platform')
        ->and($row->status)->toBe('used')
        ->and($row->task)->toBe('theme_and_color_preset')
        ->and($row->tokens_in)->toBe(10)
        ->and($row->tokens_out)->toBe(5);
});

it('records buyer-facing platform calls without charging the store', function () {
    [$seller, $page] = sellerWithPage();
    Prism::fake([StructuredResponseFake::make()->withStructured(['text' => 'A friendlier reply.'])->withUsage(new Usage(7, 3))]);

    app(AiManager::class)->runPlatform('copy_rewrite', ['text' => 'Reply.'], $page);

    $row = AiUsageLedger::query()->sole();

    expect($row->seller_id)->toBeNull()
        ->and($row->store_page_id)->toBe($page->id)
        ->and($row->funded_by)->toBe('platform')
        ->and($row->status)->toBe('used')
        ->and($row->tokens_in)->toBe(7)
        ->and(AiUsageLedger::query()->where('seller_id', $seller->id)->count())->toBe(0);
});
