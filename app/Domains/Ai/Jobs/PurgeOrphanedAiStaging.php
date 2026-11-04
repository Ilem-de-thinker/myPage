<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Services\AiImageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Staging originals are deleted when a draft is applied or discarded. Drafts
 * abandoned before either step keep their files, so this sweep is the backstop.
 */
class PurgeOrphanedAiStaging implements ShouldQueue
{
    use Queueable;

    public function handle(AiImageService $images): int
    {
        return $images->purgeOrphanedStaging();
    }
}
