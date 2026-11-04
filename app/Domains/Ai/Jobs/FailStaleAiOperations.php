<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiUsageReservations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A worker killed mid-call leaves its operation "running" forever: the task
 * never completes, the store's active-slot count never drops, and the merchant
 * sees a spinner that will not stop. Mark stale rows failed and release the
 * credit they were holding so the next attempt starts clean.
 */
class FailStaleAiOperations implements ShouldQueue
{
    use Queueable;

    public function handle(AiUsageReservations $reservations): int
    {
        $minutes = max(1, (int) config('ai.limits.operation_stale_minutes', 30));

        $stale = AiOperation::query()
            ->where('status', 'running')
            ->where('started_at', '<', now()->subMinutes($minutes))
            ->get();

        foreach ($stale as $operation) {
            $operation->update([
                'status' => 'failed',
                'error' => 'Mercho stopped responding before this task finished. Retry it to try again.',
                'completed_at' => now(),
            ]);

            $reservations->release($operation);

            if ($operation->ai_task_id !== null) {
                AiTask::find($operation->ai_task_id)?->refreshProgress();
            }
        }

        return $stale->count();
    }
}
