<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\StoreEvent;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordStoreEvent
{
    private const EVENTS = ['view', 'product_view', 'service_view', 'whatsapp_tap', 'enquiry', 'order', 'traffic', 'directory_click'];

    private const VIEW_EVENTS = ['view', 'product_view', 'service_view'];

    /**
     * Record a storefront event, returning false when it was deliberately skipped.
     *
     * View events are counted once per visitor IP per calendar day. Buyers do not
     * have accounts here, so the IP is the visitor; the hash is still salted so
     * the table never stores a raw address. A seller looking at their own shop is
     * not a visitor at all, so their event and the matching view counter are both
     * skipped.
     */
    public function handle(StorePage $page, string $event, Request $request, array $metadata = []): bool
    {
        if (! in_array($event, self::EVENTS, true)) {
            return false;
        }

        if ((int) $request->user()?->getKey() === (int) $page->seller_id) {
            return false;
        }

        $userAgent = (string) $request->userAgent();
        $visitorHash = hash('sha256', (string) $request->ip().'|'.config('app.key'));
        if ($this->alreadyRecorded($page, $event, $visitorHash, $metadata)) {
            return false;
        }

        StoreEvent::create([
            'store_page_id' => $page->id,
            'event' => $event,
            'product_id' => $metadata['product_id'] ?? null,
            'service_id' => $metadata['service_id'] ?? null,
            'visitor_hash' => $visitorHash,
            'source' => $this->source($request),
            'device' => Str::contains($userAgent, ['Mobile', 'Android', 'iPhone'], true) ? 'mobile' : 'desktop',
            'metadata' => collect($metadata)->except(['product_id', 'service_id'])->all(),
        ]);

        return true;
    }

    private function alreadyRecorded(StorePage $page, string $event, string $visitorHash, array $metadata): bool
    {
        $query = StoreEvent::query()
            ->where('store_page_id', $page->id)
            ->where('event', $event)
            ->where('visitor_hash', $visitorHash);

        if (in_array($event, self::VIEW_EVENTS, true)) {
            $query->where('created_at', '>=', now()->startOfDay());

            if ($event === 'product_view') {
                $query->where('product_id', $metadata['product_id'] ?? null);
            } elseif ($event === 'service_view') {
                $query->where('service_id', $metadata['service_id'] ?? null);
            }

            return $query->exists();
        }

        if ($event === 'directory_click') {
            return $query->where('created_at', '>=', now()->subMinutes(10))->exists();
        }

        return false;
    }

    private function source(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        return $referer ? parse_url($referer, PHP_URL_HOST) : 'direct';
    }
}
