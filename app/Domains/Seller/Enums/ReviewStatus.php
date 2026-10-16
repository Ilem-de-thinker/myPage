<?php

namespace App\Domains\Seller\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for you',
            self::Approved => 'Published',
            self::Rejected => 'Declined',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800',
            self::Approved => 'bg-emerald-100 text-emerald-800',
            self::Rejected => 'bg-slate-100 text-slate-600',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Approved;
    }
}
