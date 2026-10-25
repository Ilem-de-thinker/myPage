<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\StoreEvent;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RecordStoreEvent
{
    private const EVENTS = ['view', 'product_view', 'service_view', 'whatsapp_tap', 'enquiry', 'order', 'traffic', 'directory_click'];

    public function handle(StorePage $page, string $event, Request $request, array $metadata = []): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        $userAgent = (string) $request->userAgent();
        $visitorHash = hash('sha256', $request->ip().'|'.$userAgent.'|'.config('app.key'));
        if ($event === 'directory_click' && StoreEvent::query()->where('store_page_id', $page->id)->where('event', $event)->where('visitor_hash', $visitorHash)->where('created_at', '>=', now()->subMinutes(10))->exists()) {
            return;
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
    }

    private function source(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        return $referer ? parse_url($referer, PHP_URL_HOST) : 'direct';
    }
}
