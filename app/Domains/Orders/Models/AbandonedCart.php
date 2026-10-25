<?php

namespace App\Domains\Orders\Models;

use App\Domains\Orders\Enums\AbandonedCartStatus;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use App\Shared\Support\StoreUrl;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Orders\Models\AbandonedCartFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A cart a buyer started but never paid for. Captured when they leave an email
 * on the checkout page, which is the last moment we still have a way to reach them.
 *
 * @use HasFactory<AbandonedCartFactory>
 *
 * @property AbandonedCartStatus $status
 * @property list<array{name: string, quantity: int, line_kobo: int}> $cart
 */
class AbandonedCart extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'reference',
        'email',
        'phone',
        'cart',
        'total_kobo',
        'status',
        'recovered_order_id',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AbandonedCartStatus::class,
            'cart' => 'array',
            'total_kobo' => 'integer',
            'reminded_at' => 'datetime',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', AbandonedCartStatus::Open->value);
    }

    public function scopeForContact(Builder $query, ?string $email, ?string $phone): Builder
    {
        return $query->where(function (Builder $query) use ($email, $phone) {
            if ($email !== null && $email !== '') {
                $query->whereRaw('LOWER(email) = ?', [Str::lower($email)]);
            }

            if ($phone !== null && $phone !== '') {
                $method = $email !== null && $email !== '' ? 'orWhere' : 'where';

                $query->{$method}('phone', PhoneNumber::normalize($phone));
            }
        });
    }

    /**
     * Where the buyer lands when they follow the reminder. The cart itself lives
     * in their browser, so the link takes them to the cart page to pick it back up.
     */
    public function recoveryUrl(): string
    {
        return $this->storePage
            ? StoreUrl::absolute($this->storePage, 'storefront.cart', ['ref' => $this->reference])
            : route('home');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'AC'.now()->format('ymd').Str::upper(Str::random(4));
        } while (self::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }
}
