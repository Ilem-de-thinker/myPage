<?php

namespace App\Domains\Payments\Services\Gateways;

use App\Domains\Payments\Data\GatewayCredentials;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PaystackGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly GatewayCredentials $credentials) {}

    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = [], array $options = []): array
    {
        $data = $this->http()->post('/transaction/initialize', [
            'reference' => $reference,
            'amount' => $amountKobo,
            'email' => $email,
            'callback_url' => $callbackUrl,
            'metadata' => $meta,
            // A plan makes this a subscription signup: the gateway charges the
            // plan's own amount instead of $amountKobo, then bills the card on
            // its own from the next cycle onwards.
            ...$this->subscriptionOptions($options),
        ])->throw()->json('data');

        return ['authorization_url' => $data['authorization_url']];
    }

    public function createPlan(string $name, string $interval, int $amountKobo, array $options = []): array
    {
        $data = $this->http()->post('/plan', [
            'name' => $name,
            'interval' => $this->gatewayInterval($interval),
            'amount' => $amountKobo,
            ...$options,
        ])->throw()->json('data');

        return [
            'plan_code' => (string) $data['plan_code'],
            'raw' => $data,
        ];
    }

    public function fetchPlan(string $planCode): array
    {
        $data = $this->http()->get('/plan/'.rawurlencode($planCode))->throw()->json('data');

        return [
            'name' => (string) $data['name'],
            'amount_kobo' => isset($data['amount']) ? (int) $data['amount'] : null,
            'interval' => (string) $data['interval'],
            'raw' => $data,
        ];
    }

    public function updatePlan(string $planCode, string $name, string $interval, int $amountKobo, bool $updateExistingSubscriptions = false): array
    {
        $response = $this->http()->put('/plan/'.rawurlencode($planCode), [
            'name' => $name,
            'interval' => $this->gatewayInterval($interval),
            'amount' => $amountKobo,
            // Always sent, never omitted: omitting it means "re-price everyone
            // currently subscribed", which is the gateway's default and never
            // what a price edit should do implicitly.
            'update_existing_subscriptions' => $updateExistingSubscriptions,
        ])->throw();

        // "Plan updated. 3 subscription(s) affected" — the count the operator
        // needs to know whether a change hit people who never agreed to it.
        return [
            'affected_subscriptions' => $this->affectedCount((string) $response->json('message')),
            'raw' => (array) $response->json('data'),
        ];
    }

    private function affectedCount(string $message): ?int
    {
        return preg_match('/(\d+)\s+subscription/', $message, $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    public function createSubscription(string $customerCode, string $planCode, array $options = []): array
    {
        $data = $this->http()->post('/subscription', [
            'customer' => $customerCode,
            'plan' => $planCode,
            ...$options,
        ])->throw()->json('data');

        return $this->normalizeSubscription($data);
    }

    public function fetchSubscription(string $subscriptionCode): array
    {
        $data = $this->http()->get('/subscription/'.rawurlencode($subscriptionCode))
            ->throw()
            ->json('data');

        return $this->normalizeSubscription($data);
    }

    public function disableSubscription(string $subscriptionCode): array
    {
        $response = $this->http()->get('/subscription/'.rawurlencode($subscriptionCode).'/disable')->throw();

        return ['success' => (bool) $response->json('status'), 'raw' => (array) $response->json('data')];
    }

    public function enableSubscription(string $subscriptionCode): array
    {
        $response = $this->http()->get('/subscription/'.rawurlencode($subscriptionCode).'/enable')->throw();

        return ['success' => (bool) $response->json('status'), 'raw' => (array) $response->json('data')];
    }

    public function manageLink(string $subscriptionCode): string
    {
        $data = $this->http()
            ->get("/subscription/{$subscriptionCode}/manage/link")
            ->throw()
            ->json('data');

        return (string) $data['link'];
    }

    /**
     * Paystack counts the year as `annually` and the six months as
     * `biannually`; this application says `yearly`. Translated in one place so
     * a plan can be written the way the product talks about it.
     */
    private function gatewayInterval(string $interval): string
    {
        return match ($interval) {
            'yearly', 'annually' => 'annually',
            'biannually' => 'biannually',
            default => $interval,
        };
    }

    /**
     * Only forward the options a plan signup actually understands, so a caller
     * cannot accidentally smuggle something else into the initialize payload.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function subscriptionOptions(array $options): array
    {
        return array_intersect_key($options, array_flip(['plan']));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subscription_code: string, customer_code: ?string, email_token: ?string, status: string, next_payment_date: ?string, raw: array<string, mixed>}
     */
    private function normalizeSubscription(array $data): array
    {
        return [
            'subscription_code' => (string) $data['subscription_code'],
            'customer_code' => isset($data['customer']['customer_code'])
                ? (string) $data['customer']['customer_code']
                : null,
            'email_token' => isset($data['email_token']) ? (string) $data['email_token'] : null,
            'status' => (string) ($data['status'] ?? ''),
            'next_payment_date' => $data['next_payment_date'] ?? null,
            'raw' => $data,
        ];
    }

    public function verify(string $reference): array
    {
        $data = $this->http()->get('/transaction/verify/'.rawurlencode($reference))->throw()->json('data');

        return [
            'status' => (string) ($data['status'] ?? ''),
            'amount_kobo' => isset($data['amount']) ? (int) $data['amount'] : null,
            'currency' => $data['currency'] ?? null,
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
        return hash_hmac('sha512', $payload, $this->credentials->webhookSecret ?? $this->credentials->secretKey);
    }

    /**
     * Three attempts, backing off 250ms then 500ms.
     *
     * A flat 300ms retry is no retry at all: a name that fails to resolve or a
     * socket that refuses is still in the same state 300ms later, so the
     * attempts burn the buyer's time to arrive at the same answer.
     *
     * connectTimeout is separate from timeout on purpose. A blackholed host used
     * to hang for the full 20s on every attempt — about a minute of the buyer
     * staring at a spinner — and now gives up in 5. Three attempts plus the two
     * backoffs is the ceiling of about 16s, and the common case (DNS refusing)
     * fails in well under a second.
     */
    private function http(): PendingRequest
    {
        return Http::withToken($this->credentials->secretKey)
            ->baseUrl('https://api.paystack.co')
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(3, fn (int $attempt) => 250 * (2 ** ($attempt - 1)))
            ->throw(function (Response $response, RequestException $exception): void {
                if (in_array($response->status(), [401, 403], true) && $this->credentials->sellerId !== null) {
                    SellerGateway::markCredentialsBroken($this->credentials->sellerId, $this->credentials->gateway);
                }
            });
    }
}
