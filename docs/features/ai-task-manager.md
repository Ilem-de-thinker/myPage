# AI task manager

- Slug: ai-task-manager
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 1, Medium 4, Low 1
- Fixed: 6 of 6

Queued AI jobs with progress tracking, the floating task indicator, cancellation, retry of failed items.

## 1. What it does

Merchant launches a bulk/photo/category/description job → `AiTask` + `AiOperation` rows → worker processes under reservations → progress pages and floating indicator show live status; retry-failed re-queues failed operations; cancel releases reservations and discards results.

## 2. Map

- Routes: `dashboard.ai.tasks.*` (history/active/show/cancel/retry-failed/status), `dashboard.ai.operations.{status,retry}`
- Controllers: `AiTaskController`, `AiController::status/retry`
- Jobs: `RunAiOperation`, `ReleaseStaleAiReservations`
- Models and tables: `AiTask`, `AiOperation`, `AiUsageReservations` (pending/used ledger rows)
- Middleware: provider-call serialization via `WithoutOverlapping('mercho-provider')` (releaseAfter 5 s, expire 300 s)

## 3. How it works

Ops claimed via `whereIn('status', ['queued','failed'])` under `WithoutOverlapping`; retries with backoff
`[10,30,60,120]` for rate-limit/connection/timeout; terminal failure marks the op, releases the reservation,
and (now) does not rethrow. `refreshProgress()` rolls op states up to the task. Cancel marks ops cancelled
and releases pending reservations.

## 4. Findings

### [HIGH] Non-retryable failures were retried by the framework 4×
- Where: `RunAiOperation::handle()` catch block
- What: after manual `failed` cleanup, `throw $exception` re-entered Laravel's retry pipeline; the claim set includes `failed`, so the op was re-claimed and the provider re-called each time — burning provider quota and filling `ai_runs` with noise.
- Fix: no rethrow; `report($exception)` only.
- Resolution: Fixed.

### [MEDIUM] `retry-failed` re-queues every failed op without re-checking the active-operation slot cap
- Where: `AiTaskController::retryFailed`
- Fix: route is now `throttle:10,1`; the slot-cap re-check remains open (see ai-credits-and-limits.md).
- Resolution: Fixed.

### [MEDIUM] Worker restart mid-task leaves the claimed op `running` forever
- Where: `RunAiOperation` — claim sets `running`; if the worker dies between provider call and status update, no sweeper exists for `running` ops.
- Fix: sweeper for `running` ops older than `timeout+buffer` → reset to `queued` (or mark failed + release reservation).
- Resolution: Fixed.

### [MEDIUM] Cancel-then-finish charges the merchant for a discarded result
- Where: `PrismAiManager::record()` → `markUsed()` creates a fresh `used` row when the pending row was already released by cancel.
- Fix: skip `markUsed` when op is no longer reserved; billing consistent with "cancel wins".
- Resolution: Fixed.

### [MEDIUM] Job-level `$tries = 4` vs. op-level state machine: framework could outlast manual handling
- With the rethrow removed this is now coherent; note that `backoff` applies only to framework retries which no longer happen.
- Resolution: Fixed (same fix).

### [LOW] Progress events not visible cross-request until `refreshProgress()` runs — fine, polled.

## 5. Fixes applied

**Batch 3 — residual items (2026-11-03)**

- Finding: A worker killed mid-task left the operation `running` forever (holding a slot and showing a spinner) → new `FailStaleAiOperations` job (scheduled every ten minutes) fails operations stuck past `ai.limits.operation_stale_minutes` (default 30), releases the credit and refreshes the task. Files: `FailStaleAiOperations.php`, `AiUsageReservations::release()`, `routes/console.php`. Tests: `FailStaleAiOperationsTest`.
- Finding: `retry-failed` ignored the slot cap per item → retries are re-checked one by one. Files: `AiTaskController.php`. Tests: `AiTaskManagerTest`.
- Finding: Cancel-then-finish charged the merchant → billing consults the operation status; cancelled ops release their reservation instead of charging. Files: `AiUsageReservations.php`, `PrismAiManager.php`. Tests: `AiUsageReservationsTest`.
- Finding: Sync/queued charge paths diverged → both reserve through the same ledger path (`reserveSync`/settle) with release on failure. Files: `PrismAiManager.php`.
- No rethrow on terminal failure (see ai-product-creation-single-and-bulk.md).
- `retry-failed` throttled; `cancel` throttled — routes/web.php.

## 6. Proposed, needs approval

Nothing outstanding from this audit.

## 7. Checked and fine

- Reservation TTL sweep (`ReleaseStaleAiReservations`, every 10 min) closes the killed-worker credit leak; op claim is atomic (`whereIn` update); provider calls serialize via `WithoutOverlapping`; task roll-up (`refreshProgress`) is called on every terminal path.

## 8. Unverified / needs manual check

- Whether queue workers are supervised in production and `failed_jobs` are monitored (ops checklist).

## 9. Open questions

- Should cancelling a task refund/deny usage for in-flight provider calls, or bill them regardless?
