<?php

namespace App\Domains\Seller\Events;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StorePagePublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly StorePage $page) {}
}
