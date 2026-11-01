<?php

namespace App\Domains\Seller\Models;

use Database\Factories\Domains\Seller\Models\DeliveryZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<DeliveryZoneFactory> */
class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_profile_id',
        'name',
        'state',
        'fee_kobo',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'fee_kobo' => 'integer',
            'position' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DeliveryProfile::class, 'delivery_profile_id');
    }

    /**
     * A zero fee is how an area is marked free; there is no second flag that
     * could disagree with the price the buyer is charged.
     */
    public function isFree(): bool
    {
        return $this->fee_kobo === 0;
    }
}
