<?php

namespace App\Domains\Seller\Observers;

use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Services\SlugResolver;

class StorePageObserver
{
    public function __construct(private SlugResolver $resolver) {}

    public function saved(StorePage $page): void
    {
        $this->resolver->flush($page->slug);
    }

    public function deleted(StorePage $page): void
    {
        $this->resolver->flush($page->slug);
    }
}
