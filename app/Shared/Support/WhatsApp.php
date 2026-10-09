<?php

namespace App\Shared\Support;

use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\StorePage;

final class WhatsApp
{
    public static function link(StorePage $page, string $message): string
    {
        $number = ltrim((string) $page->whatsapp_number, '+');

        return "https://wa.me/{$number}?text=".rawurlencode($message);
    }

    public static function productLink(StorePage $page, Product $product): string
    {
        $message = "Hello {$page->name}, I'd like to buy:\n\n{$product->name}\nPrice: {$product->priceLabel}\n"
            .'Link: '.route('storefront.product', ['slug' => $page->slug, 'productSlug' => $product->slug]);

        return self::link($page, $message);
    }
}
