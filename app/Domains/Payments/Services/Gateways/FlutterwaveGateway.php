<?php

namespace App\Domains\Payments\Services\Gateways;

use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class FlutterwaveGateway implements PaymentGatewayInterface
{
    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = []): array
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

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.flutterwave.secret'))
            ->baseUrl('https://api.flutterwave.com/v3')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300);
    }
}
