<?php

namespace App\Domains\Admin\Http\Controllers;

use App\Domains\Admin\Models\SupportRequest;
use App\Domains\Admin\Models\SupportRequestStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', SupportRequestStatus::Open->value);

        return view('admin.support', [
            'requests' => SupportRequest::query()
                ->with(['seller', 'storePage', 'messages.sender'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->when($request->filled('source'), fn ($query) => $query->where('source', (string) $request->string('source')))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'counts' => SupportRequest::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'activeStatus' => $status,
            'activeSource' => $request->query('source'),
        ]);
    }

    public function show(SupportRequest $supportRequest): View
    {
        return view('admin.support-show', [
            'supportRequest' => $supportRequest->load('seller', 'storePage', 'messages.sender'),
        ]);
    }

    public function update(Request $request, SupportRequest $supportRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(SupportRequestStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:4000'],
            'reply' => ['nullable', 'string', 'min:2', 'max:4000'],
        ]);

        $supportRequest->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'resolved_at' => in_array($data['status'], [SupportRequestStatus::Resolved->value, SupportRequestStatus::Closed->value], true)
                ? now()
                : null,
        ]);

        if (filled($data['reply'] ?? null)) {
            $supportRequest->messages()->create([
                'sender_type' => 'admin',
                'sender_id' => $request->user()->getKey(),
                'body' => $data['reply'],
            ]);
        }

        return back()->with('status', "Ticket {$supportRequest->ticket_number} updated.");
    }
}
