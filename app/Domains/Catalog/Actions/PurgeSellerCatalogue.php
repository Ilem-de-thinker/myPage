<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\ProductImageFiles;
use Illuminate\Support\Facades\DB;

/**
 * Hard-delete a seller's catalogue.
 *
 * The 30-day recovery window is for the seller's own mistaken deletes. A
 * deletion request under the NDPA is different: the owner has asked for the
 * data to be gone, so this skips the window and removes every row and image.
 */
final class PurgeSellerCatalogue
{
    public function handle(int $sellerId): int
    {
        $ids = Product::withTrashed()->where('seller_id', $sellerId)->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        $paths = ProductImageFiles::forProducts($ids);

        DB::transaction(function () use ($ids): void {
            Product::withTrashed()->whereIn('id', $ids)->forceDelete();
        });

        ProductImageFiles::deleteFiles($paths);

        return count($ids);
    }
}
