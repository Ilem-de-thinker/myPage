<?php

namespace App\Domains\Admin\Jobs;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireKycVerifications implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        StorePage::withoutGlobalScopes()
            ->whereNotNull('verified_at')
            ->whereHas('seller', fn ($query) => $query
                ->whereNotNull('kyc_document_expires_at')
                ->whereDate('kyc_document_expires_at', '<', today()))
            ->update(['verified_at' => null]);
    }
}
