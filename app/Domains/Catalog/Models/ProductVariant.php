<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'options', 'price_kobo', 'stock_qty', 'sku', 'image_path'];

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
        // Every variant is sellable on its own, so it always needs a SKU.
        static::creating(function (ProductVariant $variant) {
            $variant->sku = filled($variant->sku) ? $variant->sku : self::generateSku();
        });
    }

    /**
     * SQLite cannot add a unique index to an existing table without rebuilding
     * it, so uniqueness is enforced here and by the form request validation.
     */
    public static function isSkuTaken(string $sku, ?int $ignoreId = null): bool
    {
        return self::withoutGlobalScopes()
            ->where('sku', $sku)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public static function generateSku(string $prefix = 'VAR'): string
    {
        do {
            $sku = $prefix.'-'.strtoupper(Str::random(8));
        } while (self::isSkuTaken($sku));

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
