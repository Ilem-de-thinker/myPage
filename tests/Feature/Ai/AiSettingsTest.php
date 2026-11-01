<?php

use App\Domains\Admin\Models\AdminAuditLog;
use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Ai\Services\AiCreditService;
use App\Domains\Ai\Services\AiSettings;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\Crypt;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\Testing\TextResponseFake;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

function aiCoreAdmin(): Seller
{
    return Seller::factory()->coreAdmin()->create(['email_verified_at' => now()]);
}

function aiStaffAdmin(): Seller
{
    return Seller::factory()->staffAdmin()->create(['email_verified_at' => now()]);
}

function aiSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'enabled' => '1',
        'provider' => 'groq',
        'model' => 'openai/gpt-oss-120b',
        'api_key' => 'gsk_live_super_secret_key_1234',
        'timeout' => 24,
        'retries' => 2,
        'feature_limits' => [],
    ], $overrides);
}

function aiThemeResult(): array
{
    return ['theme_key' => 'catalog', 'brand_color' => '#123456', 'accent_color' => '#abcdef', 'reason' => 'Fits the shop.'];
}

it('lets core admins manage AI settings and keeps staff out', function () {
    $core = aiCoreAdmin();
    $staff = aiStaffAdmin();

    actingAs($core)->get(route('admin.ai-settings'))
        ->assertOk()
        ->assertSee('AI settings')
        ->assertSee('Per-feature monthly caps');

    actingAs($staff)->get(route('admin.ai-settings'))->assertForbidden();
    actingAs($staff)->post(route('admin.ai-settings.update'), aiSettingsPayload())->assertForbidden();
    actingAs($staff)->post(route('admin.ai-settings.test'))->assertForbidden();
});

