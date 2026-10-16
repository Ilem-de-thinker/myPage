<?php

namespace App\Domains\Domains\Services\Resolvers;

use App\Domains\Domains\Services\Contracts\DnsResolver;

/**
 * The real resolver, on top of the platform's DNS functions.
 *
 * Suppresses warnings because a lookup for a hostname that does not exist is an
 * expected outcome here, not a programming error, and a warning per check would
 * flood the log on every verification sweep.
 */
class NativeDnsResolver implements DnsResolver
{
    /**
     * @return list<string>
     */
    public function cnameTargets(string $host): array
    {
        $targets = $this->lookup($host);

        // A CNAME may point at another CNAME before it reaches us. Following one
        // hop is enough in practice and keeps this from becoming an open resolver.
        foreach ($targets as $target) {
            $targets = array_merge($targets, $this->lookup($target));
        }

        return array_values(array_unique($targets));
    }

    /**
     * @return list<string>
     */
    private function lookup(string $host): array
    {
        if ($host === '') {
            return [];
        }

        $records = @dns_get_record(rtrim($host, '.'), DNS_CNAME);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record) => rtrim((string) ($record['target'] ?? ''), '.'),
            $records,
        )));
    }
}
