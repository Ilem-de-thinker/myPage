<?php

namespace App\Domains\Orders\Enums;

/**
 * Where an order came from, which decides how the seller collects payment for it.
 */
enum OrderSource: string
{
    /** Buyer sent the order over WhatsApp and pays the seller off-platform. */
    case Whatsapp = 'whatsapp';

    /** Buyer paid through mypage.ng, so funds settle through the platform. */
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp orders',
            self::Platform => 'On-platform orders',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Whatsapp => 'WhatsApp',
            self::Platform => 'On-platform',
        };
    }

    public function isPlatformPaid(): bool
    {
        return $this === self::Platform;
    }

    /**
     * Orders placed off-platform start life waiting for the seller to confirm
     * receipt, because no money has moved through us yet.
     *
     * @return list<string>
     */
    public function initialStatus(): string
    {
        return match ($this) {
            self::Whatsapp => OrderStatus::Pending->value,
            self::Platform => OrderStatus::PendingPayment->value,
        };
    }
}
