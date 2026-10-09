<?php

namespace App\Domains\Orders\Models;

use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $table = 'order_status_histories';

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id',
        'from_status',
        'to_status',
        'by_seller_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function bySeller(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'by_seller_id');
    }
}
