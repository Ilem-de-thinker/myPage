<?php

namespace App\Domains\Payments\Services\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Start a checkout and return the URL the buyer should be redirected to.
     *
     * @param  array<string, mixed>  $meta
     * @return array{authorization_url: string}
     */
    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = []): array;

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
}
