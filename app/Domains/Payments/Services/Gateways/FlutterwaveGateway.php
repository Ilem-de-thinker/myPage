<?php

namespace App\Domains\Payments\Services\Gateways;

use App\Domains\Payments\Data\GatewayCredentials;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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
    public function __construct(private readonly GatewayCredentials $credentials) {}

    /**
     * Exact decimal → integer kobo: no float rounding drift on odd amounts.
     */
    private static function koboFromAmount(mixed $amount): int
    {
        $formatted = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = explode('.', $formatted);

        return ((int) $whole) * 100 + (int) ($fraction === '' ? '00' : $fraction);
    }

    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = [], array $options = []): array
    {
        $data = $this->http()->post('/payments', [
            'tx_ref' => $reference,
            'amount' => number_format($amountKobo / 100, 2, '.', ''),
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
        $data = $this->http()->get('/transactions/'.rawurlencode($reference).'/verify')->throw()->json('data');

        return [
            'status' => ($data['status'] ?? '') === 'successful' ? 'success' : ($data['status'] ?? 'failed'),
            'amount_kobo' => self::koboFromAmount($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? null,
            'paid_at' => $data['created_at'] ?? null,
            'raw' => $data,
        ];
    }

    public function refund(string $reference, ?int $amountKobo = null): array
    {
        $data = $this->http()->post('/refunds', [
            'idempotency_key' => $reference,
            'tx_ref' => $reference,
            ...($amountKobo !== null ? ['amount' => number_format($amountKobo / 100, 2, '.', '')] : []),
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
            'amount' => number_format($amountKobo / 100, 2, '.', ''),
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
     * Flutterwave does not sign the body. The `verif-hash` header is the static
     * secret the account holder sets in the dashboard, sent verbatim — so this
     * returns that secret and the caller compares it with hash_equals.
     */
    public function sign(string $payload): string
    {
        return (string) ($this->credentials->webhookSecret ?? $this->credentials->secretKey);
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
        return Http::withToken($this->credentials->secretKey)
            ->baseUrl('https://api.flutterwave.com/v3')
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300)
            ->throw(function (Response $response, RequestException $exception): void {
                if (in_array($response->status(), [401, 403], true) && $this->credentials->sellerId !== null) {
                    SellerGateway::markCredentialsBroken($this->credentials->sellerId, $this->credentials->gateway);
                }
            });
    }
}
