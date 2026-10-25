<?php

namespace App\Shared\Support;

use App\Domains\Domains\Models\DomainBinding;

/**
 * Hostnames a seller may not point at their own shop.
 *
 * This is the second half of the security boundary in Hostname: normalisation
 * decides what a host *is*, this decides which hosts we hand out at all.
 *
 * The apex and every sub-subdomain of the platform are off limits, because a
 * seller who served their page from "mypage.ng" would be impersonating the
 * platform and could intercept sign-in and checkout for every other seller.
 */
final class ReservedHostnames
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $apex = strtolower(trim((string) config('mypage.domain')));

        $reserved = [$apex, 'www.'.$apex];

        // Loopback and placeholder names. Deliberately not a TLD list: listing
        // "test" or "invalid" would also refuse an unrelated domain, which is an
        // ordinary domain that happens to sit beside ours rather than under it.
        foreach (['localhost', '127.0.0.1', '0.0.0.0'] as $host) {
            $reserved[] = $host;
        }

        return array_values(array_unique(array_filter($reserved)));
    }

    /**
     * Is this host ours rather than the seller's?
     */
    public static function isReserved(string $host): bool
    {
        $host = Hostname::withoutWww(Hostname::normalise($host) ?? $host);
        $apex = Hostname::withoutWww(strtolower(trim((string) config('mypage.domain'))));

        foreach (self::all() as $reserved) {
            $reserved = Hostname::withoutWww($reserved);

            if ($host === $reserved || str_ends_with($host, '.'.$reserved)) {
                return true;
            }
        }

        // Nothing under the apex may be claimed, whatever the apex is in this
        // environment. Checked separately so the configured development apex is
        // protected on staging too.
        return $host === $apex || str_ends_with($host, '.'.$apex);
    }

    /**
     * Has somebody else already claimed this exact host?
     */
    public static function isTakenByAnotherSeller(string $host, ?int $sellerId = null): bool
    {
        return DomainBinding::withoutGlobalScopes()
            ->where('host', $host)
            ->when($sellerId !== null, fn ($query) => $query->where('seller_id', '!=', $sellerId))
            ->exists();
    }
}
