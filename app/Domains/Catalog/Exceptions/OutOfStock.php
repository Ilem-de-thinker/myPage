<?php

namespace App\Domains\Catalog\Exceptions;

use App\Domains\Catalog\Models\Product;
use DomainException;

class OutOfStock extends DomainException
{
    public function __construct(public readonly Product $product)
    {
        parent::__construct("{$product->name} does not have enough stock.");
    }
}
