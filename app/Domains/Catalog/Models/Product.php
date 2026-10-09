<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Money;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Catalog\Models\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** @use HasFactory<ProductFactory> */
class Product extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id', 'store_page_id', 'category_id', 'name', 'slug', 'description',
        'price_kobo', 'compare_at_kobo', 'sku', 'track_stock', 'stock_qty',
        'status', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'price_kobo' => 'integer',
            'compare_at_kobo' => 'integer',
            'track_stock' => 'boolean',
            'is_featured' => 'boolean',
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

    public function getPriceLabelAttribute(): string
    {
        return Money::format($this->price_kobo);
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->images->first();

        return $image ? Storage::url($image->path) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
