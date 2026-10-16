<?php

namespace App\Domains\Payments\Services\Gateways;

use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The other gateway this application can be pointed at.
 *
 * Recurring billing is not implemented here: Flutterwave's card mandates are a
 * different product from Paystack's plans, and guessing at them would be worse
 * than saying so. A plan with no gateway code behind it therefore falls back to
 * charging one period at a time by hand, which is what BillingController does
 * whenever no plan code is configured.
 */
class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = [], array $options = []): array
    {
        $data = $this->http()->post('/payments', [
            'tx_ref' => $reference,
            'amount' => $amountKobo / 100,
            'currency' => 'NGN',
            'redirect_url' => $callbackUrl,
            'customer' => ['email' => $email],
            'customizations' => ['title' => $meta['store_name'] ?? 'Checkout'],
            'meta' => $meta,
        ])->throw()->json('data');

        return ['authorization_url' => $data['link']];
    }

    public function verify(string $reference): array
    {
        $data = $this->http()->get("/transactions/{$reference}/verify")->throw()->json('data');

        return [
            'status' => ($data['status'] ?? '') === 'successful' ? 'success' : ($data['status'] ?? 'failed'),
            'amount_kobo' => (int) round(((float) ($data['amount'] ?? 0)) * 100),
            'paid_at' => $data['created_at'] ?? null,
            'raw' => $data,
        ];
    }

    public function refund(string $reference, ?int $amountKobo = null): array
    {
        $data = $this->http()->post('/refunds', [
            'idempotency_key' => $reference,
            'tx_ref' => $reference,
            ...($amountKobo !== null ? ['amount' => $amountKobo / 100] : []),
        ])->throw()->json('data');

        return [
            'success' => ($data['status'] ?? '') === 'successful',
            'gateway_reference' => (string) ($data['id'] ?? ''),
            'raw' => $data,
        ];
    }

    public function transfer(string $recipientCode, int $amountKobo, string $reference): array
    {
        $data = $this->http()->post('/transfers', [
            'transfer_code' => $reference,
            'amount' => $amountKobo / 100,
            'currency' => 'NGN',
            'destination' => $recipientCode,
        ])->throw()->json('data');

        return [
            'success' => ($data['status'] ?? '') === 'successful',
            'gateway_reference' => (string) ($data['id'] ?? ''),
            'raw' => $data,
        ];
    }

    public function resolveAccount(string $bankCode, string $accountNumber): array
    {
        $data = $this->http()->post('/account-banks', [
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
        ])->throw()->json('data');

        return [
            'account_name' => (string) $data['account_name'],
            'raw' => $data,
        ];
    }

    public function resolveRecipient(string $bankCode, string $accountNumber, string $accountName): string
    {
        $data = $this->http()->post('/transferrecipients', [
            'type' => 'nuban',
            'name' => $accountName,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ])->throw()->json('data');

        return (string) $data['id'];
    }

    /**
     * Flutterwave signs with its own secret rather than the API secret.
     */
    public function sign(string $payload): string
    {
        $hash = hash_hmac('sha256', $payload, (string) config('services.flutterwave.secret_hash'));

        return is_string($hash) ? $hash : '';
    }

    /**
     * Unimplemented recurring billing. Failing loudly matters more than a quiet
     * no-op: BillingController only reaches these when a plan code is actually
     * configured, and a seller who was told a card would renew on its own must
     * never be told that quietly.
     */
    public function createPlan(string $name, string $interval, int $amountKobo, array $options = []): array
    {
        throw $this->unsupported();
    }

    public function fetchPlan(string $planCode): array
    {
        throw $this->unsupported();
    }

    public function updatePlan(string $planCode, string $name, string $interval, int $amountKobo, bool $updateExistingSubscriptions = false): array
    {
        throw $this->unsupported();
    }

    public function createSubscription(string $customerCode, string $planCode, array $options = []): array
    {
        throw $this->unsupported();
    }

    public function fetchSubscription(string $subscriptionCode): array
    {
        throw $this->unsupported();
    }

    public function disableSubscription(string $subscriptionCode): array
    {
        throw $this->unsupported();
    }

    public function enableSubscription(string $subscriptionCode): array
    {
        throw $this->unsupported();
    }

    public function manageLink(string $subscriptionCode): string
    {
        throw $this->unsupported();
    }

    private function unsupported(): RuntimeException
    {
        return new RuntimeException('Recurring subscriptions are not implemented for the Flutterwave gateway.');
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.flutterwave.secret'))
            ->baseUrl('https://api.flutterwave.com/v3')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300);
    }
}
