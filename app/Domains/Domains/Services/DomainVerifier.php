<?php

namespace App\Domains\Domains\Services;

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Domains\Services\Contracts\DnsResolver;
use App\Domains\Seller\Services\SlugResolver;

/**
 * Checks that a seller's domain actually points at us.
 *
 * Nothing here trusts the seller's word for it. A binding only goes live once
 * DNS for their hostname resolves to the CNAME target we publish in their
 * dashboard, because a domain that has not been proved is a domain we cannot
 * serve: doing so would let anyone who can buy a hostname claim a shop they do
 * not own.
 */
class DomainVerifier
{
    public function __construct(private DnsResolver $dns) {}

    /**
     * Re-check one binding and return where it now stands.
     *
     * A live domain is re-checked too, but only to notice drift: a seller who
     * repoints their DNS should stop being served rather than keep a shop they
     * no longer control.
     */
    public function verify(DomainBinding $binding): DomainStatus
    {
        if ($this->resolvesToUs($binding->host)) {
            if ($binding->isServed()) {
                return DomainStatus::Active;
            }

            $binding->forceFill([
                'status' => DomainStatus::Active,
                'verified_at' => now(),
                'last_error' => null,
            ])->save();

            // A host that has never resolved is cached as a miss. Without this
            // the seller waits out the TTL after their DNS lands.
            app(SlugResolver::class)->flushHost($binding->host);

            return DomainStatus::Active;
        }

        if ($binding->isServed()) {
            $binding->forceFill([
                'status' => DomainStatus::Suspended,
                'last_error' => 'This domain no longer points at us.',
                'ssl_active_at' => null,
            ])->save();

            app(SlugResolver::class)->flushHost($binding->host);

            return DomainStatus::Suspended;
        }

        $binding->forceFill([
            'status' => DomainStatus::Pending,
            'last_error' => "No CNAME for {$binding->host} pointing to {$binding->cnameTarget()} yet.",
        ])->save();

        return DomainStatus::Pending;
    }

    /**
     * Does this hostname resolve to our CNAME target?
     *
     * Compared against the canonical target so "domains.mypage.test" and
     * "domains.mypage.test." both count, and only ours counts.
     */
    public function resolvesToUs(string $host): bool
    {
        $target = rtrim((string) config('mypage.custom_domain.cname_target', 'domains.mypage.test'), '.');

        foreach ($this->dns->cnameTargets($host) as $candidate) {
            if (rtrim($candidate, '.') === $target) {
                return true;
            }
        }

        return false;
    }
}
