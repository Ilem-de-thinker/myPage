<?php

namespace App\Domains\Seller\Actions;

use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Events\StorePageUnpublished;
use App\Domains\Seller\Models\StorePage;

/**
 * Takes a page back off the internet. Existing orders and the customer book are
 * untouched: this only hides the storefront from new visitors.
 */
class UnpublishStorePage
{
    public function handle(StorePage $page): StorePage
    {
        // Deliberately leaves published_at alone, so the seller can see how long
        // the page had been live when they bring it back.
        $page->update(['status' => StoreStatus::Draft]);

        event(new StorePageUnpublished($page));

        return $page;
    }
}
