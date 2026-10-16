<?php

namespace App\Domains\Seller\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\Service;
use App\Domains\Booking\Models\StaffMember;
use App\Domains\Booking\Models\TimeOff;
use App\Domains\Booking\Models\WorkingHour;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Enums\PriceTier;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Enums\Theme;
use App\Shared\Traits\BelongsToSeller;
use App\Shared\Traits\HasRating;
use Database\Factories\Domains\Seller\Models\StorePageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<StorePageFactory> */
class StorePage extends Model
{
    use BelongsToSeller, HasFactory, HasRating;

    protected $fillable = [
        'seller_id',
        'slug',
        'name',
        'theme',
        'status',
        'tagline',
        'bio',
        'logo_path',
        'cover_path',
        'whatsapp_number',
        'public_email',
        'address',
        'city',
        'state',
        'brand_color',
        'social_links',
        'settings',
        'published_at',
        'is_directory_opted_in',
        'price_tier',
        'rating',
        'review_count',
        'is_featured',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'theme' => Theme::class,
            'status' => StoreStatus::class,
            'settings' => 'array',
            'social_links' => 'array',
            'published_at' => 'datetime',
            'price_tier' => PriceTier::class,
            'rating' => 'float',
            'review_count' => 'integer',
            'is_directory_opted_in' => 'boolean',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Content blocks for this page. Order and visibility come from the theme,
     * not from these rows.
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class);
    }

    public function block(string $page, string $key): ?PageBlock
    {
        return $this->blocks()->where('page', $page)->where('key', $key)->first();
    }

    /**
     * The photograph the home hero stands on, if there is one.
     *
     * The cover photo is a page-wide setting and wins over the hero block's own
     * image, exactly as the hero component resolves it. The navbar asks this
     * question so it can start white on a photograph instead of painting ink
     * text onto something dark.
     */
    public function heroImage(): ?string
    {
        $image = $this->block('home', 'hero')?->field('image');

        return $this->cover_path ?: ($image ?: null);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('position');
    }

    public function staffMembers(): HasMany
    {
        return $this->hasMany(StaffMember::class)->orderBy('name');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(TimeOff::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isPublished(): bool
    {
        return $this->status === StoreStatus::Published;
    }

    /**
     * Products for this page.
     *
     * Not on the page itself before, because nothing that reads it wants the seller's
     * dashboard ordering — every buyer-facing read filtered on active() explicitly.
     * It exists now so the discovery hub can ask a page what it sells without
     * importing Product into a directory query.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    |
    | What the public discovery hub is allowed to show. Both conditions are stated
    | here rather than in a controller: a listing that forgot `is_directory_opted_in`
    | would publish a seller's shop to strangers without them ever having agreed
    | to it, and that omission would look like a normal query rather than a bug.
    |
    */

    /**
     * Published, dated, and the seller has said yes.
     *
     * @param  Builder<StorePage>  $query
     * @return Builder<StorePage>
     */
    public function scopeDiscoverable(Builder $query): Builder
    {
        return $query
            ->where('status', StoreStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('is_directory_opted_in', true);
    }

    /**
     * @param  Builder<StorePage>  $query
     * @return Builder<StorePage>
     */
    public function scopeOfTheme(Builder $query, Theme $theme): Builder
    {
        return $query->where('theme', $theme->value);
    }

    /**
     * @param  Builder<StorePage>  $query
     * @return Builder<StorePage>
     */
    public function scopeFeaturedOnly(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Whether this page belongs in the public hub.
     *
     * Mirrors scopeDiscoverable() for a single already-loaded row, which is what
     * the marquee needs so it can skip a page whose state changed since the
     * columns were read.
     */
    public function isDiscoverable(): bool
    {
        return $this->isPublished()
            && $this->published_at !== null
            && $this->is_directory_opted_in;
    }

    /**
     * City and state on one line, or null when the seller gave neither.
     */
    public function locationLabel(): ?string
    {
        $parts = array_filter([$this->city, $this->state]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
