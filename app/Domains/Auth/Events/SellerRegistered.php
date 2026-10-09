<?php

namespace App\Domains\Auth\Events;

use App\Domains\Seller\Models\Seller;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SellerRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public Seller $seller) {}
}
