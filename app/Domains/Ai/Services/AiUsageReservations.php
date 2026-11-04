<?php

namespace App\Domains\Ai\Services;

use App\Domains\Ai\Jobs\RunAiOperation;
use App\Domains\Ai\Models\AiOperation;
use App\Domains\Ai\Models\AiTask;
use App\Domains\Ai\Models\AiUsageLedger;
use App\Domains\Seller\Models\Seller;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Reserves a merchant's monthly Mercho credit when work is queued.
 *
 * Counting only completed ledger rows leaves a window where a whole batch can
 * pass the same remaining-credit check. A pending ledger row closes that window:
 * the seller row is locked while the check and insert happen, and every later
 * check counts pending rows alongside successful ones.
 */
final class AiUsageReservations
{
    public function __construct(
        private readonly AiCreditService $credits,
        private readonly AiLimits $limits,
    ) {}

    /**
     * Create the pending reservation and queue the operation in one place, so a
     * controller cannot accidentally queue work without reserving it first.
     */
    public function reserveAndDispatch(AiOperation $operation): void
    {
        try {
            $this->reserve($operation);
        } catch (Throwable $exception) {
            $operation->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'completed_at' => now(),
            ]);

            if ($operation->ai_task_id) {
                AiTask::find($operation->ai_task_id)?->refreshProgress();
            }

