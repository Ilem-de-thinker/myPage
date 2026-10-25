<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends Model
{
    protected $fillable = ['chat_thread_id', 'sender_type', 'sender_id', 'body', 'status', 'read_at', 'metadata'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'metadata' => 'array'];
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class);
    }
}
