<?php

namespace App\Domains\Admin\Jobs;

use App\Domains\Admin\Models\BadgeApplication;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredKycDocuments implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        BadgeApplication::query()
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now())
            ->whereNull('document_deleted_at')
            ->chunkById(100, function ($applications): void {
                foreach ($applications as $application) {
                    if (filled($application->document_path)) {
                        Storage::disk('local')->delete($application->document_path);
                    }

                    $application->forceFill(['document_deleted_at' => now()])->save();
                }
            });
    }
}
