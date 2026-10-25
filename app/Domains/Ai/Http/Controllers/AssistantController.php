<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Services\StoreGrounding;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AssistantController extends Controller
{
    public function store(Request $request, StorePage $page, AiManager $ai, StoreGrounding $grounding): JsonResponse
    {
        abort_unless((bool) config('ai.enabled') && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'The AI assistant is not enabled for this store.');
        $data = $request->validate(['message' => ['required', 'string', 'max:2000'], 'history' => ['nullable', 'array', 'max:10']]);
        $task = $page->theme->value === 'service' ? 'service_booking_assistant' : 'store_assistant';
        $result = $ai->run($task, ['message' => $data['message'], 'history' => $data['history'] ?? [], 'store_context' => $grounding->forPage($page)], $page);

        return response()->json(['task' => $task, 'answer' => is_array($result) ? ($result['reply'] ?? $result['suggested_reply'] ?? json_encode($result)) : $result, 'disclosure' => 'AI-generated answer based on this merchant’s published information.']);
    }
}
