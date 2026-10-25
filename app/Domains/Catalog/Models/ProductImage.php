<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'thumbnail_path', 'width', 'height', 'processed_at', 'position', 'alt'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::url($this->path);
    }

    public function thumbnailUrl(): string
    {
        return Storage::url($this->thumbnail_path ?: $this->path);
    }
}
