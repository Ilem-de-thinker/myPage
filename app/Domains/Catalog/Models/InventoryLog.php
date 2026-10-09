<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    protected $fillable = ['product_id', 'variant_id', 'delta', 'reason', 'ref_type', 'ref_id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
