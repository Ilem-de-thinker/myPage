<?php

namespace App\Domains\Seller\Models;

use App\Domains\Catalog\Models\Category;
use App\Domains\Seller\Enums\StoreStatus;
use App\Domains\Seller\Enums\Theme;
use App\Shared\Traits\BelongsToSeller;
use Database\Factories\Domains\Seller\Models\StorePageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<StorePageFactory> */
class StorePage extends Model
{
    use BelongsToSeller, HasFactory;

    protected $fillable = [
        'seller_id',
        'slug',
        'name',
        'theme',
        'status',
        'tagline',
        'bio',
        'logo_path',
        'cover_path',
        'whatsapp_number',
        'address',
        'city',
        'state',
        'brand_color',
        'font_pair',
        'settings',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'theme' => Theme::class,
            'status' => StoreStatus::class,
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(StoreSection::class)->orderBy('position');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->status === StoreStatus::Published;
    }
}
