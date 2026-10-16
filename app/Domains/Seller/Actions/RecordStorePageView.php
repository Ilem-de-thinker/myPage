<?php

namespace App\Domains\Seller\Actions;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;

/**
 * Counts one visit to a storefront.
 *
 * The discovery hub orders unranked businesses by traffic as the last thing
 * before the name, so a page nobody has looked at does not sit above one that has.
 *
 * A raw UPDATE for the same reason as the catalog's RecordProductView: this runs
 * on every page render of every storefront, and a read-modify-write would add a
 * SELECT and a model hydration to each one for a counter nothing reads back in
 * that request.
 *
 * Bots count too. That is accepted deliberately — the counter is a tiebreaker
 * between businesses of otherwise equal reputation, not a measurement, and
 * filtering crawlers out of it costs a user-agent table that would need keeping
 * current for no change in the ordering.
 */
class RecordStorePageView
{
    public function handle(StorePage $page): void
    {
        DB::table('store_pages')
            ->where('id', $page->getKey())
            ->increment('view_count');
    }
}
