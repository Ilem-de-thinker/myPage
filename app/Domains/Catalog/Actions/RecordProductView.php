<?php

namespace App\Domains\Catalog\Actions;

use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Counts one view of a product's detail page.
 *
 * Feeds the "Best Sellers" ordering, which otherwise has nothing to fall back on
 * for a product nobody has bought yet — a product with no order lines and a
 * product nobody wanted are indistinguishable in the order table alone.
 *
 * A raw UPDATE rather than loading and saving the model, deliberately. This runs
 * on a page render, once per visit, against a row the caller has just read and
 * does not need back: a read-modify-write would add a SELECT and a full model
 * hydration to every product page view for a counter nothing reads back that
 * request. It also cannot clobber a price a seller is editing at the same moment,
 * which a model save would.
 *
 * Traffic is not a sale. A popular product is still a popular product; it is just
 * not revenue, and nothing here reads it as such.
 */
class RecordProductView
{
    public function handle(Product $product): void
    {
        DB::table('products')
            ->where('id', $product->getKey())
            ->increment('view_count');
    }
}
