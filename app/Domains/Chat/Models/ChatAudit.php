<?php

namespace App\Domains\Chat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAudit extends Model
{
    protected $fillable = ['chat_thread_id', 'seller_id', 'actor_type', 'actor_id', 'action', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
