<?php

namespace App\Domains\Seller\Models;

use Database\Factories\Domains\Seller\Models\StoreSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<StoreSectionFactory> */
class StoreSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_page_id',
        'page',
        'key',
        'position',
        'is_enabled',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'content' => 'array',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }
}
