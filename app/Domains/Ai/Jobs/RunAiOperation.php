<?php

namespace App\Domains\Ai\Jobs;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Models\AiDraft;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunAiOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 90];

    public function __construct(public AiOperation $operation) {}

    public function handle(AiManager $ai): void
    {
        // A job that was queued before the merchant cancelled the task can
        // still be picked up by a worker. Cancellation wins.
        $this->operation->refresh();
        if (in_array($this->operation->status, ['cancelled', 'succeeded'], true)) {
            return;
        }

        $this->operation->update(['status' => 'running', 'started_at' => now()]);
        $mediaPaths = $this->operation->input['media_paths'] ?? (isset($this->operation->input['media_path']) ? [$this->operation->input['media_path']] : []);
        $mediaRelativePaths = $this->operation->input['media_relative_paths'] ?? [];

        try {
            $page = $this->operation->store_page_id ? StorePage::find($this->operation->store_page_id) : null;
            $result = $ai->run($this->operation->task, $this->operation->input ?? [], $page);

            // The merchant may have cancelled while the provider was working.
            $this->operation->refresh();
            if ($this->operation->status === 'cancelled') {
                return;
            }

            $output = is_array($result) ? $result : ['text' => $result];
            $output['_media_relative_paths'] = array_values($mediaRelativePaths);
            if (isset($this->operation->input['feature'])) {
                $output['_feature'] = $this->operation->input['feature'];
            }
            if (($this->operation->input['feature'] ?? null) === 'services' && isset($output['title'])) {
                $output['name'] = $output['name'] ?? $output['title'];
            }

            $draft = null;
            if (! ($this->operation->input['_no_draft'] ?? false)) {
                $previous = AiDraft::query()->where('store_page_id', $this->operation->store_page_id)->where('task', $this->operation->task)->latest('id')->first();
                $draft = AiDraft::create(['seller_id' => $this->operation->seller_id, 'store_page_id' => $this->operation->store_page_id, 'parent_id' => $previous?->parent_id ?: $previous?->id, 'task' => $this->operation->task, 'version' => ($previous?->version ?? 0) + 1, 'status' => 'pending', 'payload' => $output]);
            }

            $this->operation->update(['status' => 'succeeded', 'output' => [...$output, 'draft_id' => $draft?->id], 'completed_at' => now()]);
            if ($this->operation->ai_task_id) {
                AiTask::find($this->operation->ai_task_id)?->refreshProgress();
            }
        } catch (\Throwable $exception) {
            $this->operation->refresh();
            if ($this->operation->status === 'cancelled') {
                return;
            }

            $this->operation->update(['status' => 'failed', 'error' => $exception->getMessage(), 'completed_at' => now()]);
            if ($this->operation->ai_task_id) {
                AiTask::find($this->operation->ai_task_id)?->refreshProgress();
            }
            throw $exception;
        }
    }
}
