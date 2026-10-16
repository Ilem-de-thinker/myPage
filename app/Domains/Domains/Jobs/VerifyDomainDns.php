<?php

namespace App\Domains\Domains\Jobs;

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Models\DomainBinding;
use App\Domains\Domains\Services\DomainVerifier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-checks one seller's domain.
 *
 * Unique per binding so a seller hammering "check again", or the sweep and a
 * manual check running together, cannot queue the same lookup twice.
 */
class VerifyDomainDns implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * One lookup, so a transient resolver failure should not be retried into a
     * duplicate. The sweep will come back around on its own.
     *
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public int $uniqueFor = 300;

    public function __construct(public DomainBinding $binding) {}

    public function uniqueId(): string
    {
        return (string) $this->binding->getKey();
    }

    public function handle(DomainVerifier $verifier): void
    {
        // Re-read rather than trusting the serialised copy: a seller may have
        // deleted the binding between dispatch and execution.
        $binding = DomainBinding::withoutGlobalScopes()->find($this->binding->getKey());

        if ($binding === null) {
            return;
        }

        $verifier->verify($binding);
    }

    /**
     * Give up quietly.
     *
     * A domain whose DNS never arrives is not an incident, and the seller is
     * told what to do on the page. Retrying forever would just fill the failed
     * jobs table with rows nobody will ever read.
     */
    public function failed(\Throwable $exception): void
    {
        DomainBinding::withoutGlobalScopes()
            ->whereKey($this->binding->getKey())
            ->where('status', '!=', DomainStatus::Active)
            ->update([
                'last_error' => 'We could not check your DNS right now. Try again in a few minutes.',
                'updated_at' => now(),
            ]);
    }
}
