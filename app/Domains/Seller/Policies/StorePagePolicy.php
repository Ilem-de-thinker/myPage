<?php

namespace App\Domains\Seller\Policies;

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;

class StorePagePolicy
{
    public function view(Seller $seller, StorePage $page): bool
    {
        return $page->seller_id === $seller->id;
    }

    public function update(Seller $seller, StorePage $page): bool
    {
        return $page->seller_id === $seller->id;
    }

    public function delete(Seller $seller, StorePage $page): bool
    {
        return $page->seller_id === $seller->id;
    }
}
