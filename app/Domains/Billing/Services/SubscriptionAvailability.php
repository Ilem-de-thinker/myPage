<?php

namespace App\Domains\Billing\Services;

use App\Domains\Admin\Models\PlatformSetting;

final class SubscriptionAvailability
{
    private const SETTING = 'billing.subscriptions.enabled';

    public function enabled(): bool
    {
        return (bool) PlatformSetting::value(self::SETTING, true);
    }

    public function disable(): void
    {
        PlatformSetting::put(self::SETTING, false, 'boolean');
    }

    public function enable(): void
    {
        PlatformSetting::put(self::SETTING, true, 'boolean');
    }
}
