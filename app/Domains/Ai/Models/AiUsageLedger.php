<?php

namespace App\Domains\Ai\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsageLedger extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'operation_id', 'task', 'status', 'provider', 'model', 'tokens_in', 'tokens_out', 'estimated_cost', 'reserved_at'];

    protected function casts(): array
    {
        return [
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'estimated_cost' => 'decimal:6',
            'reserved_at' => 'datetime',
        ];
    }
}
