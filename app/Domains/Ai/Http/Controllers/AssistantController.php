<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Services\AiSettings;
use App\Domains\Ai\Services\BuyerAiBudget;
use App\Domains\Ai\Services\StoreGrounding;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AssistantController extends Controller
{
    public function __construct(
        private readonly AiSettings $settings,
        private readonly BuyerAiBudget $budget,
    ) {}

    public function store(Request $request, StorePage $page, AiManager $ai, StoreGrounding $grounding): JsonResponse
    {
        abort_unless((bool) $this->settings->enabled() && (bool) ($page->settings['ai_enabled'] ?? true), 503, 'Mercho is not enabled for this store.');

        $this->budget->assertAvailable($page);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*' => ['array'],
            'history.*.role' => ['nullable', 'string', 'in:user,assistant', 'max:20'],
            'history.*.content' => ['required', 'string', 'max:1200'],
        ]);
        $task = $page->theme->value === 'service' ? 'service_booking_assistant' : 'store_assistant';
        $result = $ai->runPlatform($task, ['message' => $data['message'], 'history' => $data['history'] ?? [], 'store_context' => $grounding->forPage($page)], $page);

        return response()->json(['task' => $task, 'answer' => is_array($result) ? ($result['reply'] ?? $result['suggested_reply'] ?? json_encode($result)) : $result, 'disclosure' => 'Mercho-generated answer based on this merchant’s published information.']);
    }
}
