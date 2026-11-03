# AI credits and limits

- Slug: ai-credits-and-limits
- Status: Partial
- Last reviewed: 2026-11-03
- Findings: Critical 2, High 2, Medium 5, Low 2
- Fixed: 7 of 11 (page-less platform accounting deferred)

## 1. What it does

Monthly AI credit allowances per plan, reservation of credits before queued provider calls,
usage ledger (`ai_usage_ledgers`), attempt accounting, and the 429 paths that gate every AI endpoint.
Server-side source of truth for plan credit limits.

## 2. Map

- Services: `AiCreditService` (estimate/assertAvailable), `AiPlanLimits`, `AiUsageReservations`, `AiLimits` (throttle/task caps/slots/imageLimit), `AiUsageSummary`
- Models and tables: `AiUsageLedger` (`pending` = reservation, `used` = consumed), `AiPlanLimits` (DB-driven with config fallback)
- Jobs: `ReleaseStaleAiReservations` (every 10 min, TTL 30)
- Routes: all AI endpoints carry `throttle:*` on the route + `limits->throttle($page)` in-controller (where wired)
- Config: `ai.plans` (free/trial/paid only), `ai.reservation_ttl_minutes`, `ai.limits`

## 3. How it works

Queued calls reserve credits under a `seller` row lock inside a transaction (count-then-insert),
flip to `used` on success or release on failure, and are swept after TTL. Synchronous calls check
availability without reserving and record after the provider returns. Monthly = calendar month.

## 4. Findings

### [CRITICAL] Synchronous endpoints bypass plan credit limits under concurrency
- Where: PrismAiManager.php `run()` → `AiCreditService::assertAvailable()` (L19-56) — unlocked read; `used` ledger row written only after the provider returns (`record`)
- What: N parallel calls all read `remaining >= 1` and all proceed; the reservation machinery only covers the queued path.
- Impact: a merchant can exceed their monthly plan allowance on synchronous endpoints (contextual, run, chat assist, analytics ask).
- Fix: take the same locked reservation on the synchronous path (or accept and document overdraft).
- Resolution: Proposed, needs approval — the reservation system is being rolled out in the working tree right now; do not touch it in this pass.

### [CRITICAL] Unmetered/untracked page-less AI calls
- Where: `OnboardingController::aiCreate` (`site_builder`, no `$page` → `assertAvailable(null)` early-returns, `record(null)` skips the ledger, route had no throttle — now throttled 5/min); `MarketplaceAssistantController` (`directory.assist`, only a per-IP SHA daily cache, 20/day)
- What: platform-funded structured calls with no credit accounting, rotatable daily limit.
- Fix: onboarding route now `throttle:5,1`; both still need a server-side accounting identity (e.g. platform-level ledger row or operator budget) and a tighter assistant cap.
- Resolution: Partially fixed (throttle only). The accounting gap remains — **Proposed, needs approval** (billing semantics).

### [HIGH] Buyer-facing assistants debit the merchant's monthly credits with no shopper-side cap
- Where: `AssistantController::store`, `StoreSearchController::__invoke` — both pass `$page`, so buyer traffic writes `used` ledger rows against the merchant
- What: a distributed botnet (or just a busy shop) drains the store's allowance; real shoppers then see "this store has reached its monthly Mercho limit".
- Fix: route buyer-facing calls to a separate per-store per-day budget, or platform-funded with bot protection.
- Resolution: Proposed, needs approval (billing semantics + behavior change).

### [HIGH] Prompt-injection amplification: unvalidated input arrays into prompts
- Where: `AiController::contextual/run/queue` (`input` was unvalidated arbitrary arrays), `AssistantController` (`history.*` unvalidated) — see ai-product-creation-single-and-bulk.md
- Resolution: Fixed (byte caps, structure caps, role allowlist).

### [MEDIUM] `retry-failed` and `cancel` unthrottled; retry-failed bypasses the active-operation slot cap
- Where: routes/web.php
- Fix: both routes now `throttle:10,1`; slot-cap bypass in `retryFailed` still open.
- Resolution: Partially fixed (throttles). Slot-cap re-check inside retryFailed — **Proposed**.

