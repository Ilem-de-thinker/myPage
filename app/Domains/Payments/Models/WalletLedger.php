<?php

namespace App\Domains\Payments\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One half of a double-entry movement. Rows are immutable: correcting a mistake
 * means writing an offsetting pair, never editing history.
 */
class WalletLedger extends Model
{
    protected $table = 'wallet_ledger';

    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'transaction_id',
        'payout_id',
        'direction',
        'amount_kobo',
        'balance_after_kobo',
        'reason',
        'counterpart_wallet_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'balance_after_kobo' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function counterpart(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'counterpart_wallet_id');
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    /**
     * Signed impact on the wallet, so a full ledger can be checked for zero sum.
     */
    public function signedAmountKobo(): int
    {
        return $this->isCredit() ? $this->amount_kobo : -$this->amount_kobo;
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Wallet ledger rows are immutable.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Wallet ledger rows are immutable.');
        });
    }
}