            throw $exception;
        }

        try {
            RunAiOperation::dispatch($operation);
        } catch (Throwable $exception) {
            $this->release($operation);
            throw $exception;
        }
    }

    /**
     * Atomically reserve one credit for an operation.
     *
     * $credits->estimate() counts pending and used rows, so the lock plus that
     * count is the gate concurrent requests share. An existing reservation is a
     * no-op, which makes retries and duplicate dispatches safe.
     */
    public function reserve(AiOperation $operation): void
    {
        if ($operation->store_page_id === null) {
            return;
        }

        DB::transaction(function () use ($operation): void {
            Seller::query()->whereKey($operation->seller_id)->lockForUpdate()->first();

            $existing = AiUsageLedger::query()->where('operation_id', $operation->getKey())->first();

            if ($existing !== null) {
                return;
            }

            $page = StorePage::query()->find($operation->store_page_id);

            if ($page === null) {
                return;
            }

            // The seller lock is also the slot lock. Checking the active
            // operation count here (rather than only before inserting) makes
            // concurrent batches share one authoritative gate: the row this
            // operation already added counts, so the batch stops at the cap
            // instead of sailing past a stale pre-flight count.
            $active = AiOperation::query()
                ->where('seller_id', $operation->seller_id)
                ->whereIn('status', ['queued', 'running'])
                ->count();

            abort_if(
                $active > $this->limits->maxActiveOperations(),
                429,
                'This store already has the maximum number of Mercho tasks running. Wait for one to finish and try again.'
            );

            $globalMax = $this->limits->maxGlobalActiveOperations();

            if ($globalMax > 0) {
                abort_if(
                    $this->limits->globalActiveOperations() > $globalMax,
                    429,
                    'Mercho is handling the maximum number of tasks right now. Try again in a moment.'
                );
            }

            $estimate = $this->credits->estimate($page, 1, $operation->task);

            abort_if(
                $estimate['allowed'] < 1,
                429,
                'This store has reached its monthly Mercho limit for '.AiSettings::taskLabel($operation->task).'.',
            );

            AiUsageLedger::create([
                'seller_id' => $operation->seller_id,
                'store_page_id' => $operation->store_page_id,
                'operation_id' => $operation->getKey(),
                'task' => $operation->task,
                'status' => 'pending',
                'provider' => 'pending',
                'model' => 'pending',
                'tokens_in' => 0,
                'tokens_out' => 0,
                'estimated_cost' => 0,
                'reserved_at' => now(),
            ]);
        });
    }

    /**
     * Reserve a credit for a provider call that happens inside the request.
     *
     * The queued path reserves before dispatch, but synchronous merchants skip
     * the queue entirely. Without this row two parallel clicks can both pass
     * the remaining-credit check, so the same lock-and-count rule applies here.
     * Returns null when the caller has no store page (platform-owned work).
     */
    public function reserveSync(StorePage $page, string $task): ?AiUsageLedger
    {
        return DB::transaction(function () use ($page, $task): AiUsageLedger {
            Seller::query()->whereKey($page->seller_id)->lockForUpdate()->first();

            $estimate = $this->credits->estimate($page, 1, $task);

            abort_if(
                $estimate['allowed'] < 1,
                429,
                'This store has reached its monthly Mercho limit for '.AiSettings::taskLabel($task).'.',
            );

            return AiUsageLedger::create([
                'seller_id' => $page->seller_id,
                'store_page_id' => $page->id,
                'operation_id' => null,
                'task' => $task,
                'status' => 'pending',
                'provider' => 'pending',
                'model' => 'pending',
                'tokens_in' => 0,
                'tokens_out' => 0,
                'estimated_cost' => 0,
                'reserved_at' => now(),
            ]);
        });
    }

    /**
     * Settle a synchronous reservation once the provider answered.
     */
    public function settleSync(
        AiUsageLedger $ledger,
        string $provider,
        string $model,
        int $tokensIn,
        int $tokensOut,
        float $estimatedCost = 0,
    ): void {
        AiUsageLedger::query()
            ->whereKey($ledger->getKey())
            ->where('status', 'pending')
            ->update([
                'status' => 'used',
                'provider' => $provider,
                'model' => $model,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'estimated_cost' => $estimatedCost,
            ]);
    }

    /**
     * Drop a synchronous reservation that never completed.
     */
    public function releaseSync(AiUsageLedger $ledger): void
    {
        AiUsageLedger::query()
            ->whereKey($ledger->getKey())
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * Turn the pending row into usage. If a cleanup already released it, create
     * a fresh used row so a successful provider call is never lost from billing.
     */
    public function markUsed(
        int $operationId,
        StorePage $page,
        string $task,
        string $provider,
        string $model,
        int $tokensIn,
        int $tokensOut,
        float $estimatedCost = 0,
    ): void {
        // A worker already talking to the provider cannot be interrupted when
        // the merchant cancels. Cancelled work is not billed; the platform
        // absorbs the in-flight provider cost instead of double-charging.
        $operation = AiOperation::query()->find($operationId);

        if ($operation !== null && $operation->status === 'cancelled') {
            $this->release($operationId);

            return;
        }

        $ledger = AiUsageLedger::query()->where('operation_id', $operationId)->first();

        if ($ledger !== null) {
            if ($ledger->status === 'used') {
                return;
            }

            $ledger->update([
                'status' => 'used',
                'provider' => $provider,
                'model' => $model,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'estimated_cost' => $estimatedCost,
            ]);

            return;
        }

        AiUsageLedger::create([
            'seller_id' => $page->seller_id,
            'store_page_id' => $page->id,
            'operation_id' => $operationId,
            'task' => $task,
            'status' => 'used',
            'provider' => $provider,
            'model' => $model,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'estimated_cost' => $estimatedCost,
            'reserved_at' => now(),
        ]);
    }

    /**
     * Delete only pending rows. A successful row is usage history and must stay.
     */
    public function release(AiOperation|int $operation): void
    {
        $operationId = $operation instanceof AiOperation ? $operation->getKey() : $operation;

        AiUsageLedger::query()
            ->where('operation_id', $operationId)
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * @param  iterable<int>  $operationIds
     */
    public function releaseMany(iterable $operationIds): void
    {
        AiUsageLedger::query()
            ->whereIn('operation_id', $operationIds)
            ->where('status', 'pending')
            ->delete();
    }

    /**
     * A worker can be killed after reserving but before it can release. The
     * scheduled sweep is the safety net that keeps those rows from holding a
     * merchant's allowance forever.
     */
    public function releaseStale(): int
    {
        $minutes = max(1, (int) config('ai.limits.reservation_ttl_minutes', 30));

        return AiUsageLedger::query()
            ->where('status', 'pending')
            ->where('reserved_at', '<', now()->subMinutes($minutes))
            ->delete();
    }
}
