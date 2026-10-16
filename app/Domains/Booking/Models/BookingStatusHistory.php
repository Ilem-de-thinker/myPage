<?php

namespace App\Domains\Booking\Models;

use App\Domains\Booking\Enums\BookingStatus;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors OrderStatusHistory for the booking side of the app.
 */
class BookingStatusHistory extends Model
{
    protected $table = 'booking_status_histories';

    public const UPDATED_AT = null;

    protected $fillable = [
        'booking_id',
        'from_status',
        'to_status',
        'by_seller_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => BookingStatus::class,
            'to_status' => BookingStatus::class,
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bySeller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'by_seller_id');
    }
}
