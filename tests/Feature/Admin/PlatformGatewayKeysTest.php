<?php

use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Payments\Data\GatewayCredentials;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

function platformSettingsAdmin(): Seller
{
    return Seller::factory()->admin()->create(['email_verified_at' => now()]);
}

function platformSettingsPayload(array $extra = []): array
{
    return array_merge([
        'platform_name' => 'mypage.ng',
        'platform_tagline' => 'Sovereign storefronts for Nigerian businesses.',
        'store_theme_enabled' => '1',
        'service_theme_enabled' => '1',
    ], $extra);
}

beforeEach(function () {
    Cache::flush();
});

it('saves platform paystack keys encrypted and uses them for subscriptions', function () {
    Http::fake(['api.paystack.co/*' => Http::response(['status' => true], 200)]);

    actingAs(platformSettingsAdmin())
        ->post(route('admin.settings.update'), platformSettingsPayload([
            'paystack_secret' => 'sk_test_platform_123456',
            'paystack_public' => 'pk_test_platform_123456',
        ]))
        ->assertRedirect();

    expect(PlatformSetting::value('payments.paystack.secret'))->toBe('sk_test_platform_123456')
        ->and(PlatformSetting::value('payments.paystack.public'))->toBe('pk_test_platform_123456');

    $stored = DB::table('platform_settings')->where('key', 'payments.paystack.secret')->value('value');

    expect($stored)->not->toBe('sk_test_platform_123456');

    $credentials = GatewayCredentials::platform('paystack');

    expect($credentials->secretKey)->toBe('sk_test_platform_123456')
        ->and($credentials->publicKey)->toBe('pk_test_platform_123456')
        ->and($credentials->environment)->toBe('test');
});

it('refuses a key that does not look like a paystack secret', function () {
    Http::fake();

    actingAs(platformSettingsAdmin())
        ->post(route('admin.settings.update'), platformSettingsPayload([
            'paystack_secret' => 'sk_bogus',
        ]))
        ->assertSessionHasErrors(['paystack_secret']);

    Http::assertNothingSent();
    expect(PlatformSetting::value('payments.paystack.secret'))->toBeNull();
});

it('refuses a key the gateway rejects', function () {
    Http::fake(['api.paystack.co/*' => Http::response(['status' => false], 401)]);

    actingAs(platformSettingsAdmin())
        ->post(route('admin.settings.update'), platformSettingsPayload([
            'paystack_secret' => 'sk_test_rejected',
        ]))
        ->assertSessionHasErrors(['paystack_secret']);

    expect(PlatformSetting::value('payments.paystack.secret'))->toBeNull();
});

it('clears the saved platform keys and falls back to env', function () {
    PlatformSetting::put('payments.paystack.secret', 'sk_test_saved', 'encrypted');
    PlatformSetting::put('payments.paystack.public', 'pk_test_saved');

    actingAs(platformSettingsAdmin())
        ->post(route('admin.settings.update'), platformSettingsPayload([
            'remove_paystack' => '1',
        ]))
        ->assertRedirect();

    expect(PlatformSetting::value('payments.paystack.secret'))->toBeNull()
        ->and(PlatformSetting::value('payments.paystack.public'))->toBeNull();
});

it('does not require a paystack key to save the rest of the settings', function () {
    actingAs(platformSettingsAdmin())
        ->post(route('admin.settings.update'), platformSettingsPayload([
            'platform_tagline' => 'A new tagline.',
        ]))
        ->assertRedirect();

    expect(PlatformSetting::value('platform_tagline'))->toBe('A new tagline.');
});
