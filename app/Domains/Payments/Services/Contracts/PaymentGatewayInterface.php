<?php

namespace App\Domains\Payments\Services\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Start a checkout and return the URL the buyer should be redirected to.
     *
     * Passing a gateway plan is not a discount hint: the gateway charges the
     * plan's own amount in place of $amountKobo and opens a recurring
     * subscription once the charge succeeds.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $options  Gateway-specific extras, e.g. `plan`.
     * @return array{authorization_url: string}
     */
    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = [], array $options = []): array;

    /**
     * Register a recurring plan and return the code subscriptions are created from.
     *
     * @return array{plan_code: string, raw: array<string, mixed>}
     */
    public function createPlan(string $name, string $interval, int $amountKobo, array $options = []): array;

    /**
     * What the gateway currently charges for a plan.
     *
     * Read rather than assumed, because the gateway charges its own amount in
     * place of the one this application sends. A plan whose two disagree bills
     * the wrong sum, and nothing else in the system would notice.
     *
     * @return array{name: string, amount_kobo: int, interval: string, raw: array<string, mixed>}
     */
    public function fetchPlan(string $planCode): array;

    /**
     * Change a plan's price or interval.
     *
     * $updateExistingSubscriptions is the dangerous one. Left true — the
     * gateway's own default when the flag is omitted — a price change re-prices
     * every seller already on the plan at their next cycle. False means current
     * subscribers keep what they agreed to and only new signups see the change,
     * which is why callers must opt in to it explicitly.
     *
     * @return array{affected_subscriptions: ?int, raw: array<string, mixed>}
     */
    public function updatePlan(string $planCode, string $name, string $interval, int $amountKobo, bool $updateExistingSubscriptions = false): array;

    /**
     * Attach a card to a plan. The customer must already have a reusable
     * authorization, which is why a subscription is only ever created off the
     * back of a successful charge.
     *
     * @param  array<string, mixed>  $options
     * @return array{subscription_code: string, customer_code: ?string, email_token: ?string, status: string, next_payment_date: ?string, raw: array<string, mixed>}
     */
    public function createSubscription(string $customerCode, string $planCode, array $options = []): array;

    /**
     * @return array{status: string, next_payment_date: ?string, raw: array<string, mixed>}
     */
    public function fetchSubscription(string $subscriptionCode): array;

    /**
     * Stop the next cycle from being charged, leaving the current one intact.
     *
     * @return array{success: bool, raw: array<string, mixed>}
     */
    public function disableSubscription(string $subscriptionCode): array;

    /**
     * Resume charging a subscription that was disabled.
     *
     * @return array{success: bool, raw: array<string, mixed>}
     */
    public function enableSubscription(string $subscriptionCode): array;

    /**
     * A hosted page where the customer updates their card or cancels, without
     * the card ever touching this application.
     */
    public function manageLink(string $subscriptionCode): string;

    /**
     * Normalise the gateway's view of a transaction.
     *
     * @return array{status: string, amount_kobo: int, paid_at: ?string, raw: array<string, mixed>}
     */
    public function verify(string $reference): array;

    /**
     * @return array{success: bool, gateway_reference: ?string, raw: array<string, mixed>}
     */
    public function refund(string $reference, ?int $amountKobo = null): array;

    /**
     * @return array{success: bool, gateway_reference: ?string, raw: array<string, mixed>}
     */
    public function transfer(string $recipientCode, int $amountKobo, string $reference): array;

    /**
     * Confirm a bank account belongs to the expected name before sending money to it.
     *
     * @return array{account_name: string, raw: array<string, mixed>}
     */
    public function resolveAccount(string $bankCode, string $accountNumber): array;

    /**
     * Turn verified bank details into the opaque recipient handle the transfer
     * endpoint expects.
     */
    public function resolveRecipient(string $bankCode, string $accountNumber, string $accountName): string;

    /**
     * Signature the gateway sends with webhook deliveries, for this account.
     *
     * Each seller has their own secret, so a delivery is only genuine for the
     * account it was addressed to.
     */
    public function sign(string $payload): string;
}
