<?php

namespace App\Domains\Billing\Models;

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Billing\Models\EscrowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** @use HasFactory<EscrowFactory> */
class Escrow extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'transaction_id',
        'payable_type',
        'payable_id',
        'amount_kobo',
        'commission_kobo',
        'status',
        'held_at',
        'auto_releases_at',
        'released_at',
        'refunded_at',
        'disputed_by_seller_id',
        'dispute_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => EscrowStatus::class,
            'amount_kobo' => 'integer',
            'commission_kobo' => 'integer',
            'held_at' => 'datetime',
            'auto_releases_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function transaction(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The storefront the money belongs to.
     *
     * Without the scope bypass this is null for anyone who is not the seller:
     * the buyer's escrow page loads the row with withoutGlobalScopes() and then
     * cannot render the shop it is about.
     */
    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class)->withoutGlobalScopes();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * The order or booking the money belongs to.
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeHeld(Builder $query): Builder
    {
        return $query->where('status', EscrowStatus::Held->value);
    }

    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    public function scopeDueForAutoRelease(Builder $query): Builder
    {
        return $query->held()
            ->whereNotNull('auto_releases_at')
            ->where('auto_releases_at', '<=', now());
    }

    public function isHeld(): bool
    {
        return $this->status === EscrowStatus::Held;
    }

    /**
     * The public identifier a buyer uses to confirm delivery. Deliberately a
     * separate token from the order reference, which buyers can guess.
     */
    public function releaseToken(): string
    {
        return substr(hash_hmac('sha256', $this->getKey().$this->payable_id, (string) config('app.key')), 0, 24);
    }
}
