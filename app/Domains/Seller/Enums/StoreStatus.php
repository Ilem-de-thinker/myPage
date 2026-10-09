<?php

namespace App\Domains\Seller\Enums;

enum StoreStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Suspended = 'suspended';
}
