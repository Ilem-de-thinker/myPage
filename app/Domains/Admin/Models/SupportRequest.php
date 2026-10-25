<?php

namespace App\Domains\Admin\Models;

use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Database\Factories\Domains\Admin\Models\SupportRequestFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportRequest extends Model
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SupportRequest $supportRequest): void {
            $supportRequest->ticket_number ??= 'MP-'.Str::upper(Str::random(10));
        });
    }

    protected static function newFactory(): Factory
    {
        return SupportRequestFactory::new();
    }

    protected $fillable = [
        'seller_id',
        'store_page_id',
        'source',
        'category',
        'subject',
        'body',
        'requester_name',
        'requester_contact',
        'status',
        'admin_note',
        'resolved_at',
        'access_token_hash',
    ];

    protected $attributes = [
        'status' => SupportRequestStatus::Open,
    ];

    protected function casts(): array
    {
        return [
            'status' => SupportRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->oldest();
    }
}