it('saves AI settings and encrypts the key at rest', function () {
    $admin = aiCoreAdmin();
    $key = 'gsk_live_super_secret_key_1234';

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload([
        'provider' => 'openrouter',
        'model' => 'anthropic/claude-sonnet-4',
        'feature_limits' => ['listing_copy' => 12],
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $raw = PlatformSetting::query()->where('key', 'ai.api_key')->value('value');
    $settings = app(AiSettings::class);

    expect($raw)->not->toBe($key)
        ->and(Crypt::decryptString($raw))->toBe($key)
        ->and($settings->provider())->toBe('openrouter')
        ->and($settings->model())->toBe('anthropic/claude-sonnet-4')
        ->and($settings->timeout())->toBe(24)
        ->and($settings->retries())->toBe(2)
        ->and($settings->limitFor('listing_copy'))->toBe(12)
        ->and($settings->configured())->toBeTrue()
        ->and($settings->maskedKey())->toBe('gsk_…1234');

    actingAs($admin)->get(route('admin.ai-settings'))
        ->assertOk()
        ->assertSee('gsk_…1234')
        ->assertDontSee($key);
});

it('leaves the saved key untouched when the key field is blank', function () {
    $admin = aiCoreAdmin();

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload())->assertRedirect();

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload([
        'api_key' => '',
        'model' => 'openai/gpt-oss-20b',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $raw = PlatformSetting::query()->where('key', 'ai.api_key')->value('value');

    expect(Crypt::decryptString($raw))->toBe('gsk_live_super_secret_key_1234')
        ->and(app(AiSettings::class)->model())->toBe('openai/gpt-oss-20b');
});

it('clears the cached settings immediately after saving', function () {
    $admin = aiCoreAdmin();

    PlatformSetting::put('ai.provider', 'groq');
    expect(PlatformSetting::value('ai.provider'))->toBe('groq');

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload([
        'provider' => 'openai',
        'model' => 'gpt-5-mini',
    ]))->assertRedirect();

    expect(PlatformSetting::value('ai.provider'))->toBe('openai')
        ->and(PlatformSetting::value('ai.model'))->toBe('gpt-5-mini');
});

it('falls back to environment configuration when no settings exist', function () {
    config([
        'ai.provider' => 'openai',
        'ai.model' => 'gpt-env-model',
        'ai.api_key' => 'env-secret-key-1234',
        'ai.timeout' => 17,
        'ai.retries' => 4,
        'ai.feature_limits' => ['translate' => 6],
    ]);

    $settings = app(AiSettings::class);

    expect($settings->provider())->toBe('openai')
        ->and($settings->model())->toBe('gpt-env-model')
        ->and($settings->timeout())->toBe(17)
        ->and($settings->retries())->toBe(4)
        ->and($settings->limitFor('translate'))->toBe(6)
        ->and($settings->configured())->toBeTrue()
        ->and($settings->maskedKey())->toBe('env-…1234');

    $settings->apply();

    expect(config('ai.tasks.listing_copy.provider'))->toBe('openai')
        ->and(config('ai.tasks.listing_copy.model'))->toBe('gpt-env-model')
        ->and(config('prism.providers.openai.api_key'))->toBe('env-secret-key-1234');
});

it('applies saved provider, model, timeout and retries to every AI run', function () {
    PlatformSetting::put('ai.provider', 'openrouter');
    PlatformSetting::put('ai.model', 'admin/selected-model');
    PlatformSetting::put('ai.api_key', 'admin-secret-key-9876', 'encrypted');
    PlatformSetting::put('ai.timeout', 12, 'integer');
    PlatformSetting::put('ai.retries', 3, 'integer');

    $fake = Prism::fake([
        StructuredResponseFake::make()->withStructured(aiThemeResult()),
    ]);

    app(AiManager::class)->run('theme_and_color_preset', ['description' => 'A shop.']);

    $fake->assertRequest(function (array $requests): void {
        expect($requests)->toHaveCount(1)
            ->and($requests[0]->provider())->toBe('openrouter')
            ->and($requests[0]->model())->toBe('admin/selected-model')
            ->and($requests[0]->clientOptions()['timeout'])->toBe(12)
            ->and($requests[0]->clientRetry()[0])->toBe(3);
    });

    expect(config('prism.providers.openrouter.api_key'))->toBe('admin-secret-key-9876');
});

it('turns AI off globally without changing task configuration', function () {
    PlatformSetting::put('ai.enabled', false, 'boolean');

    expect(app(AiSettings::class)->enabled())->toBeFalse()
        ->and(config('ai.tasks.listing_copy.provider'))->toBe('groq');

    try {
        app(AiManager::class)->run('copy_rewrite', ['text' => 'Hello']);
        $this->fail('AI should be disabled.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(503);
    }
});

it('reports a successful connection without exposing the key', function () {
    $admin = aiCoreAdmin();
    $key = 'gsk_connection_secret_4321';

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload(['api_key' => $key]))->assertRedirect();

    Prism::fake([TextResponseFake::make()->withText('OK')]);

    actingAs($admin)->post(route('admin.ai-settings.test'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Connection successful.');

    $notes = AdminAuditLog::where('action', 'ai.settings.tested')->pluck('note')->implode(' ');

    expect($notes)->toContain('groq')
        ->and($notes)->not->toContain($key);
});

it('reports a failed connection without leaking the key', function () {
    $admin = aiCoreAdmin();
    $key = 'gsk_failure_secret_8765';

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload(['api_key' => $key]))->assertRedirect();

    Prism::fake([TextResponseFake::make()->withFinishReason(FinishReason::Error)]);

    actingAs($admin)->post(route('admin.ai-settings.test'))
        ->assertRedirect()
        ->assertSessionHasErrors('ai_test');

    $notes = AdminAuditLog::where('action', 'ai.settings.tested')->pluck('note')->implode(' ');

    expect($notes)->toContain('failed')
        ->and($notes)->not->toContain($key);
});

it('audits changed setting names but never the key value', function () {
    $admin = aiCoreAdmin();
    $key = 'gsk_audit_secret_2468';

    actingAs($admin)->post(route('admin.ai-settings.update'), aiSettingsPayload(['api_key' => $key]))->assertRedirect();

    $note = AdminAuditLog::where('action', 'ai.settings.updated')->latest('id')->value('note');

    expect($note)->toContain('ai.provider')
        ->and($note)->toContain('ai.api_key')
        ->and($note)->not->toContain($key);
});

it('caps one task without blocking another', function () {
    [$seller, $page] = sellerWithPage();

    config(['ai.plans.free.monthly_credits' => 100]);
    PlatformSetting::put('ai.feature_limits', ['listing_copy' => 1], 'json');

    AiUsageLedger::create([
        'seller_id' => $seller->id,
        'store_page_id' => $page->id,
        'task' => 'listing_copy',
        'provider' => 'groq',
        'model' => 'test',
        'tokens_in' => 1,
        'tokens_out' => 1,
    ]);

    $credits = app(AiCreditService::class);

    expect($credits->estimate($page, 1, 'listing_copy')['allowed'])->toBe(0)
        ->and($credits->estimate($page, 1, 'copy_rewrite')['allowed'])->toBe(1);

    Prism::fake([StructuredResponseFake::make()->withStructured(['text' => 'Rewritten.'])]);

    try {
        app(AiManager::class)->run('listing_copy', ['text' => 'Original'], $page);
        $this->fail('The capped task should be blocked.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(429)
            ->and($exception->getMessage())->toContain('Product copy');
    }

    Prism::fake([StructuredResponseFake::make()->withStructured(['text' => 'Rewritten.'])]);

    expect(app(AiManager::class)->run('copy_rewrite', ['text' => 'Original'], $page))
        ->toMatchArray(['text' => 'Rewritten.']);
});
