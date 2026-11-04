<?php

namespace App\Domains\Ai\Http\Controllers;

use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Services\AiLimits;
use App\Domains\Ai\Services\AiUsageReservations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

class AiTaskController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $tasks = AiTask::query()
            ->where('store_page_id', $page->id)
            ->whereIn('status', ['queued', 'processing', 'completed', 'partially_completed', 'failed'])
            ->where(fn ($query) => $query
                ->whereIn('status', ['queued', 'processing'])
                ->orWhere('completed_at', '>=', now()->subMinutes(15)))
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json(['tasks' => $tasks]);
    }

    public function history(Request $request)
    {
        $page = $request->user()->storePages()->firstOrFail();
        $tasks = AiTask::query()->where('store_page_id', $page->id)->latest()->paginate(20);

        return view('dashboard.ai.tasks.index', compact('page', 'tasks'));
    }

    public function show(Request $request, int $task)
    {
        $page = $request->user()->storePages()->firstOrFail();
        $task = AiTask::query()->where('store_page_id', $page->id)->with('operations')->findOrFail($task);

        return view('dashboard.ai.tasks.show', compact('page', 'task'));
    }

    public function cancel(Request $request, int $task): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $task = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);

        $cancelledIds = AiOperation::query()
            ->where('ai_task_id', $task->id)
            ->whereIn('status', ['queued', 'running'])
            ->pluck('id');

        app(AiUsageReservations::class)->releaseMany($cancelledIds);

        $task->update(['status' => 'cancelled', 'cancelled_at' => now(), 'completed_at' => now()]);
        AiOperation::query()
            ->whereIn('id', $cancelledIds)
            ->update(['status' => 'cancelled', 'completed_at' => now()]);

        return response()->json(['task' => $task->fresh()]);
    }

    public function retryFailed(Request $request, int $task): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $limits = app(AiLimits::class);

        if ($limits->availableSlots($page) < 1) {
            return response()->json(['message' => 'This store has reached its active AI operation limit.'], 429);
        }

        $task = AiTask::query()->where('store_page_id', $page->id)->findOrFail($task);
        $failed = AiOperation::query()->where('ai_task_id', $task->id)->where('status', 'failed')->get();

        if ($failed->isEmpty()) {
            return response()->json([
                'retried' => 0,
                'message' => 'There are no failed items to retry on this task.',
            ], 422);
        }

        // A merchant can retry after cancelling (or after a failure). Clear the
        // terminal markers first so refreshProgress() does not treat the task as
        // cancelled while the retried jobs are running.
        $task->update(['status' => 'processing', 'cancelled_at' => null, 'completed_at' => null]);

        $retried = 0;
        $limited = false;

        foreach ($failed as $op) {
            // Re-check per item: the reservation is the authoritative slot
            // gate, and a batch that hits the cap stops instead of queueing
            // work the store has no slot or credit for.
            if ($limits->availableSlots($page) < 1) {
                $limited = true;

                break;
            }

            $op->update(['status' => 'queued', 'error' => null, 'started_at' => null, 'completed_at' => null]);

            try {
                app(AiUsageReservations::class)->reserveAndDispatch($op);
            } catch (Throwable) {
                $limited = true;

                break;
            }

            $retried++;
        }

        $task->refreshProgress();

        if ($retried === 0) {
            return response()->json([
                'retried' => 0,
                'message' => 'This store has reached its active AI operation limit.',
            ], 429);
        }

        return response()->json([
            'retried' => $retried,
            'limit_reached' => $limited,
            'task' => $task->fresh(),
        ]);
    }

    public function status(Request $request, int $task): JsonResponse
    {
        $page = $request->user()->storePages()->firstOrFail();
        $task = AiTask::query()->where('store_page_id', $page->id)->with('operations')->findOrFail($task);
        $task->refreshProgress();

        return response()->json(['task' => $task->fresh()->load('operations')]);
    }
}
