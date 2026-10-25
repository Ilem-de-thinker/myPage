<?php

namespace App\Domains\Admin\Models;

use App\Domains\Seller\Models\Seller;
use Database\Factories\Domains\Admin\Models\SupportMessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    /** @use HasFactory<SupportMessageFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return SupportMessageFactory::new();
    }

    protected $fillable = [
        'support_request_id',
        'sender_type',
        'sender_id',
        'body',
        'is_internal',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Seller::class, 'sender_id');
    }
}
