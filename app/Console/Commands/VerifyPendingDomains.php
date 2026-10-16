<?php

namespace App\Console\Commands;

use App\Domains\Domains\Enums\DomainStatus;
use App\Domains\Domains\Jobs\VerifyDomainDns;
use App\Domains\Domains\Models\DomainBinding;
use Illuminate\Console\Command;

/**
 * Re-checks every domain that is still waiting on DNS.
 *
 * Sellers are not in a hurry when they are pointing a domain at us, and DNS
 * propagation is measured in hours. Polling from the queue on a timer means a
 * seller who set the record and walked away still finds their shop live when
 * they come back, without anybody pressing a button.
 */
class VerifyPendingDomains extends Command
{
    protected $signature = 'domains:verify-pending';

    protected $description = 'Re-check domains whose DNS has not pointed at us yet';

    public function handle(): int
    {
        $pending = DomainBinding::withoutGlobalScopes()
            ->where('status', DomainStatus::Pending)
            ->get();

        if ($pending->isEmpty()) {
            $this->components->info('No domains are waiting on DNS.');

            return self::SUCCESS;
        }

        foreach ($pending as $binding) {
            VerifyDomainDns::dispatch($binding);
        }

        $this->components->info("Queued {$pending->count()} domain(s) for a DNS check.");

        return self::SUCCESS;
    }
}
