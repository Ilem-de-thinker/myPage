<?php

namespace App\Domains\Billing\Enums;

/**
 * The one status a merchant, an operator or a buyer-facing gate should read.
 *
 * The database still stores gateway lifecycle values (trialing, past_due and so
 * on) for history, but whether somebody may write is always answered here.
 */
enum MerchantStatus: string
{
    case Active = 'active';

    case Grace = 'grace';

    case Expired = 'expired';

    case Cancelled = 'cancelled';

    public function allowsWrites(): bool
    {
        return in_array($this, [self::Active, self::Grace], true);
    }

    public function isLapsed(): bool
    {
        return ! $this->allowsWrites();
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Grace => 'Grace period',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Active => 'Your plan is running normally.',
            self::Grace => 'Your payment did not go through, but you still have access while you sort it out.',
            self::Expired => 'Your plan has ended. Your dashboard is read-only and your storefront is not accepting new orders.',
            self::Cancelled => 'Your subscription is cancelled. Your dashboard is read-only and your storefront is not accepting new orders.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Active => 'bg-emerald-100 text-emerald-800',
            self::Grace => 'bg-amber-100 text-amber-800',
            self::Expired, self::Cancelled => 'bg-slate-100 text-slate-600',
        };
    }
}
