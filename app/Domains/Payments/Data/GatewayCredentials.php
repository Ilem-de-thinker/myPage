<?php

namespace App\Domains\Payments\Data;

use App\Domains\Payments\Models\SellerGateway;

/**
 * The credentials a payment call needs, resolved once from the seller's row.
 *
 * Controllers never read the model's key; they pass this object to the gateway
 * work. The secret is marked sensitive so an exception trace does not print it,
 * and the class is deliberately not JsonSerializable.
 */
final readonly class GatewayCredentials
{
    public function __construct(
        public string $gateway,
        public string $environment,
        public ?string $publicKey,
        #[\SensitiveParameter] public string $secretKey,
        #[\SensitiveParameter] public ?string $webhookSecret = null,
        public ?int $sellerId = null,
    ) {}

    public static function fromModel(SellerGateway $gateway): self
    {
        return new self(
            gateway: $gateway->gateway,
            environment: $gateway->environment,
            publicKey: $gateway->public_key,
            secretKey: $gateway->secretKey(),
            webhookSecret: $gateway->webhook_secret,
            sellerId: $gateway->seller_id,
        );
    }

    /**
     * The platform's own account. Only subscriptions use this.
     */
    public static function platform(string $gateway): self
    {
        return new self(
            gateway: $gateway,
            environment: app()->isProduction() ? 'live' : 'test',
            publicKey: config("services.{$gateway}.public"),
            secretKey: (string) config("services.{$gateway}.secret"),
            webhookSecret: config("services.{$gateway}.secret_hash")
                ?? config("services.{$gateway}.secret"),
        );
    }
}
