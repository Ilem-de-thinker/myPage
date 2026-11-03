<?php

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDraft extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'ai_task_id', 'parent_id', 'task', 'version', 'status', 'payload', 'approved_at', 'rejected_at', 'reviewed_by', 'applied_type', 'applied_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'version' => 'integer', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AiTask::class, 'ai_task_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->latest('version');
    }
}
