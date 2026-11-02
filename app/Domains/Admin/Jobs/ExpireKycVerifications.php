<?php

namespace App\Domains\Admin\Jobs;

use App\Domains\Admin\Models\BadgeApplication;
use App\Domains\Admin\Models\BadgeStatus;
use App\Domains\Admin\Notifications\KycStatusChangedNotification;
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

        BadgeApplication::query()
            ->where('status', BadgeStatus::Approved->value)
            ->whereNotNull('document_expires_at')
            ->whereDate('document_expires_at', '<', today())
            ->chunkById(100, function ($applications): void {
                foreach ($applications as $application) {
                    $application->update(['status' => BadgeStatus::Expired]);
                    $application->storePage?->forceFill(['verified_at' => null])->save();
                    $application->seller?->forceFill(['kyc_verified_at' => null])->save();
                    $application->seller?->notify(new KycStatusChangedNotification($application->id, BadgeStatus::Expired->value));
                }
            });
    }
}
