<?php

namespace App\Shared\Support;

/**
 * The gateway event names this application reacts to.
 *
 * Gateway events arrive as free strings, so they are gathered here rather than
 * spelled out at each call site: a typo in a match arm fails silently, and a
 * subscription that is never cancelled is a far worse bug than a failed request.
 */
final class GatewayWebhookEvent
{
    public const CHARGE_SUCCESS = 'charge.success';

    public const SUBSCRIPTION_CREATE = 'subscription.create';

    public const SUBSCRIPTION_NOT_RENEW = 'subscription.not_renew';

    public const SUBSCRIPTION_DISABLE = 'subscription.disable';

    public const SUBSCRIPTION_EXPIRING_CARDS = 'subscription.expiring_cards';

    public const INVOICE_PAYMENT_FAILED = 'invoice.payment_failed';

    public const INVOICE_UPDATE = 'invoice.update';
}
