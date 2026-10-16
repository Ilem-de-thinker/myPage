<?php

namespace App\Domains\Customers\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Enums\Theme;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Customers\Models\CustomerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer, unique per seller. Created automatically from orders and bookings, so
 * the seller never has to maintain a list by hand.
 *
 * @use HasFactory<CustomerFactory>
 *
 * @property bool $opted_in
 * @property int $order_count
 * @property int $booking_count
 * @property int $lifetime_value_kobo
 */
class Customer extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'identity_key',
        'name',
        'email',
        'phone',
        'opted_in',
        'order_count',
        'booking_count',
        'lifetime_value_kobo',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'opted_in' => 'boolean',
            'order_count' => 'integer',
            'booking_count' => 'integer',
            'lifetime_value_kobo' => 'integer',
            'last_activity_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(function (Builder $query) use ($like, $term) {
            $query->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);

            // Numbers are stored normalised, so a seller typing the local format
            // they know the buyer by would otherwise find nobody.
            if (PhoneNumber::isValidNigerian($term)) {
                $query->orWhere('phone', PhoneNumber::normalize($term));
            }
        });
    }

    /**
     * How a buyer is matched back to an existing record. A phone number is the
     * strongest identity a Nigerian buyer gives us, so it wins; an email is the
     * fallback for the many who order without one.
     *
     * The two are prefixed apart so an email can never collide with a phone.
     */
    public static function identityKey(?string $phone, ?string $email): ?string
    {
        $phone = PhoneNumber::normalize($phone);

        if ($phone !== null && $phone !== '') {
            return 'p:'.$phone;
        }

        $email = $email !== null ? strtolower(trim($email)) : '';

        return $email === '' ? null : 'e:'.$email;
    }

    public function displayName(): string
    {
        return $this->name ?: ($this->email ?: ($this->phone ?: 'Guest'));
    }

    /**
     * How many things this customer has bought through the storefront. The two
     * themes sell different things, so the noun follows whichever page the
     * seller runs.
     *
     * Named to avoid `purchases()`, which Eloquent would try to read as a
     * relationship and throw on.
     */
    public function purchaseCount(): int
    {
        return $this->sellsServices() ? $this->booking_count : $this->order_count;
    }

    public function purchaseNoun(): string
    {
        return $this->sellsServices() ? 'booking' : 'order';
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    /**
     * A seller runs one page, so its theme decides which noun reads correctly.
     * Falls back to orders when no page is loaded, which keeps console contexts
     * from rendering an empty noun.
     */
    private function sellsServices(): bool
    {
        return $this->storePage?->theme === Theme::Service;
    }
}
