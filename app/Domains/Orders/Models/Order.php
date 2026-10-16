<?php

namespace App\Domains\Orders\Models;

use App\Domains\Billing\Models\Escrow;
use App\Domains\Customers\Models\Customer;
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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'customer_id',
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

    /**
     * The buyer's money parked until they are satisfied, if this order used it.
     *
     * Bypasses the seller scope on purpose: the escrow is about one order, and
     * "one per payable" is enforced by a unique index rather than by who is
     * asking.
     */
    public function escrow(): HasOne
    {
        return $this->hasOne(Escrow::class, 'payable_id')
            ->where('payable_type', $this->getMorphClass())
            ->withoutGlobalScopes();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
