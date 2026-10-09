<?php

namespace App\Domains\Payments\Services\Gateways;

use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PaystackGateway implements PaymentGatewayInterface
{
    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = []): array
    {
        $data = $this->http()->post('/transaction/initialize', [
            'reference' => $reference,
            'amount' => $amountKobo,
            'email' => $email,
            'callback_url' => $callbackUrl,
            'metadata' => $meta,
        ])->throw()->json('data');

        return ['authorization_url' => $data['authorization_url']];
    }

    public function verify(string $reference): array
    {
        $data = $this->http()->get("/transaction/verify/{$reference}")->throw()->json('data');

        return [
            'status' => $data['status'],
            'amount_kobo' => (int) $data['amount'],
            'paid_at' => $data['paid_at'] ?? null,
            'raw' => $data,
        ];
    }

    public function refund(string $reference, ?int $amountKobo = null): array
    {
        $payload = ['reference' => $reference];

        if ($amountKobo !== null) {
            $payload['amount'] = $amountKobo;
        }

        $data = $this->http()->post('/refund', $payload)->throw()->json('data');

        return [
            'success' => ($data['status'] ?? null) === 'success',
            'gateway_reference' => (string) ($data['reference'] ?? $data['id'] ?? ''),
            'raw' => $data,
        ];
    }

    public function transfer(string $recipientCode, int $amountKobo, string $reference): array
    {
        $data = $this->http()->post('/transfer', [
            'recipient' => $recipientCode,
            'amount' => $amountKobo,
            'reference' => $reference,
        ])->throw()->json('data');

        return [
            'success' => ($data['status'] ?? null) === 'success',
            'gateway_reference' => (string) ($data['reference'] ?? ''),
            'raw' => $data,
        ];
    }

    public function resolveAccount(string $bankCode, string $accountNumber): array
    {
        $data = $this->http()->get('/bank/resolve', [
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
        $data = $this->http()->post('/transfer/transferrecipient', [
            'type' => 'nuban',
            'name' => $accountName,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ])->throw()->json('data');

        return (string) $data['recipient_code'];
    }

    /**
     * Signature the gateway sends with webhook deliveries.
     */
    public function sign(string $payload): string
    {
        return hash_hmac('sha512', $payload, (string) config('services.paystack.secret'));
    }

    private function http(): PendingRequest
    {
        return Http::withToken((string) config('services.paystack.secret'))
            ->baseUrl('https://api.paystack.co')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300);
    }
}
