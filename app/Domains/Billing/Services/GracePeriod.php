<?php

namespace App\Domains\Billing\Services;

use App\Domains\Admin\Models\PlatformSetting;
use Illuminate\Support\Carbon;

/**
 * The platform-wide grace window, overridable by an operator without a deploy.
 */
final class GracePeriod
{
    public const SETTING = 'billing.grace_days';

    public function days(): int
    {
        return max(0, (int) PlatformSetting::value(
            self::SETTING,
            (int) config('billing.grace_days', 7),
        ));
    }

    public function end(?Carbon $from = null): Carbon
    {
        return ($from ?? now())->copy()->addDays($this->days());
    }
}
