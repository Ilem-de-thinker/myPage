<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiUsageReservations;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Prism\Prism\Exceptions\PrismRateLimitedException;
use Throwable;

class RunAiOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 4;

    public array $backoff = [10, 30, 60, 120];

    public int $timeout = 120;

    public function __construct(public AiOperation $operation) {}

    /**
     * Providers rate-limit by tokens per minute. Serialising AI jobs avoids a
     * batch of photo requests exhausting the quota before the first one lands.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('mercho-provider'))
                ->releaseAfter(5)
                ->expireAfter(300),
        ];
    }

    public function handle(AiManager $ai): void
    {
        // A job that was queued before the merchant cancelled the task can
        // still be picked up by a worker. Cancellation wins.
        $this->operation->refresh();
        if (in_array($this->operation->status, ['cancelled', 'succeeded'], true)) {
            if ($this->operation->status === 'cancelled') {
                app(AiUsageReservations::class)->release($this->operation);
            }

            return;
        }

        $claimed = AiOperation::query()
            ->whereKey($this->operation->getKey())
            ->whereIn('status', ['queued', 'failed'])
            ->update(['status' => 'running', 'started_at' => now(), 'error' => null, 'completed_at' => null]);

        if ($claimed === 0) {
            // Another worker owns this operation, or it reached a terminal
            // state while this job was waiting. Do not create a second draft.
            $this->operation->refresh();
            if ($this->operation->status === 'running') {
                $this->release(10);

                return;
            }

            if (in_array($this->operation->status, ['cancelled', 'failed'], true)) {
                app(AiUsageReservations::class)->release($this->operation);
            }

            return;
        }

        $this->operation->refresh();
        $mediaRelativePaths = array_values(array_filter((array) ($this->operation->input['media_relative_paths'] ?? []), 'is_string'));

        try {
            $page = $this->operation->store_page_id ? StorePage::find($this->operation->store_page_id) : null;
            $input = $this->operation->input ?? [];
            $input['_operation_id'] = $this->operation->getKey();
            $result = $ai->run($this->operation->task, $input, $page);

            // The merchant may have cancelled while the provider was working.
            $this->operation->refresh();
            if ($this->operation->status === 'cancelled') {
                return;
            }

            $output = is_array($result) ? $result : ['text' => $result];
            $output['_media_relative_paths'] = $mediaRelativePaths;
            if (isset($this->operation->input['feature'])) {
                $output['_feature'] = $this->operation->input['feature'];
            }
            if (($this->operation->input['feature'] ?? null) === 'services' && isset($output['title'])) {
                $output['name'] = $output['name'] ?? $output['title'];
            }

            $draft = null;
            if (! ($this->operation->input['_no_draft'] ?? false)) {
                $existingDraftId = $this->operation->output['draft_id'] ?? null;
                $draft = $existingDraftId ? AiDraft::query()->find($existingDraftId) : null;

                if (! $draft) {
                    $previous = AiDraft::query()->where('store_page_id', $this->operation->store_page_id)->where('task', $this->operation->task)->latest('id')->first();
                    $draft = AiDraft::create([
                        'seller_id' => $this->operation->seller_id,
                        'store_page_id' => $this->operation->store_page_id,
                        'ai_task_id' => $this->operation->ai_task_id,
                        'parent_id' => $previous?->parent_id ?: $previous?->id,
                        'task' => $this->operation->task,
                        'version' => ($previous?->version ?? 0) + 1,
                        'status' => 'pending',
                        'payload' => $output,
                    ]);
                }
            }

            $this->operation->update(['status' => 'succeeded', 'output' => [...$output, 'draft_id' => $draft?->id], 'completed_at' => now()]);
            if ($this->operation->ai_task_id) {
                AiTask::find($this->operation->ai_task_id)?->refreshProgress();
            }
        } catch (Throwable $exception) {
            $this->operation->refresh();
            if ($this->operation->status === 'cancelled') {
                app(AiUsageReservations::class)->release($this->operation);

                return;
            }

            $attempts = $this->job?->attempts() ?? $this->tries;

            if ($this->isRetryable($exception) && $attempts < $this->tries) {
                $delay = $this->retryDelay($exception, $attempts);

                // Keep the item queued while the provider recovers. Only the
                // final attempt turns it into a visible failure.
                $this->operation->update(['status' => 'queued', 'error' => $this->friendlyMessage($exception), 'started_at' => null, 'completed_at' => null]);
                if ($this->operation->ai_task_id) {
                    AiTask::find($this->operation->ai_task_id)?->refreshProgress();
                }

                $this->release($delay);

                return;
            }

            app(AiUsageReservations::class)->release($this->operation);
            $this->operation->update(['status' => 'failed', 'error' => $this->friendlyMessage($exception), 'completed_at' => now()]);
            if ($this->operation->ai_task_id) {
                AiTask::find($this->operation->ai_task_id)?->refreshProgress();
            }

            // Deliberately not rethrown: the framework's retry would re-claim
            // this operation (the claim set includes 'failed') and call the
            // provider again for a call that can never succeed. The failure
            // state, the reservation release and the notification are all
            // handled above; report() keeps the error visible in the logs.
            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->operation->refresh();

        if (in_array($this->operation->status, ['cancelled', 'succeeded', 'failed'], true)) {
            if ($this->operation->status !== 'succeeded') {
                app(AiUsageReservations::class)->release($this->operation);
            }

            return;
        }

        app(AiUsageReservations::class)->release($this->operation);
        $this->operation->update([
            'status' => 'failed',
            'error' => $exception ? $this->friendlyMessage($exception) : 'The Mercho task could not be completed.',
            'completed_at' => now(),
        ]);

        if ($this->operation->ai_task_id) {
            AiTask::find($this->operation->ai_task_id)?->refreshProgress();
        }
    }

    private function isRetryable(Throwable $exception): bool
    {
        return $exception instanceof PrismRateLimitedException
            || $exception instanceof ConnectionException
            || $exception instanceof TimeoutExceededException;
    }

    private function friendlyMessage(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof PrismRateLimitedException => 'Mercho is briefly rate limited by the AI provider.',
            $exception instanceof ConnectionException, $exception instanceof TimeoutExceededException => 'Mercho could not reach the AI provider.',
            default => $exception->getMessage(),
        };
    }

    private function retryDelay(Throwable $exception, int $attempts): int
    {
        if ($exception instanceof PrismRateLimitedException) {
            return max(5, (int) ($exception->retryAfter ?? 10)) + 2;
        }

        return (int) ($this->backoff[$attempts - 1] ?? 30);
    }
}
