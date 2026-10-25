<?php

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'task', 'provider', 'model', 'prompt_version', 'status', 'latency_ms', 'tokens_in', 'tokens_out', 'error'];

    protected function casts(): array
    {
        return ['latency_ms' => 'integer', 'tokens_in' => 'integer', 'tokens_out' => 'integer'];
    }
}
