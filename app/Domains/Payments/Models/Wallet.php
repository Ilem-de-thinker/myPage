<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\WalletKind;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'kind',
        'seller_id',
        'balance_kobo',
        'pending_kobo',
    ];

    protected function casts(): array
    {
        return [
            'kind' => WalletKind::class,
            'balance_kobo' => 'integer',
            'pending_kobo' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(WalletLedger::class);
    }

    /**
     * A seller's wallet, created on first use.
     */
    public static function forSeller(Seller|int $seller): self
    {
        $sellerId = $seller instanceof Seller ? $seller->getKey() : $seller;

        return static::firstOrCreate(
            ['kind' => WalletKind::Seller->value, 'seller_id' => $sellerId],
            ['balance_kobo' => 0, 'pending_kobo' => 0],
        );
    }

    /**
     * Only a seller's own money is protected from going negative. Platform
     * accounts are allowed to sit at a negative balance because they carry
     * obligations: money sitting at the gateway that is owed out to sellers and
     * commission, or commission owed to the platform.
     */
    public function canOverdraw(): bool
    {
        return $this->kind !== WalletKind::Seller;
    }

    /**
     * A platform account (clearing, commission, payouts). These are singletons:
     * every row of a given kind is the same logical account.
     */
    public static function platform(WalletKind $kind): self
    {
        return static::firstOrCreate(
            ['kind' => $kind->value, 'seller_id' => null],
            ['balance_kobo' => 0, 'pending_kobo' => 0],
        );
    }
}
