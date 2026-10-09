<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Exceptions\OutOfStock;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AdjustStock
{
    public function handle(Product $product, int $delta, string $reason, ?Model $reference = null, ?ProductVariant $variant = null): Product
    {
        return DB::transaction(function () use ($product, $delta, $reason, $reference, $variant) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($locked->track_stock && $locked->stock_qty + $delta < 0) {
                throw new OutOfStock($locked);
            }

            $locked->increment('stock_qty', $delta);

            $locked->inventoryLogs()->create([
                'variant_id' => $variant?->id,
                'delta' => $delta,
                'reason' => $reason,
                'ref_type' => $reference?->getMorphClass(),
                'ref_id' => $reference?->getKey(),
            ]);

            return $locked;
        });
    }
}
