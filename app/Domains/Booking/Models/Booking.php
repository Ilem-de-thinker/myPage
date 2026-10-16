<?php

namespace App\Domains\Booking\Models;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Customers\Models\Customer;
use App\Domains\Payments\Models\Transaction;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Booking\Models\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/** @use HasFactory<BookingFactory> *
 * @property BookingStatus $status
 */
class Booking extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'service_id',
        'staff_member_id',
        'customer_id',
        'reference',
        'starts_at',
        'ends_at',
        'status',
        'price_kobo',
        'deposit_kobo',
        'expires_at',
        'reminded_24h_at',
        'reminded_2h_at',
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'reminded_24h_at' => 'datetime',
            'reminded_2h_at' => 'datetime',
            'price_kobo' => 'integer',
            'deposit_kobo' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'payable');
    }

    public function history(): HasMany
    {
        // created_at only has second precision, so id breaks ties when a status
        // changes twice within the same second.
        return $this->hasMany(BookingStatusHistory::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function scopeForPage(Builder $query, int $storePageId): Builder
    {
        return $query->where('store_page_id', $storePageId);
    }

    /**
     * Narrow to whoever the slot is being checked for.
     *
     * With nobody chosen this means the shop as a whole: a booking held by one
     * person does not close the shop, it only removes that person. Without this,
     * one busy stylist would make the whole day look closed.
     */
    public function scopeScopedTo(Builder $query, ?StaffMember $staff): Builder
    {
        return $query->when(
            $staff,
            fn (Builder $query) => $query->where('staff_member_id', $staff->getKey()),
            fn (Builder $query) => $query->whereNull('staff_member_id'),
        );
    }

    /**
     * Bookings that still occupy their slot: confirmed ones, plus pending holds
     * whose payment window has not run out.
     */
    public function scopeOccupyingSlot(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', BookingStatus::Confirmed->value)
                ->orWhere('status', BookingStatus::Completed->value)
                ->orWhere(function (Builder $query) {
                    $query->where('status', BookingStatus::PendingPayment->value)
                        ->where('expires_at', '>', now());
                });
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'BK'.Str::upper(Str::random(8));
        } while (self::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * What the buyer owes to secure this booking. A free service owes nothing.
     */
    public function amountDueKobo(): int
    {
        return $this->deposit_kobo ?: $this->price_kobo;
    }
}
