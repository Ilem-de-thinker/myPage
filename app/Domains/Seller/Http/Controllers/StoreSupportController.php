<?php

namespace App\Domains\Seller\Http\Controllers;

use App\Domains\Admin\Models\SupportRequest;
use App\Domains\Admin\Models\SupportRequestStatus;
use App\Domains\Seller\Models\StorePage;
use App\Shared\Support\StoreUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StoreSupportController extends Controller
{
    public function show(Request $request, StorePage $page, string $ticketNumber): View
    {
        $ticket = $this->ticket($request, $page, $ticketNumber);

        return view('themes.shared.support-ticket', [
            'page' => $page,
            'ticket' => $ticket,
            'token' => (string) $request->query('token'),
        ]);
    }

    public function store(Request $request, StorePage $page): RedirectResponse
    {
        $data = $request->validate([
            'destination' => ['required', 'in:store,platform'],
            'category' => ['required', 'in:question,payment,order,technical,other'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:20', 'max:4000'],
            'requester_name' => ['nullable', 'string', 'max:120'],
            'requester_contact' => ['nullable', 'string', 'max:190'],
        ]);

        $token = Str::random(48);
        $ticket = SupportRequest::create([
            ...$data,
            'store_page_id' => $page->getKey(),
            'seller_id' => $data['destination'] === 'store' ? $page->seller_id : null,
            'source' => $data['destination'] === 'store' ? 'store' : 'buyer',
            'status' => SupportRequestStatus::Open,
            'access_token_hash' => Hash::make($token),
        ]);

        $ticket->messages()->create([
            'sender_type' => 'buyer',
            'body' => $data['body'],
        ]);

        $ticketUrl = StoreUrl::path('storefront.support.show', [
            'ticketNumber' => $ticket->ticket_number,
            'token' => $token,
            'slug' => $page->slug,
        ]);

        $recipient = $data['destination'] === 'store' ? $page->name : 'mypage.ng support';

        return back()
            ->with('support_sent', "Ticket {$ticket->ticket_number} sent to {$recipient}.")
            ->with('support_ticket_url', $ticketUrl);
    }

    public function reply(Request $request, StorePage $page, string $ticketNumber): RedirectResponse
    {
        $ticket = $this->ticket($request, $page, $ticketNumber);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:4000']]);

        $ticket->messages()->create([
            'sender_type' => 'buyer',
            'body' => $data['body'],
        ]);

        if ($ticket->status === SupportRequestStatus::Resolved || $ticket->status === SupportRequestStatus::Closed) {
            $ticket->update(['status' => SupportRequestStatus::Open, 'resolved_at' => null]);
        }

        return back()->with('support_sent', 'Your reply was added to the ticket.');
    }

    private function ticket(Request $request, StorePage $page, string $ticketNumber): SupportRequest
    {
        $token = (string) $request->query('token');
        $ticket = SupportRequest::query()
            ->where('ticket_number', $ticketNumber)
            ->where('store_page_id', $page->getKey())
            ->with('messages.sender')
            ->firstOrFail();

        abort_unless(filled($ticket->access_token_hash) && Hash::check($token, $ticket->access_token_hash), 404);

        return $ticket;
    }
}
