<?php

namespace App\Domains\Admin\Models;

enum BadgeStatus: string
{
    case Pending = 'pending';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case NeedsMoreInfo = 'needs_more_info';

    case Expired = 'expired';

    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Approved => 'Verified',
            self::Rejected => 'Rejected',
            self::NeedsMoreInfo => 'Needs more information',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Approved => 'bg-emerald-100 text-emerald-800',
            self::Rejected, self::Revoked => 'bg-red-100 text-red-700',
            self::NeedsMoreInfo => 'bg-sky-100 text-sky-800',
            self::Expired => 'bg-slate-100 text-slate-700',
        };
    }
}
