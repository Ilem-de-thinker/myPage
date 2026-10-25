<?php

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;

class AiOperation extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'task', 'status', 'input', 'output', 'error', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['input' => 'array', 'output' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
