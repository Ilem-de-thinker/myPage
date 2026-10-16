<?php

namespace App\Domains\Domains\Services\Contracts;

/**
 * Looks up DNS records for a hostname.
 *
 * Behind a contract so the verification logic above it can be tested without a
 * real resolver. Verifying a domain is the whole security boundary of this
 * feature, so the decision logic has to be exercised deliberately rather than
 * only against live DNS.
 */
interface DnsResolver
{
    /**
     * The CNAME targets this hostname resolves to, following one level of chain.
     *
     * An empty array means "no CNAME", which includes a lookup that failed or a
     * hostname with no DNS at all. The caller cannot tell those apart, and does
     * not need to: neither is a reason to serve the domain.
     *
     * @return list<string>
     */
    public function cnameTargets(string $host): array;
}
