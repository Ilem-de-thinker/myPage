<?php

namespace App\Domains\Catalog\Jobs;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Support\ProductImageFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Finish the deletion a seller started, after the recovery window.
 *
 * A soft-deleted product keeps its rows and images for 30 days so a mistaken
 * delete can be undone. When the window closes this removes the row for good —
 * which also cascades the image rows — and deletes the files those rows named.
 */
class PurgeDeletedProducts implements ShouldQueue
{
    use Queueable;

    public function handle(): int
    {
        $cutoff = now()->subDays(30);
        $purged = 0;

        Product::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->chunkById(100, function ($products) use (&$purged): void {
                $ids = $products->pluck('id')->all();
                $paths = ProductImageFiles::forProducts($ids);

                Product::onlyTrashed()->whereIn('id', $ids)->forceDelete();
                ProductImageFiles::deleteFiles($paths);

                $purged += count($ids);
            });

        return $purged;
    }
}
