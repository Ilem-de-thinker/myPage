<?php

namespace App\Domains\Chat\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Chat\Models\ChatAudit;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Chat\Models\ChatThread;
use App\Domains\Orders\Models\Order;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ChatService
{
    /**
     * @return array{thread: ChatThread, token: string}
     */
    public function openGuestThread(StorePage $page, array $data): array
    {
        $order = $this->order($page, $data['order_reference'] ?? null, $data['email'] ?? null, $data['phone'] ?? null);
        $booking = $this->booking($page, $data['booking_reference'] ?? null, $data['email'] ?? null, $data['phone'] ?? null);

        $thread = ChatThread::query()->firstOrCreate(
            ['store_page_id' => $page->id, 'order_id' => $order?->id, 'booking_id' => $booking?->id],
            ['seller_id' => $page->seller_id, 'customer_id' => $order?->customer_id ?? $booking?->customer_id, 'subject' => $data['subject'] ?? null, 'guest_name' => $data['name'] ?? null, 'guest_email' => $data['email'] ?? null, 'guest_phone' => $data['phone'] ?? null, 'status' => 'open'],
        );

        $token = Str::random(64);
        $thread->forceFill(['guest_token_hash' => hash('sha256', $token), 'guest_token_expires_at' => now()->addDays(30)])->save();

        return ['thread' => $thread, 'token' => $token];
    }

    public function findByGuestToken(StorePage $page, int $threadId, string $token): ChatThread
    {
        $thread = ChatThread::query()->where('store_page_id', $page->id)->findOrFail($threadId);
        abort_unless($thread->guest_token_expires_at?->isFuture() && hash_equals((string) $thread->guest_token_hash, hash('sha256', $token)), 403);

        return $thread;
    }

    public function send(ChatThread $thread, string $senderType, ?int $senderId, string $body, array $metadata = [], ?UploadedFile $attachment = null): ChatMessage
    {
        $message = $thread->messages()->create(['sender_type' => $senderType, 'sender_id' => $senderId, 'body' => trim($body), 'status' => 'sent', 'metadata' => $metadata]);

        if ($attachment !== null) {
            $message->attachments()->create(['path' => $attachment->store('chat-attachments'), 'mime_type' => $attachment->getMimeType() ?: 'application/octet-stream', 'size' => $attachment->getSize() ?: 0, 'original_name' => $attachment->getClientOriginalName()]);
        }

        $thread->forceFill(['last_message_at' => now()])->save();
        ChatAudit::create(['chat_thread_id' => $thread->id, 'seller_id' => $thread->seller_id, 'actor_type' => $senderType, 'actor_id' => $senderId, 'action' => 'message_sent', 'metadata' => ['message_id' => $message->id]]);

        return $message->load('attachments');
    }

    /** @return array<int, array{label:string,status:string,at:mixed,note:?string,complete:bool}> */
    public function timeline(ChatThread $thread): array
    {
        if ($thread->order_id !== null) {
            return $thread->order?->history->map(fn ($item): array => ['label' => $item->to_status->label(), 'status' => $item->to_status->value, 'at' => $item->created_at, 'note' => $item->note, 'complete' => true])->all() ?? [];
        }

        return $thread->booking?->history->map(fn ($item): array => ['label' => $item->to_status->label(), 'status' => $item->to_status->value, 'at' => $item->created_at, 'note' => $item->note, 'complete' => true])->all() ?? [];
    }

    public function whatsappUrl(ChatThread $thread): ?string
    {
        $phone = $thread->guest_phone ?: $thread->order?->customer_phone ?: $thread->booking?->customer_phone;
        $storePhone = $thread->storePage?->whatsapp_number;
        if (! $phone || ! $storePhone) {
            return null;
        }

        $reference = $thread->order?->reference ?: $thread->booking?->reference;
        $message = 'Hello '.$thread->guest_name.($reference ? ', following up on '.$reference : '').'.';

        return 'https://wa.me/'.preg_replace('/\D+/', '', (string) PhoneNumber::normalize($storePhone)).'?text='.rawurlencode($message);
    }

    private function order(StorePage $page, ?string $reference, ?string $email, ?string $phone): ?Order
    {
        if (! $reference) {
            return null;
        }
        $order = Order::query()->where('store_page_id', $page->id)->where('reference', $reference)->firstOrFail();
        abort_unless($this->matchesContact($order->customer_email, $order->customer_phone, $email, $phone), 403);

        return $order;
    }

    private function booking(StorePage $page, ?string $reference, ?string $email, ?string $phone): ?Booking
    {
        if (! $reference) {
            return null;
        }
        $booking = Booking::query()->where('store_page_id', $page->id)->where('reference', $reference)->firstOrFail();
        abort_unless($this->matchesContact($booking->customer_email, $booking->customer_phone, $email, $phone), 403);

        return $booking;
    }

    private function matchesContact(?string $recordEmail, ?string $recordPhone, ?string $email, ?string $phone): bool
    {
        return ($email && $recordEmail && hash_equals(mb_strtolower($recordEmail), mb_strtolower($email))) || ($phone && $recordPhone && preg_replace('/\D+/', '', $recordPhone) === preg_replace('/\D+/', '', $phone));
    }
}
