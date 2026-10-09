<?php

namespace App\Shared\Support;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\URL;

final class StoreUrl
{
    public static function home(StorePage $page): string
    {
        return route('storefront.show', $page->slug);
    }

    public static function preview(StorePage $page, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('storefront.show', now()->addMinutes($minutes), ['slug' => $page->slug]);
    }

    public static function forSeller(StorePage $page): string
    {
        return $page->isPublished() ? self::home($page) : self::preview($page);
    }
}
