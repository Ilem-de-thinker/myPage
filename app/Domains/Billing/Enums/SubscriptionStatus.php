<?php

namespace App\Domains\Billing\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';

    case Active = 'active';

    /** Checkout started but no money has landed yet. Grants nothing. */
    case Pending = 'pending';

    /** Payment failed or ran out. The seller is warned before anything changes. */
    case PastDue = 'past_due';

    case Cancelled = 'cancelled';

    case Expired = 'expired';

    /**
     * Gateway lifecycle statuses are history; use ResolveMerchantStatus for any
     * access decision. This remains for callers that need the raw mapping.
     */
    public function allowsWrites(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }

    /**
     * Whether the money is genuinely in hand. A trial and a lapsed subscription
     * both owe nothing, which matters when deciding whether to keep selling.
     */
    public function isPaid(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Free trial',
            self::Active => 'Active',
            self::Pending => 'Awaiting payment',
            self::PastDue => 'Payment due',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Trialing => 'bg-sky-100 text-sky-800',
            self::Active => 'bg-emerald-100 text-emerald-800',
            self::Pending, self::PastDue => 'bg-amber-100 text-amber-800',
            self::Cancelled, self::Expired => 'bg-slate-100 text-slate-600',
        };
    }
}
