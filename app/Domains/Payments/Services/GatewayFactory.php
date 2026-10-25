<?php

namespace App\Domains\Payments\Services;

use App\Domains\Payments\Data\GatewayCredentials;
use App\Domains\Payments\Exceptions\GatewayNotConfigured;
use App\Domains\Payments\Models\SellerGateway;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\Gateways\FlutterwaveGateway;
use App\Domains\Payments\Services\Gateways\PaystackGateway;
use App\Domains\Seller\Models\Seller;

/**
 * Which credentials a gateway call runs on.
 *
 * There are two money flows and they never share a key. Subscription billing is
 * ours, so it uses the platform account from config. Buyer payments belong to
 * the seller, so they use that seller's own row — the platform is never the
 * merchant of record for an order.
 */
class GatewayFactory
{
    public function platform(): PaymentGatewayInterface
    {
        $gateway = (string) config('mypage.gateway', 'paystack');

        return $this->make($gateway, GatewayCredentials::platform($gateway));
    }

    public function forSeller(Seller|int $seller): PaymentGatewayInterface
    {
        $sellerId = $seller instanceof Seller ? $seller->getKey() : $seller;

        $records = SellerGateway::withoutGlobalScopes()
            ->where('seller_id', $sellerId)
            ->get();

        // Paystack first when a seller somehow has both. The checkout flow is
        // written for it, and a silent preference is better than breaking a
        // shop because two rows exist.
        $record = $records->firstWhere('gateway', 'paystack') ?? $records->first();

        if ($record === null) {
            throw GatewayNotConfigured::forSeller((int) $sellerId);
        }

        return $this->make($record->gateway, GatewayCredentials::fromModel($record));
    }

    /**
     * The account a transaction was charged on, derived from the transaction
     * itself rather than the request. Webhooks arrive on whichever host the
     * seller configured, so the reference is the only trustworthy link.
     */
    public function forTransaction(Transaction $transaction): PaymentGatewayInterface
    {
        if ($transaction->payable_type === 'subscription') {
            return $this->platform();
        }

        return $this->forSeller((int) $transaction->seller_id);
    }

    /**
     * Webhook deliveries carry a transaction reference but no tenant. Resolve the
     * account from the reference, and fall back to the platform for lifecycle
     * events that have no transaction behind them yet.
     */
    public function forReference(?string $reference): PaymentGatewayInterface
    {
        if (! is_string($reference) || $reference === '') {
            return $this->platform();
        }

        $transaction = Transaction::withoutGlobalScopes()
            ->where('reference', $reference)
            ->first();

        return $transaction ? $this->forTransaction($transaction) : $this->platform();
    }

    public function make(string $gateway, GatewayCredentials $credentials): PaymentGatewayInterface
    {
        return match ($gateway) {
            'flutterwave' => new FlutterwaveGateway($credentials),
            'paystack' => new PaystackGateway($credentials),
            default => throw GatewayNotConfigured::unknown($gateway),
        };
    }
}
