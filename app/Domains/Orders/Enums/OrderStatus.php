<?php

namespace App\Domains\Orders\Enums;

/**
 * The lifecycle of an order. Status only ever changes through UpdateOrderStatus,
 * which validates the move against {@see self::canMoveTo()} and writes history.
 */
enum OrderStatus: string
{
    /** Platform order created, waiting for the buyer to pay at checkout. */
    case PendingPayment = 'pending_payment';

    /** WhatsApp order placed, waiting for the seller to confirm they received it. */
    case Pending = 'pending';

    case Paid = 'paid';

    case Processing = 'processing';

    case Shipped = 'shipped';

    case Delivered = 'delivered';

    case Cancelled = 'cancelled';

    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment, self::Pending => [self::Paid, self::Cancelled],
            self::Paid => [self::Processing, self::Cancelled, self::Refunded],
            self::Processing => [self::Shipped, self::Cancelled, self::Refunded],
            self::Shipped => [self::Delivered, self::Refunded],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
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

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Processing => 'Processing',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Tailwind classes for the status pill used across the order screens.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingPayment, self::Pending => 'bg-amber-100 text-amber-800',
            self::Paid, self::Processing => 'bg-sky-100 text-sky-800',
            self::Shipped => 'bg-indigo-100 text-indigo-800',
            self::Delivered => 'bg-emerald-100 text-emerald-800',
            self::Cancelled => 'bg-slate-100 text-slate-600',
            self::Refunded => 'bg-red-100 text-red-700',
        };
    }
}
