<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class StoreEvent extends Model
{
    protected $fillable = ['store_page_id', 'event', 'product_id', 'service_id', 'visitor_hash', 'source', 'device', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
