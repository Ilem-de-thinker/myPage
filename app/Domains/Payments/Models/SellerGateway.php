<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Notifications\SellerGatewayBrokenNotification;
use App\Domains\Seller\Models\Seller;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Payments\Models\SellerGatewayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A seller's own gateway credentials.
 *
 * The secret is deliberately absent from $fillable: a request may describe which
 * gateway it is, but only SaveSellerGateway writes the key, after proving it
 * against the gateway. It is also hidden from serialisation, so a controller that
 * hands the model to a view, a response or a queue cannot leak it by accident.
 *
 * @property string $gateway
 * @property string $environment
 * @property string|null $public_key
 * @property string $secret_key_last4
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_failed_at
 * @property Carbon|null $last_webhook_at
 */
class SellerGateway extends Model
{
    /** @use HasFactory<SellerGatewayFactory> */
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'gateway',
        'environment',
        'public_key',
        'secret_key_last4',
        'verified_at',
        'last_failed_at',
    ];

    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'secret_key' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'verified_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'last_webhook_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * What the seller sees after saving: enough to recognise the key, not enough
     * to use it. The prefix is spelled out because test and live keys are
     * otherwise easy to confuse.
     */
    public function maskedSecret(): string
    {
        return "sk_{$this->environment}_••••{$this->secret_key_last4}";
    }

    /**
     * Write the secret. Not an accessor and not fillable: this is the one door
     * into the column, and every caller has to name it.
     */
    public function setSecretKey(#[\SensitiveParameter] string $secretKey): void
    {
        $this->forceFill([
            'secret_key' => $secretKey,
            'secret_key_last4' => mb_substr($secretKey, -4),
        ]);
    }

    public function setWebhookSecret(#[\SensitiveParameter] ?string $webhookSecret): void
    {
        $this->forceFill(['webhook_secret' => $webhookSecret]);
    }

    /**
     * Read the secret for a gateway call.
     *
     * Kept out of an accessor on purpose: `$gateway->secret_key` reads like any
     * other column and invites a Blade or JSON leak, while this name is a
     * reminder at every call site that the value is live.
     */
    public function secretKey(): string
    {
        return (string) $this->secret_key;
    }

    /**
     * The gateway told us the key no longer works (401/403). The seller is
     * notified once per day, so a failing checkout cannot fill a seller's inbox.
     */
    public function markBroken(): void
    {
        $shouldNotify = $this->last_failed_at === null
            || $this->last_failed_at->lt(now()->subDay());

        $this->forceFill(['last_failed_at' => now()])->save();

        if ($shouldNotify) {
            $this->seller()->first()?->notify(new SellerGatewayBrokenNotification(ucfirst($this->gateway)));
        }
    }

    public static function markCredentialsBroken(int $sellerId, string $gateway): void
    {
        $record = self::withoutGlobalScopes()
            ->where('seller_id', $sellerId)
            ->where('gateway', $gateway)
            ->first();

        $record?->markBroken();
    }
}
