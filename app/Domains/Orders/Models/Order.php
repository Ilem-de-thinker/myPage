<?php

namespace App\Domains\Orders\Models;

use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Traits\BelongsToSeller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @method static \Illuminate\Database\Eloquent\Builder<Order> forSource(\App\Domains\Orders\Enums\OrderSource $source)
 *
 * @property OrderStatus $status
 * @property OrderSource $source
 */
class Order extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'reference',
        'source',
        'status',
        'total_kobo',
        'customer_note',
    ];

    protected function casts(): array
    {
        return [
            'source' => OrderSource::class,
            'status' => OrderStatus::class,
            'total_kobo' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function history(): HasMany
    {
        // created_at only has second precision, so id breaks ties when a status
        // changes twice within the same second.
        return $this->hasMany(OrderStatusHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function transactions(): HasMany
    {
        return $this->morphMany(Transaction::class, 'payable');
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function scopeForSource(Builder $query, OrderSource $source): Builder
    {
        return $query->where('source', $source->value);
    }

    /**
     * Buyer-facing reference. The MP prefix is what the storefront has always
     * put in WhatsApp messages, so sellers keep matching chats to records.
     */
    public static function generateReference(): string
    {
        do {
            $reference = 'MP'.now()->format('ymd').Str::upper(Str::random(4));
        } while (self::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }
}
