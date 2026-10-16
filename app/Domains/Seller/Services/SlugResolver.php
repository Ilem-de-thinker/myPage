<?php

namespace App\Domains\Seller\Services;

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\Hostname;
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

    /**
     * The shop behind a hostname, when it is one of ours.
     *
     * Only a binding that has been proved by DNS is served. A pending or
     * suspended domain resolves to null, which falls through to a 404 rather
     * than to whoever last claimed it.
     */
    public function resolveByHost(string $host): ?StorePage
    {
        $host = Hostname::withoutWww(Hostname::normalise($host) ?? $host);

        if ($host === '') {
            return null;
        }

        $cached = Cache::get($this->hostKey($host));

        if ($cached instanceof StorePage) {
            return $cached;
        }

        $page = $this->hostQuery($host);

        if ($page instanceof StorePage) {
            Cache::put($this->hostKey($host), $page, self::CACHE_TTL_SECONDS);
        }

        return $page;
    }

    /**
     * Drop the cached host lookup after a binding changes.
     *
     * Called when a domain is verified or suspended. Without this, a seller who
     * drops their plan keeps serving their shop for the length of the cache TTL.
     */
    public function flushHost(string $host): void
    {
        $host = Hostname::withoutWww(Hostname::normalise($host) ?? $host);

        if ($host !== '') {
            Cache::forget($this->hostKey($host));
        }
    }

    public function flush(string $slug): void
    {
        Cache::forget($this->key($slug));
    }

    private function query(string $slug): ?StorePage
    {
        // Blocks are resolved per page by BlockRenderer, so nothing is eager loaded
        // here. Eager loading every block for a slug lookup was loading the
        // content of every page on the site on every request.
        return StorePage::where('slug', $slug)->first();
    }

    private function hostQuery(string $host): ?StorePage
    {
        $binding = DomainBinding::withoutGlobalScopes()
            ->where('host', $host)
            ->where('status', DomainStatus::Active)
            ->first();

        if ($binding === null || $binding->store_page_id === null) {
            return null;
        }

        return StorePage::find($binding->store_page_id);
    }

    private function key(string $slug): string
    {
        return "store:slug:{$slug}";
    }

    private function hostKey(string $host): string
    {
        return "store:host:{$host}";
    }
}
