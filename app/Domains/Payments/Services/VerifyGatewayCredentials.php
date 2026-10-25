<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Exceptions\GatewayVerificationUnavailable;
use App\Domains\Payments\Exceptions\InvalidGatewayCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Prove a seller's secret key against the gateway before we store it.
 *
 * The key is handed to this class and never logged, queued or put in context.
 * The balance endpoint is used rather than a transaction endpoint because it
 * answers "is this key valid for this account?" without reading or creating
 * anything that belongs to the seller.
 */
class VerifyGatewayCredentials
{
    public function handle(string $gateway, #[\SensitiveParameter] string $secretKey): void
    {
        $endpoint = match ($gateway) {
            'paystack' => 'https://api.paystack.co/balance',
            'flutterwave' => 'https://api.flutterwave.com/v3/balances',
            default => throw InvalidGatewayCredentials::malformed($gateway),
        };

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get($endpoint);
        } catch (ConnectionException) {
            throw GatewayVerificationUnavailable::for($gateway);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw InvalidGatewayCredentials::rejectedBy($gateway);
        }

        if (! $response->successful()) {
            throw GatewayVerificationUnavailable::for($gateway);
        }
    }
}
