<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\PayoutStatus;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    protected $fillable = [
        'seller_id',
        'reference',
        'amount_kobo',
        'status',
        'bank_code',
        'account_number',
        'account_name',
        'gateway',
        'gateway_reference',
        'failure_reason',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_kobo' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedger::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', PayoutStatus::Pending->value);
    }
}
