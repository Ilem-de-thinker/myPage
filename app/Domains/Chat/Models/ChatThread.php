<?php

namespace App\Domains\Chat\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ChatThread extends Model
{
    protected $fillable = ['seller_id', 'store_page_id', 'order_id', 'booking_id', 'customer_id', 'subject', 'guest_name', 'guest_email', 'guest_phone', 'guest_token_hash', 'guest_token_expires_at', 'status', 'last_message_at', 'closed_at', 'ai_summary', 'ai_summary_updated_at'];

    protected $hidden = ['guest_token_hash'];

    protected function casts(): array
    {
        return ['guest_token_expires_at' => 'datetime', 'last_message_at' => 'datetime', 'closed_at' => 'datetime', 'ai_summary_updated_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->oldest();
    }

    public function attachments(): HasManyThrough
    {
        return $this->hasManyThrough(ChatAttachment::class, ChatMessage::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function storePage(): BelongsTo
    {
        return $this->belongsTo(StorePage::class);
    }
}
