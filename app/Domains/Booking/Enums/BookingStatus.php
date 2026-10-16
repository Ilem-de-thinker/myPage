<?php

namespace App\Domains\Booking\Enums;

/**
 * The lifecycle of a booking. Status only ever changes through UpdateBookingStatus,
 * which validates the move and writes a row to booking_status_histories.
 */
enum BookingStatus: string
{
    /** Created and holding its slot while the buyer pays. */
    case PendingPayment = 'pending_payment';

    /** Paid (or free), slot secured. */
    case Confirmed = 'confirmed';

    case Completed = 'completed';

    case Cancelled = 'cancelled';

    /** Slot held but the buyer never paid and the hold ran out. */
    case Expired = 'expired';

    case NoShow = 'no_show';

    /**
     * Whether the slot is still spoken for. An expired pending booking releases it.
     */
    public function holdsSlot(): bool
    {
        return match ($this) {
            self::PendingPayment => true,
            self::Confirmed, self::Completed => true,
            self::Cancelled, self::Expired, self::NoShow => false,
        };
    }

    public function isPending(): bool
    {
        return $this === self::PendingPayment;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::Completed, self::Cancelled, self::NoShow],
            self::Completed, self::Cancelled, self::Expired, self::NoShow => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Does this booking earn the seller money? A completed appointment is money
     * actually earned; a cancelled or expired hold is not.
     */
    public function countsTowardsLifetimeValue(): bool
    {
        return in_array($this, [self::Confirmed, self::Completed], true);
    }

    public function notifiesBuyer(): bool
    {
        return in_array($this, [self::Confirmed, self::Completed, self::Cancelled, self::NoShow], true);
    }

    /**
     * Statuses that still occupy a slot and are still ahead of us.
     *
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return [self::PendingPayment->value, self::Confirmed->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::NoShow => 'No-show',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingPayment => 'bg-amber-100 text-amber-800',
            self::Confirmed => 'bg-sky-100 text-sky-800',
            self::Completed => 'bg-emerald-100 text-emerald-800',
            self::Cancelled => 'bg-slate-100 text-slate-600',
            self::Expired => 'bg-slate-100 text-slate-500',
            self::NoShow => 'bg-red-100 text-red-700',
        };
    }
}
