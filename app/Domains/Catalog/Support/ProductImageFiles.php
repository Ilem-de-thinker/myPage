<?php

namespace App\Domains\Catalog\Support;

use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Every public-disk file owned by the given products.
 *
 * Deletion is two different things now: a soft delete hides the row and keeps
 * the images for a restore; the 30-day purge is what really removes both. Both
 * paths read the file list from here so they cannot drift apart.
 */
final class ProductImageFiles
{
    /**
     * @param  array<int>  $productIds
     * @return array<int, string>
     */
    public static function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return ProductImage::query()
            ->whereIn('product_id', $productIds)
            ->pluck('path')
            ->merge(
                ProductVariant::query()
                    ->whereIn('product_id', $productIds)
                    ->whereNotNull('image_path')
                    ->pluck('image_path')
            )
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $paths
     */
    public static function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete($paths);
        }
    }
}
