<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Database\Factories\Domains\Billing\Models\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<SubscriptionFactory> */
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'plan_id',
        'status',
        'price_kobo',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancelled_at',
        'grace_ends_at',
        'payment_method',
        'gateway_reference',
        'gateway_subscription_code',
        'gateway_customer_code',
        'email_token',
        'next_payment_date',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'price_kobo' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'next_payment_date' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * A seller runs one page; subscriptions are billed against it.
     */
    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class, 'seller_id', 'seller_id');
    }

    /**
     * What we charge for the current period. Named for InitializePayment, which
     * reads the same attribute off orders and bookings.
     */
    public function getTotalKoboAttribute(): int
    {
        return (int) $this->price_kobo;
    }

    /**
     * The grace period is what stops a lapsed subscription from taking a paying
     * seller's page offline the day a debit fails.
     */
    public function isInGrace(): bool
    {
        return $this->grace_ends_at !== null && $this->grace_ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return match (true) {
            $this->status === SubscriptionStatus::Expired => true,
            $this->status === SubscriptionStatus::Cancelled => true,
            $this->status === SubscriptionStatus::PastDue => ! $this->isInGrace(),
            $this->status === SubscriptionStatus::Trialing => (bool) $this->trial_ends_at?->isPast(),

            // Cancelled part way through a paid period: still allowed until that
            // period runs out, and not one minute longer.
            $this->cancelled_at !== null && (bool) $this->current_period_ends_at?->isPast() => true,

            default => false,
        };
    }

    public function canWrite(): bool
    {
        return $this->status->allowsWrites() && ! $this->isExpired();
    }

    public function daysUntilRenewal(): ?int
    {
        if ($this->current_period_ends_at === null) {
            return null;
        }

        return (int) ceil(now()->diffInDays($this->current_period_ends_at, absolute: false));
    }
}
