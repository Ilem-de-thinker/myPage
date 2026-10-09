<?php

namespace App\Domains\Seller\Services;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\Cache;

class SlugResolver
{
    private const CACHE_TTL_SECONDS = 300;

    public function resolveBySlug(string $slug): ?StorePage
    {
        $cached = Cache::get($this->key($slug));

        // Only a genuine StorePage may be served from cache. A value written by an
        // older build unserializes as __PHP_Incomplete_Class, and a null result
        // must not become a permanent "not found".
        if ($cached instanceof StorePage) {
            return $cached;
        }

        $page = $this->query($slug);

        if ($page instanceof StorePage) {
            Cache::put($this->key($slug), $page, self::CACHE_TTL_SECONDS);
        } else {
            Cache::forget($this->key($slug));
        }

        return $page;
    }

    public function flush(string $slug): void
    {
        Cache::forget($this->key($slug));
    }

    private function query(string $slug): ?StorePage
    {
        return StorePage::with('sections')->where('slug', $slug)->first();
    }

    private function key(string $slug): string
    {
        return "store:slug:{$slug}";
    }
}
