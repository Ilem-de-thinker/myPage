<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'store_page_id', 'name', 'options', 'price_kobo', 'stock_qty', 'sku', 'image_path'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price_kobo' => 'integer',
            'stock_qty' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant) {
            // Denormalised so the composite unique index can scope a SKU to one
            // shop: two sellers may both use "BAG-001", one shop may not.
            $variant->store_page_id ??= $variant->product?->store_page_id;

            // Every variant is sellable on its own, so it always needs a SKU.
            $variant->sku = filled($variant->sku) ? $variant->sku : self::generateSku($variant->store_page_id);
        });
    }

    /**
     * Uniqueness is per store. The database index is the guarantee; this check
     * exists so the seller gets a field error instead of a 500.
     */
    public static function isSkuTaken(string $sku, ?int $storePageId, ?int $ignoreId = null): bool
    {
        return self::withoutGlobalScopes()
            ->where('sku', $sku)
            ->where('store_page_id', $storePageId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public static function generateSku(?int $storePageId = null, string $prefix = 'VAR'): string
    {
        do {
            $sku = $prefix.'-'.strtoupper(Str::random(8));
        } while (self::isSkuTaken($sku, $storePageId));

        return $sku;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }

    public function priceKobo(): ?int
    {
        return $this->price_kobo ?? $this->product?->price_kobo;
    }

    public function label(): string
    {
        return $this->name;
    }
}
