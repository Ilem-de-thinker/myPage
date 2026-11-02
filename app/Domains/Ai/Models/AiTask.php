<?php

namespace App\Domains\Ai\Models;

use App\Domains\Ai\Notifications\AiTaskFailedNotification;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiTask extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'type', 'status', 'total', 'completed', 'failed', 'summary', 'started_at', 'completed_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(AiOperation::class, 'ai_task_id');
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class, 'store_page_id');
    }

    public function refreshProgress(): void
    {
        /*
            A cancelled task is a terminal, merchant-chosen state. A queued job
            that was cancelled may still be finishing on the worker; if it
            reports progress afterwards it must not drag the task back to
            "processing" (or worse, "completed"). The task stays cancelled until
            the merchant explicitly retries it.
        */
        if ($this->status === 'cancelled') {
            return;
        }

        $ops = $this->operations()->get();
        $completed = $ops->where('status', 'succeeded')->count();
        $failed = $ops->where('status', 'failed')->count();
        $processing = $ops->whereIn('status', ['queued', 'running'])->count();
        $wasFinal = in_array($this->status, ['completed', 'failed', 'partially_completed'], true);
        $this->completed = $completed;
        $this->failed = $failed;
        if ($processing > 0) {
            $this->status = 'processing';
        } elseif ($failed === 0) {
            $this->status = 'completed';
        } elseif ($completed === 0) {
            $this->status = 'failed';
        } else {
            $this->status = 'partially_completed';
        }
        if ($this->status === 'completed' || $this->status === 'failed' || $this->status === 'partially_completed') {
            $this->completed_at = $this->completed_at ?: now();
        }
        $this->save();

        if (! $wasFinal && $processing === 0 && $failed > 0) {
            $seller = Seller::find($this->seller_id);
            if ($seller) {
                $seller->notify(new AiTaskFailedNotification($this->id, $this->type, $failed, $this->total));
            }
        }
    }
}
