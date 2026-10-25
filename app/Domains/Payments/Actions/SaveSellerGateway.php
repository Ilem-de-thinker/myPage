<?php

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Exceptions\InvalidGatewayCredentials;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Services\VerifyGatewayCredentials;
use App\Domains\Seller\Models\Seller;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of a seller's gateway secret.
 *
 * Order matters: the key is checked against its own prefix, then against the
 * gateway, and only then written. Nothing is stored on a failed proof — a broken
 * key in this table is worse than no key, because the checkout flow would try to
 * use it and fail after the buyer has committed.
 */
class SaveSellerGateway
{
    public function __construct(private VerifyGatewayCredentials $verifier) {}

    public function handle(
        Seller $seller,
        string $gateway,
        #[\SensitiveParameter] string $secretKey,
        #[\SensitiveParameter] ?string $webhookSecret = null,
        ?string $publicKey = null,
    ): SellerGateway {
        $gateway = mb_strtolower(trim($gateway));
        $secretKey = trim($secretKey);

        $environment = $this->environmentFor($gateway, $secretKey);

        $this->verifier->handle($gateway, $secretKey);

        return DB::transaction(function () use ($seller, $gateway, $environment, $secretKey, $webhookSecret, $publicKey): SellerGateway {
            $record = SellerGateway::withoutGlobalScopes()->firstOrNew([
                'seller_id' => $seller->getKey(),
                'gateway' => $gateway,
            ]);

            $record->fill([
                'seller_id' => $seller->getKey(),
                'gateway' => $gateway,
                'environment' => $environment,
                'public_key' => $publicKey,
                'verified_at' => now(),
                'last_failed_at' => null,
            ]);

            $record->setSecretKey($secretKey);

            // A replacement form cannot echo the old webhook secret back, so a
            // blank field on an existing row means "leave it alone", not "erase
            // it". The remove action is how a secret is deliberately cleared.
            if (filled($webhookSecret) || ! $record->exists) {
                $record->setWebhookSecret($webhookSecret);
            }

            $record->save();

            return $record;
        });
    }

    /**
     * The key's own prefix decides the environment.
     *
     * It is never a form field: a request that could claim "live" while sending
     * a test key would make the dashboard lie about which money is moving.
     */
    private function environmentFor(string $gateway, #[\SensitiveParameter] string $secretKey): string
    {
        [$livePrefixes, $testPrefixes] = match ($gateway) {
            'paystack' => [['sk_live_'], ['sk_test_']],
            'flutterwave' => [['FLWSECK-'], ['FLWSECK_TEST-']],
            default => throw InvalidGatewayCredentials::malformed($gateway),
        };

        $isLive = $this->startsWithAny($secretKey, $livePrefixes);
        $isTest = $this->startsWithAny($secretKey, $testPrefixes);

        if (! $isLive && ! $isTest) {
            throw InvalidGatewayCredentials::malformed($gateway);
        }

        $production = app()->isProduction();

        if ($production && ! $isLive) {
            throw InvalidGatewayCredentials::wrongEnvironment($gateway, production: true);
        }

        if (! $production && ! $isTest) {
            throw InvalidGatewayCredentials::wrongEnvironment($gateway, production: false);
        }

        return $isLive ? 'live' : 'test';
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
