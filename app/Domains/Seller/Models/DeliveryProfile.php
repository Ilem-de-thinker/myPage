<?php

namespace App\Domains\Seller\Models;

use Database\Factories\Domains\Seller\Models\DeliveryProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @use HasFactory<DeliveryProfileFactory> */
class DeliveryProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_page_id',
        'offers_delivery',
        'offers_pickup',
        'offers_home_service',
        'free_over_kobo',
        'radius_km',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'offers_delivery' => 'boolean',
            'offers_pickup' => 'boolean',
            'offers_home_service' => 'boolean',
            'free_over_kobo' => 'integer',
            'radius_km' => 'integer',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class)->orderBy('position');
    }
}
