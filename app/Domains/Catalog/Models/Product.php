<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\DeliveryMode;
use App\Domains\Catalog\Enums\ProductSort;
use App\Domains\Directory\Jobs\SyncDirectorySearchIndex;
use App\Domains\Orders\Models\OrderItem;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\HasSalePrice;
use App\Shared\Support\Money;
use App\Shared\Support\SearchQueryParser;
use App\Shared\Traits\BelongsToSeller;
use App\Shared\Traits\HasRating;
use Database\Factories\Domains\Catalog\Models\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<ProductFactory> */
class Product extends Model
{
    use BelongsToSeller, HasFactory, HasRating, HasSalePrice;

    protected static function booted(): void
    {
        static::saved(fn (Product $product): mixed => SyncDirectorySearchIndex::dispatch(self::class, $product->getKey()));
        static::deleted(fn (Product $product): mixed => SyncDirectorySearchIndex::dispatch(self::class, $product->getKey()));
    }

    protected $fillable = [
        'seller_id', 'store_page_id', 'category_id', 'name', 'slug', 'description',
        'price_kobo', 'compare_at_kobo', 'sale_starts_at', 'sale_ends_at', 'sku', 'track_stock', 'stock_qty', 'ai_metadata',
        'status', 'is_featured', 'view_count', 'rating', 'review_count',
        'is_directory_listed', 'delivery_mode', 'delivery_fee_kobo',
    ];

    protected function casts(): array
    {
        return [
            ...$this->salePriceCasts(),
            'price_kobo' => 'integer',
            'track_stock' => 'boolean',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
            'rating' => 'float',
            'review_count' => 'integer',
            'is_directory_listed' => 'boolean',
            'ai_metadata' => 'array',
            'delivery_mode' => DeliveryMode::class,
            'delivery_fee_kobo' => 'integer',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * The lines this product appears on.
     *
     * Read only — nothing writes through it. It exists so ProductSort::Popular can
     * count units sold in a single subquery. OrderItem carries no seller scope of
     * its own, and the join is on product_id, so a seller's sort cannot see
     * another seller's lines.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getPriceLabelAttribute(): string
    {
        return Money::format($this->price_kobo);
    }

    public function getSaleLabelAttribute(): ?string
    {
        return $this->isOnSale() ? 'Sale −'.$this->discountPercent().'%' : null;
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->images->first();

        return $image?->thumbnailUrl();
    }

    /**
     * Kept for callers written before sale windows. A sale that is future or
     * expired is not a discount any more, so this follows isOnSale().
     */
    public function isDiscounted(): bool
    {
        return $this->isOnSale();
    }

    public function isSoldOut(): bool
    {
        return $this->track_stock && $this->stock_qty < 1;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /*
    |--------------------------------------------------------------------------
    | Catalog filtering
    |--------------------------------------------------------------------------
    |
    | Every clause a buyer-facing catalogue can be narrowed by, as a local scope.
    | They are chainable and side-effect free so a caller composes exactly the
    | filters it has rather than passing a whole request into the model.
    |
    */

    /**
     * Free-text match over name, description and SKU.
     *
     * The wildcards inside the term are escaped. An unescaped `%` makes the
     * predicate match every row, which turns the search box into a no-op and
     * looks to a buyer like "my search is broken" rather than "you found
     * everything".
     *
     * @param  Builder<Product>  $query
     */
    /**
     * Every word of the term must appear somewhere in the row, so a title, a
     * description detail, or a SKU can each carry a match on their own.
     * LOWER(...) on both sides keeps the comparison case-insensitive on every
     * engine, not only the ones whose collation happens to ignore case.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        $tokens = SearchQueryParser::tokens($term);

        // A term made only of punctuation (a typed "%") or of single letters
        // tokenises to nothing. Adding no conditions would return the entire
        // catalogue, so an empty token list matches nothing instead.
        if ($term !== '' && $tokens === []) {
            return $query->whereRaw('1 = 0');
        }

        foreach ($tokens as $token) {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $token).'%';

            $query->where(function (Builder $inner) use ($needle): void {
                $inner->whereRaw('LOWER(products.name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(products.description) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(products.sku) LIKE ?', [$needle]);
            });
        }

        return $query;
    }

    /**
     * Restrict to one category, or to any of several.
     *
     * A single id and an array of ids arrive from the same control — the
     * single-select is the array of one — so both are accepted rather than making
     * the caller normalise first.
     *
     * @param  Builder<Product>  $query
     * @param  array<int>|int|string|null  $categories
     */
    public function scopeInCategories(Builder $query, array|int|string|null $categories): Builder
    {
        $ids = collect(is_array($categories) ? $categories : [$categories])
            ->filter(fn ($id): bool => $id !== null && $id !== '')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $query;
        }

        return $query->whereIn('category_id', $ids);
    }

    /**
     * Price band, in kobo, inclusive at both ends.
     *
     * @param  Builder<Product>  $query
     */
    public function scopePricedBetween(Builder $query, ?int $minKobo, ?int $maxKobo): Builder
    {
        return $query
            ->when($minKobo !== null, fn (Builder $q) => $q->where('price_kobo', '>=', $minKobo))
            ->when($maxKobo !== null, fn (Builder $q) => $q->where('price_kobo', '<=', $maxKobo));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSorted(Builder $query, ProductSort $sort): Builder
    {
        return $sort->apply($query);
    }
}
