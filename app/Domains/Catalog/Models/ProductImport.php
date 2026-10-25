<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImport extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'filename', 'path', 'status', 'imported_count', 'failed_count', 'errors', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['errors' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }
}
