<?php

namespace App\Domains\Billing\Models;

use App\Domains\Billing\Actions\ResolveMerchantStatus;
use App\Domains\Billing\Enums\MerchantStatus;
use App\Domains\Billing\Enums\SubscriptionStatus;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Database\Factories\Domains\Billing\Models\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
        'grace_override_until',
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
            'grace_override_until' => 'datetime',
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
     * The status everything should read: active, grace, expired or cancelled.
     * Stored gateway statuses are history; this is the answer to "can they work".
     */
    public function merchantStatus(): MerchantStatus
    {
        return app(ResolveMerchantStatus::class)->handle($this);
    }

    public function canWrite(): bool
    {
        return $this->merchantStatus()->allowsWrites();
    }

    public function isInGrace(): bool
    {
        return $this->merchantStatus() === MerchantStatus::Grace;
    }

    public function isExpired(): bool
    {
        return $this->merchantStatus() === MerchantStatus::Expired;
    }

    public function graceEndsAt(): ?Carbon
    {
        return app(ResolveMerchantStatus::class)->graceEndsAt($this);
    }

    /**
     * Days left in the current paid period, trial, or grace window.
     */
    public function daysLeft(): ?int
    {
        $until = match ($this->merchantStatus()) {
            MerchantStatus::Active => $this->trial_ends_at?->isFuture()
                ? $this->trial_ends_at
                : $this->current_period_ends_at,
            MerchantStatus::Grace => $this->graceEndsAt(),
            default => null,
        };

        if ($until === null || ! $until->isFuture()) {
            return null;
        }

        return (int) ceil(now()->diffInDays($until));
    }

    public function daysUntilRenewal(): ?int
    {
        if ($this->current_period_ends_at === null) {
            return null;
        }

        return (int) ceil(now()->diffInDays($this->current_period_ends_at, absolute: false));
    }
}