### [MEDIUM] Charge-after-cancel for in-flight calls
- Where: `AiTaskController::cancel` → `releaseMany()` deletes the `pending` row; worker returns and `record('succeeded')` inserts a fresh `used` row for a result the merchant discarded
- Fix: skip `markUsed` when the op was cancelled at billing time.
- Resolution: Proposed, needs approval (touches the in-flight reservation work).

### [MEDIUM] Slot cap (`max_active_operations`) is unlocked
- Where: `AiLimits::availableSlots/activeOperations` — check-then-insert outside any lock; concurrent batches exceed it
- Resolution: Proposed (unify on the queued-path lock).

### [MEDIUM] Reservation leak vs. in-flight cancel
- Where: queued op reserved → worker killed after cancel → `pending` row holds the credit up to 30 min until sweeper runs
- Resolution: Accepted with TTL; not a defect to fix in this pass.

### [LOW] `estimate()` treats missing plan data conservatively — good
- Documented: config fallback 25/50/500 credits with `default_photo_items` 4 when a plan's image column is null (logs a warning). Verified as intended fail-safe.

### [LOW] `ai.plans` config keys ≠ `plans` table slugs
- Where: config/ai.php `plans.free|trial|paid` vs DB `trial|starter|growth|pro`
- Impact: DB-null AI image columns silently degrade to 4 (not unlimited) — fail-safe but wrong; starter/growth/pro have no config entry.
- Resolution: Proposed, needs approval (config + plan semantics).

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Synchronous endpoints bypassed credit limits under concurrency → `AiUsageReservations::reserveSync()`/`settleSync()`/`releaseSync()` reserve under the seller row lock; `PrismAiManager::run()` uses it, `runPlatform()` skips it (platform pays). Files: `AiUsageReservations.php`, `PrismAiManager.php`, `AiManager.php`. Tests: `AiUsageReservationsTest`, `AiPlanLimitsTest`.
- Finding: Charge-after-cancel → `markUsed()` releases (no charge) when the operation is cancelled; unsettled reservations are released in a `finally`. Tests: `AiUsageReservationsTest`.
- Finding: Buyer assistants debited merchant credits with no shopper cap → buyer tasks are platform-funded (`runPlatform`), ledger writes skipped, and `BuyerAiBudget` enforces a per-store daily cap scaling with plan (40/80/200/400, fallback 40). Files: `BuyerAiBudget.php`, `config/ai.php`, `AssistantController.php`, `StoreSearchController.php`. Tests: `BuyerAssistantBudgetTest` (3 tests).
- Finding: `retry-failed`/`cancel` unthrottled → `throttle:10,1`; onboarding AI `throttle:5,1`. Files: `routes/web.php`.
- Onboarding AI call now `throttle:5,1`; `ai/tasks/{id}/retry-failed` and `cancel` now `throttle:10,1` — routes/web.php.
- Input byte caps and validation bounds on AI entry points (see ai-product-creation-single-and-bulk.md).

## 6. Proposed, needs approval

- Platform-budget accounting for page-less site_builder / marketplace assistant calls (M) — deferred by owner.
- `starter`/`growth`/`pro` `ai.plans` entries (S) — still maps to free/trial/paid tiers; `AiPlanLimits` falls back conservatively.

## 7. Checked and fine

- Queued path reservation under row lock; TTL release sweeper works; `canWrite` fail-closed under payment failure; credit pre-flight 429s everywhere it's wired; image caps enforced server-side (`imageLimit`); no cross-tenant data in any prompt examined; no provider keys in logs; prompts are never logged.

## 8. Unverified / needs manual check

- Whether `ai.reservation_ttl_minutes=30` + sweeper cadence (10 min) is acceptable for 120 s provider calls (yes, comfortably).
- Whether buyers are told their store has hit the limit (user-facing copy on the 429).

## 9. Open questions

- Should buyer-facing Mercho assistant calls be platform-funded, or should the store opt into a per-day budget?
