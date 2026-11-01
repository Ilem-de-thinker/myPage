<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Admin\Models\SupportRequest;
use App\Domains\Admin\Models\SupportRequestStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.support', [
            'requests' => SupportRequest::query()
                ->where(function ($query) use ($request): void {
                    $query->where('seller_id', $request->user()->getKey())
                        ->orWhere(function ($storeQuery) use ($request): void {
                            $storeQuery->where('source', 'store')
                                ->whereHas('storePage', fn ($pageQuery) => $pageQuery->where('seller_id', $request->user()->getKey()));
                        });
                })
                ->with('messages.sender')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Request $request, SupportRequest $supportRequest): View
    {
        abort_unless(
            $supportRequest->seller_id === $request->user()->getKey()
                || ($supportRequest->source === 'store' && $supportRequest->storePage?->seller_id === $request->user()->getKey()),
            404,
        );

        $supportRequest->load('messages.sender', 'storePage');

        return view('dashboard.support-show', ['supportRequest' => $supportRequest]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:account,billing,technical,storefront,other'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:20', 'max:4000'],
        ]);

        $page = $request->user()->storePages()->first();

        $ticket = SupportRequest::create([
            ...$data,
            'seller_id' => $request->user()->getKey(),
            'store_page_id' => $page?->getKey(),
            'source' => 'seller',
            'requester_name' => $request->user()->name,
            'requester_contact' => $request->user()->email,
            'status' => SupportRequestStatus::Open,
        ]);

        $ticket->messages()->create([
            'sender_type' => 'seller',
            'sender_id' => $request->user()->getKey(),
            'body' => $data['body'],
        ]);

        return back()->with('status', "Ticket {$ticket->ticket_number} created. mypage.ng support will reply soon.");
    }

    public function reply(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        abort_unless($supportRequest->seller_id === $request->user()->getKey(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ]);

        $supportRequest->messages()->create([
            'sender_type' => 'seller',
            'sender_id' => $request->user()->getKey(),
            'body' => $data['body'],
        ]);

        if ($supportRequest->status === SupportRequestStatus::Resolved || $supportRequest->status === SupportRequestStatus::Closed) {
            $supportRequest->update([
                'status' => SupportRequestStatus::Open,
                'resolved_at' => null,
            ]);
        }

        return back()->with('status', "Reply added to ticket {$supportRequest->ticket_number}.");
    }
}
