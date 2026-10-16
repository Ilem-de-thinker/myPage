<?php

namespace App\Domains\Booking\Models;

use App\Domains\Booking\Enums\BillingUnit;
use App\Domains\Booking\Enums\DepositType;
use App\Domains\Booking\Enums\ServiceSort;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use App\Shared\Traits\BelongsToSeller;
use App\Shared\Traits\HasRating;
use Database\Factories\Domains\Booking\Models\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @use HasFactory<ServiceFactory> */
class Service extends Model
{
    use BelongsToSeller, HasFactory, HasRating;

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'name',
        'slug',
        'description',
        'image_path',
        'duration_min',
        'buffer_min',
        'price_kobo',
        'deposit_type',
        'deposit_value',
        'is_active',
        'position',
        'view_count',
        'is_featured',
        'billing_unit',
        'turnaround_label',
        'deliverables',
        'rating',
        'review_count',
    ];

    protected function casts(): array
    {
        return [
            'duration_min' => 'integer',
            'buffer_min' => 'integer',
            'price_kobo' => 'integer',
            'deposit_value' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
            'rating' => 'float',
            'review_count' => 'integer',
            'deposit_type' => DepositType::class,
            'billing_unit' => BillingUnit::class,
            // `array`, not SupportCollection::class. Laravel reads any other class
            // name as a custom cast and calls methods on it — including
            // withoutObjectCaching, which only exists on an Eloquent Collection.
            // An array cast also matches social_links and settings, so a reader
            // of this file sees one way to store json here and not two.
            'deliverables' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            $service->slug = $service->slug ?: static::generateSlug($service->name);
        });
    }

    public static function generateSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'service';

        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (self::withoutGlobalScopes()->where('slug', $slug)->exists());

        return $slug;
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(StaffMember::class, 'service_staff');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The deposit alone, which is 0 when the seller set no deposit. Kept separate
     * from amountDueKobo() so a seller can say "no deposit, pay in full".
     */
    public function depositKobo(): int
    {
        return $this->deposit_type->amountDueKobo($this->price_kobo, $this->deposit_value);
    }

    /**
     * What the buyer actually owes to secure the slot: the deposit if there is one,
     * otherwise the whole price.
     */
    public function amountDueKobo(): int
    {
        return $this->depositKobo() ?: $this->price_kobo;
    }

    public function hasDeposit(): bool
    {
        return $this->depositKobo() > 0;
    }

    public function priceLabel(): string
    {
        return Money::format($this->price_kobo);
    }

    public function durationLabel(): string
    {
        $minutes = $this->duration_min;

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return $minutes < 60 ? "{$minutes} min" : intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    /*
    |--------------------------------------------------------------------------
    | Directory filtering
    |--------------------------------------------------------------------------
    |
    | The service directory's own narrowing, kept beside the store's catalog
    | scopes so the two listings behave identically under a term typed into a
    | search box.
    |
    */

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopePricedBetween(Builder $query, ?int $minKobo, ?int $maxKobo): Builder
    {
        return $query
            ->when($minKobo !== null, fn (Builder $q) => $q->where('price_kobo', '>=', $minKobo))
            ->when($maxKobo !== null, fn (Builder $q) => $q->where('price_kobo', '<=', $maxKobo));
    }

    /**
     * "How long can I spare?" is the first question on a booking page, so it gets
     * its own scope rather than being a `duration_min` where() the caller builds.
     *
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeWithinDuration(Builder $query, ?int $maxMinutes): Builder
    {
        return $query->when(
            $maxMinutes !== null && $maxMinutes > 0,
            fn (Builder $q) => $q->where('duration_min', '<=', $maxMinutes),
        );
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopePricedPer(Builder $query, BillingUnit $unit): Builder
    {
        return $query->where('billing_unit', $unit->value);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeFeaturedOnly(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeSorted(Builder $query, ServiceSort $sort): Builder
    {
        return $sort->apply($query);
    }

    /**
     * The full price with its unit attached: ₦25,000 /project.
     *
     * Falls back to per-project for a row written before billing_unit existed, so
     * an old service never renders with a price and no idea of what it is for.
     */
    public function priceWithUnitLabel(): string
    {
        return $this->priceLabel().' '.$this->billingUnit()->suffix();
    }

    public function billingUnit(): BillingUnit
    {
        return $this->billing_unit ?? BillingUnit::PerProject;
    }

    /**
     * The tick-list on the card, as plain strings.
     *
     * Normalised rather than trusted: whatever is in the json column gets trimmed
     * and emptied entries dropped, because it is seller-authored and is rendered
     * straight into the page.
     *
     * @return list<string>
     */
    public function deliverableList(): array
    {
        return collect($this->deliverables ?? [])
            ->filter(fn ($item): bool => is_string($item))
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->values()
            ->all();
    }
}
