<?php

namespace App\Domains\Admin\Models;

use App\Domains\Booking\Models\Service;
use App\Domains\Catalog\Models\Product;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_page_id',
        'product_id',
        'service_id',
        'category',
        'body',
        'reporter_name',
        'reporter_contact',
        'status',
        'resolved_by_seller_id',
        'resolved_at',
        'admin_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'resolved_by_seller_id');
    }
}
