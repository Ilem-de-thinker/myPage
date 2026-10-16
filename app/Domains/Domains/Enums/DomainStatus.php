<?php

namespace App\Domains\Domains\Enums;

enum DomainStatus: string
{
    /** Added by the seller. DNS has not been checked yet, or the check failed. */
    case Pending = 'pending';

    /** DNS points at us. Serving is allowed. */
    case Active = 'active';

    /**
     * It was active and has stopped being. DNS was changed or removed after the
     * fact, so the domain is no longer served rather than being left to 404 in a
     * way that looks like the seller's site broke.
     */
    case Suspended = 'suspended';

    public function isServed(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for DNS',
            self::Active => 'Live',
            self::Suspended => 'DNS no longer points here',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Active => 'bg-emerald-100 text-emerald-800',
            self::Suspended => 'bg-slate-100 text-slate-600',
        };
    }
}
