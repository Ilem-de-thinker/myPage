<?php

namespace App\Domains\Payments\Models;

use App\Domains\Payments\Enums\TransactionStatus;
use App\Domains\Seller\Models\Seller;
use Database\Factories\Domains\Payments\Models\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** @use HasFactory<TransactionFactory> */
class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'payable_type',
        'payable_id',
        'reference',
        'gateway',
        'amount_kobo',
        'fee_kobo',
        'status',
        'paid_at',
        'gateway_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'amount_kobo' => 'integer',
            'fee_kobo' => 'integer',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }
}
