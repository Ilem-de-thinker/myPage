<?php

namespace App\Domains\Chat\Http\Controllers;

use App\Domains\Chat\Services\ChatService;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PublicChatController extends Controller
{
    public function open(Request $request, StorePage $page, ChatService $chat): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'subject' => ['nullable', 'string', 'max:160'], 'order_reference' => ['nullable', 'string', 'max:30'], 'booking_reference' => ['nullable', 'string', 'max:30'], 'body' => ['required', 'string', 'max:5000']]);
        abort_unless($data['email'] ?? $data['phone'] ?? false, 422, 'A contact method is required.');
        $opened = $chat->openGuestThread($page, $data);
        $chat->send($opened['thread'], 'buyer', null, $data['body']);

        return response()->json(['thread_id' => $opened['thread']->id, 'token' => $opened['token']], 201);
    }

    public function show(Request $request, StorePage $page, int $thread, ChatService $chat): View|JsonResponse
    {
        $thread = $chat->findByGuestToken($page, $thread, (string) $request->query('token'));

        $thread->load('messages.attachments');
        $thread->messages()->where('sender_type', 'seller')->whereNull('read_at')->update(['read_at' => now()]);

        if (! $request->expectsJson()) {
            return view('themes.shared.chat', ['page' => $page, 'thread' => $thread, 'token' => (string) $request->query('token')]);
        }

        return response()->json(['thread' => $thread]);
    }

    public function store(Request $request, StorePage $page, int $thread, ChatService $chat): JsonResponse
    {
        $thread = $chat->findByGuestToken($page, $thread, (string) $request->input('token'));
        $data = $request->validate(['token' => ['required', 'string'], 'body' => ['required', 'string', 'max:5000'], 'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp3,m4a', 'max:10240']]);
        $message = $chat->send($thread, 'buyer', null, $data['body'], attachment: $request->file('attachment'));

        return response()->json(['message' => $message]);
    }
}

namespace App\Http\Controllers;

class PublicChatController extends Controller
{
    //
}
