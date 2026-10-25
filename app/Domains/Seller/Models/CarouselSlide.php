<?php

namespace App\Domains\Seller\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CarouselSlide extends Model
{
    protected $fillable = [
        'store_page_id',
        'headline',
        'subtext',
        'button_label',
        'button_url',
        'image_path',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::url($this->image_path) : null;
    }
}
