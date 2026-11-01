<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Actions\RecordAdminAction;
use App\Domains\Admin\Models\PlatformSetting;
use App\Domains\Billing\Services\GracePeriod;
use App\Domains\Billing\Services\SubscriptionAvailability;
use App\Shared\Support\MapProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlatformSettingsController extends Controller
{
    public function edit(SubscriptionAvailability $availability, GracePeriod $grace): View
    {
        return view('admin.settings', [
            'settings' => [
                'platform_name' => PlatformSetting::value('platform_name', 'mypage.ng'),
                'support_email' => PlatformSetting::value('support_email', ''),
                'platform_tagline' => PlatformSetting::value('platform_tagline', 'Sovereign storefronts for Nigerian businesses.'),
                'store_theme_enabled' => PlatformSetting::value('theme.store.enabled', true),
                'service_theme_enabled' => PlatformSetting::value('theme.service.enabled', true),
                'store_carousel_enabled' => PlatformSetting::value('theme.store.carousel', true),
                'service_carousel_enabled' => PlatformSetting::value('theme.service.carousel', true),
                'store_hero_styles_enabled' => PlatformSetting::value('theme.store.hero_styles', true),
                'service_hero_styles_enabled' => PlatformSetting::value('theme.service.hero_styles', true),
                'map_provider' => MapProvider::selection(),
            ],
            'logoPath' => PlatformSetting::value('platform_logo_path'),
            'subscriptionsEnabled' => $availability->enabled(),
            'graceDays' => $grace->days(),
        ]);
    }

    public function update(
        Request $request,
        SubscriptionAvailability $availability,
        RecordAdminAction $audit,
    ): RedirectResponse {
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:80'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'platform_tagline' => ['required', 'string', 'max:180'],
            'platform_logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'store_theme_enabled' => ['sometimes', 'boolean'],
            'service_theme_enabled' => ['sometimes', 'boolean'],
            'store_carousel_enabled' => ['sometimes', 'boolean'],
            'service_carousel_enabled' => ['sometimes', 'boolean'],
            'store_hero_styles_enabled' => ['sometimes', 'boolean'],
            'service_hero_styles_enabled' => ['sometimes', 'boolean'],
            'subscriptions_enabled' => ['sometimes', 'boolean'],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'map_provider' => ['sometimes', Rule::in([MapProvider::OPENSTREETMAP, MapProvider::GOOGLE])],
        ]);

        if (! $request->boolean('store_theme_enabled') && ! $request->boolean('service_theme_enabled')) {
            return back()
                ->withErrors(['themes' => 'Keep at least one storefront theme enabled.'])
                ->withInput();
        }

        PlatformSetting::put('platform_name', trim($data['platform_name']));
        PlatformSetting::put('support_email', trim((string) ($data['support_email'] ?? '')));
        PlatformSetting::put('platform_tagline', trim($data['platform_tagline']));

        if (isset($data['map_provider'])) {
            PlatformSetting::put('platform.map_provider', $data['map_provider']);
        }

        foreach ([
            'store_theme_enabled' => 'theme.store.enabled',
            'service_theme_enabled' => 'theme.service.enabled',
            'store_carousel_enabled' => 'theme.store.carousel',
            'service_carousel_enabled' => 'theme.service.carousel',
            'store_hero_styles_enabled' => 'theme.store.hero_styles',
            'service_hero_styles_enabled' => 'theme.service.hero_styles',
        ] as $input => $key) {
            PlatformSetting::put($key, $request->boolean($input), 'boolean');
        }

        if (array_key_exists('grace_days', $data)) {
            PlatformSetting::put('billing.grace_days', (int) $data['grace_days'], 'integer');
        }

        if ($request->boolean('subscriptions_enabled')) {
            $availability->enable();
        } else {
            $availability->disable();
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo();
        } elseif ($request->hasFile('platform_logo')) {
            $this->deleteLogo();
            PlatformSetting::put(
                'platform_logo_path',
                $request->file('platform_logo')->store('platform/brand', 'public'),
            );
        }

        $audit->handle($request->user(), 'platform.settings.updated', null, 'Platform settings saved.');

        return back()->with('status', 'Platform settings saved.');
    }

    private function deleteLogo(): void
    {
        $path = PlatformSetting::value('platform_logo_path');

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        PlatformSetting::forget('platform_logo_path');
    }
}
