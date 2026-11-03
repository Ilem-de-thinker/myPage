<?php

namespace App\Domains\Chat\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Chat\Models\ChatThread;
use App\Domains\Chat\Services\ChatService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();

        $threads = ChatThread::query()->where('store_page_id', $page->id)->withCount(['messages', 'messages as unread_count' => fn ($query) => $query->where('sender_type', 'buyer')->whereNull('read_at')])->latest('last_message_at')->paginate(30);

        if (! $request->expectsJson()) {
            return view('dashboard.chat.index', ['page' => $page, 'threads' => $threads]);
        }

        return response()->json(['threads' => $threads]);
    }

    public function show(Request $request, int $thread): View|JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $thread = ChatThread::query()->where('store_page_id', $page->id)->with(['messages.attachments', 'order.history', 'booking.history', 'storePage'])->findOrFail($thread);
        $thread->messages()->where('sender_type', 'buyer')->whereNull('read_at')->update(['read_at' => now()]);

        if (! $request->expectsJson()) {
            return view('dashboard.chat.show', ['page' => $page, 'thread' => $thread, 'timeline' => app(ChatService::class)->timeline($thread), 'whatsappUrl' => app(ChatService::class)->whatsappUrl($thread)]);
        }

        return response()->json(['thread' => $thread]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $threads = ChatThread::query()->where('store_page_id', $page->id)->withCount(['messages as unread_count' => fn ($query) => $query->where('sender_type', 'buyer')->whereNull('read_at')])->latest('last_message_at')->limit(30)->get(['id', 'subject', 'guest_name', 'status', 'last_message_at']);

        return response()->json(['threads' => $threads, 'unread_count' => $threads->sum('unread_count')]);
    }

    public function summarize(Request $request, int $thread, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $thread = ChatThread::query()->where('store_page_id', $page->id)->with('messages')->findOrFail($thread);
        $result = $ai->run('order_chat_assist', ['thread_subject' => $thread->subject, 'customer_name' => $thread->guest_name, 'messages' => $thread->messages->slice(-20)->values()->map(fn ($message): array => ['sender' => $message->sender_type, 'body' => $message->body])->all(), 'instruction' => 'Return a concise conversation summary. Do not suggest actions or invent facts.'], $page);
        $summary = is_array($result) ? (string) ($result['summary'] ?? '') : $result;
        $thread->forceFill(['ai_summary' => $summary, 'ai_summary_updated_at' => now()])->save();

        return response()->json(['summary' => $summary]);
    }

    public function translate(Request $request, int $thread, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $thread = ChatThread::query()->where('store_page_id', $page->id)->with('messages')->findOrFail($thread);
        $data = $request->validate(['message_id' => ['required', 'integer'], 'language' => ['required', 'string', 'max:40']]);
        $message = $thread->messages()->findOrFail($data['message_id']);
        $result = $ai->run('translate', ['text' => $message->body, 'target_language' => $data['language']], $page);

        return response()->json(['text' => is_array($result) ? ($result['text'] ?? '') : $result]);
    }

    public function store(Request $request, int $thread, ChatService $chat): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $thread = ChatThread::query()->where('store_page_id', $page->id)->findOrFail($thread);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000'], 'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp3,m4a', 'max:10240']]);
        $message = $chat->send($thread, 'seller', $request->user()->id, $data['body'], attachment: $request->file('attachment'));

        return response()->json(['message' => $message]);
    }

    public function aiSuggestion(Request $request, int $thread, AiManager $ai): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        abort_unless((bool) ($page->settings['ai_enabled'] ?? true) && (bool) ($page->settings['ai_order_chat'] ?? false), 403, 'Mercho is disabled for order chat in this store.');
        $thread = ChatThread::query()->where('store_page_id', $page->id)->with('messages')->findOrFail($thread);
        $result = $ai->run('order_chat_assist', [
            'thread_subject' => $thread->subject,
            'customer_name' => $thread->guest_name,
            'messages' => $thread->messages->slice(-20)->values()->map(fn ($message): array => ['sender' => $message->sender_type, 'body' => $message->body])->all(),
            'instruction' => 'Suggest a reply only. Never promise refunds, payment outcomes, delivery dates, or policy exceptions.',
        ], $page);

        return response()->json(['suggestion' => $result, 'auto_send' => false]);
    }

    public function toggleAi(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $settings = $page->settings ?? [];
        $settings['ai_order_chat'] = ! (bool) ($settings['ai_order_chat'] ?? false);
        $page->forceFill(['settings' => $settings])->save();

        return response()->json(['enabled' => $settings['ai_order_chat']]);
    }
}

namespace App\Http\Controllers;

class ChatController extends Controller
{
    //
}
