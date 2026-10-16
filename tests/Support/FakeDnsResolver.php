<?php

namespace Tests\Support;

use App\Domains\Domains\Services\Contracts\DnsResolver;

/**
 * A resolver that answers from a fixed table instead of the network.
 *
 * Verifying a domain is the security boundary of the custom-domain feature: an
 * unproved domain must never be served. That rule has to be provable in tests,
 * so DNS is injected rather than looked up through a global function nobody can
 * intercept from a test.
 */
class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, list<string>> */
    public array $records = [];

    /**
     * @param  array<string, list<string>|string>  $records
     */
    public static function for(array $records): self
    {
        $resolver = new self;

        foreach ($records as $host => $targets) {
            $resolver->records[$host] = is_string($targets) ? [$targets] : $targets;
        }

        return $resolver;
    }

    /**
     * @return list<string>
     */
    public function cnameTargets(string $host): array
    {
        return $this->records[rtrim($host, '.')] ?? [];
    }
}
