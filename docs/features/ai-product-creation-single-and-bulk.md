# AI product creation (single and bulk)

- Slug: ai-product-creation-single-and-bulk
- Status: Partial
- Last reviewed: 2026-11-03
- Findings: Critical 2, High 2, Medium 4, Low 2
- Fixed: 8 of 10

Covers `POST ai/run`, `ai/contextual`, `ai/queue`, `ai/batch`, `ai/drafts/*` (single product/service
copy generation with mandatory merchant approval), and the photo-to-listing flow (ai-photo-listing.md).

## 1. What it does

Generate listing copy for products and services from a merchant-supplied seed; drafts are stored
unapproved and must be explicitly published. Bulk mode queues one operation per item, each reserved
against the plan's credits.

## 2. Map

- Routes: `dashboard.ai.run`, `dashboard.ai.contextual`, `dashboard.ai.queue`, `dashboard.ai.batch` (routes/web.php:245-258), `dashboard.ai.drafts.*` (approve/reject/update/discard/regenerate/undo/confirm-selected, routes/web.php:230-238)
- Controllers: `AiController` (`run`, `contextual`, `queue`, `batch`), `AiDraftController`
- Services: `PrismAiManager` (provider), `AiLimits`, `AiCreditService`, `AiUsageReservations`
- Models and tables: `AiDraft`, `AiOperation`, `AiTask`, `ai_usage_ledgers`
- Plan gating: `AiLimits::throttle/assertCanQueue/availableSlots/maxBatchItems`, credit pre-flight 429

## 3. How it works

Sync `run()`: whitelist task → validate → `AI feature registry` authorize → provider call → draft created
(`approval_required`). Bulk `batch()`: `min(credits, slots, maxBatchItems)` — pre-flight only; each item
gets its own locked reservation (`reserveAndDispatch`) so overspend can't happen at the ledger level.

## 4. Findings

### [CRITICAL] Arbitrary local files read and forwarded to the provider (authenticated)
- Where: PrismAiManager.php `request()` — previously honored `input['media_paths']`/`media_path` with no allowlist and **any task** (not just vision tasks)
- What: a merchant with 1 credit could reference `/etc/passwd`-style paths; the bytes (if decodable as an image) went to the vision provider.
- Fix: legacy `media_paths` branch removed entirely; `media_relative_paths` now validated against the page's own `ai/{page_id}/` staging prefix only, `..`/leading-slash/normalization-safe, and requires a `$page`. Non-vision tasks ignore media silently.
- Resolution: Fixed.

### [CRITICAL] Path traversal on the public disk for media_relative_paths
- Where: PrismAiManager.php `request()` — `Storage::disk('public')->exists()` only; Flysystem does not collapse `../`
- Impact: a merchant could reference (and have forwarded) files outside the intended staging area.
- Fix: `safeStagingPath()` normalizes, rejects `.`/`..` segments and anything outside `ai/{page_id}/`. Draft regeneration paths (`$draft->payload['_media_relative_paths']`) are likewise forced through this check.
- Resolution: Fixed.

### [HIGH] Unvalidated `input` arrays into prompts (cost amplification)
- Where: `AiController::contextual`, `run`, `queue` — `'input' => 'array'` with no size bound
- What: a 1 MB `description` string would be embedded verbatim in the system-bound prompt; no token accounting pre-call; the `assistant`/`translate` copy paths were partially capped, these were not.
- Fix: `input` capped at 30 keys; `json_encode` size cap 64 KB → 422.
- Resolution: Fixed.

### [HIGH] Onboarding site-builder call bypasses all credit accounting
- Where: `OnboardingController::aiCreate` — pageless call, `assertAvailable(null)` early-returns; the route had no throttle
- Fix: route now `throttle:5,1`; full accounting still pending (see ai-credits-and-limits.md).
- Resolution: Partially fixed.

### [MEDIUM] `retry-failed` unthrottled and re-queues without re-checking the slot cap
- Where: `AiTaskController::retryFailed`; routes/web.php
- Fix: route now `throttle:10,1`; slot-cap re-check open — see ai-credits-and-limits.md.
- Resolution: Partially fixed.

### [MEDIUM] Non-retryable provider failures were retried 4× by the framework
- Where: `RunAiOperation::handle()` catch — manual cleanup, then `throw $exception`; `tries = 4` and the claim set includes `failed`
- Impact: permanently failing calls (auth errors, schema violations) re-hit the provider up to 4 times, burning tokens and spamming `ai_runs`.
- Fix: no rethrow after manual cleanup; `report($exception)` keeps the error visible.
- Resolution: Fixed.

### [MEDIUM] Images shipped to the provider at upload size, no resize
- Where: `PrismAiManager::request()` → `Image::fromStoragePath` — up to 10 MB base64 in the prompt
- Fix: resize/recompress to a bounded edge (e.g. 1600 px) before the provider call.
- Resolution: Proposed (behavior change; affects provider cost/latency).

### [MEDIUM] Batch slot cap is advisory under concurrency
- Where: `batch()`/`photoListing()` compute `allowed` outside a lock, then per-item reservations take the lock — credits are safe, `max_active_operations` is not.
- Resolution: Proposed (unify on the reservation lock).

### [LOW] ValidationException was recorded nowhere
- Where: `run()` rethrows before `record('failed')`
- Resolution: Proposed (one-line: record before rethrow).

### [LOW] Logo color GD path could decode huge bitmaps
- Where: `AiController::logoColors()` — full `imagecreatefrom*` on up to 4 MB with no dimension bound before the sampling loop
- Fix: reject sources > 4096 px on any side (checked via `getimagesize` before decode); test coverage by existing suite.
- Resolution: Fixed.

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Absolute-file forwarding/staging privacy → the provider only receives tenant-scoped private staging paths; dashboard previews go through `dashboard.ai.media`. Files: `PrismAiManager.php`, `AiImageService.php`, `routes/web.php`. Tests: `AiListingImagesTest`.
- Finding: No resize before provider → 1600px webp q80 downscale for provider payloads. Files: `AiImageService.php`.
- Media path allowlist + traversal rejection + legacy `media_paths` removal; `RunAiOperation` no longer rethrows non-retryable failures; chat-thread prompts capped to last 20 messages; `AssistantController` history items validated (`max:1200` content, `role in:user,assistant`); AI input arrays byte-capped; onboarding AI route throttled; `retry-failed`/`cancel` throttled; logo dimension cap.
- Files: `PrismAiManager.php`, `AiController.php`, `AssistantController.php`, `ChatController.php`, `RunAiOperation.php`, `routes/web.php`.
- Tests: `php artisan test --filter='Ai|AI'` — 286 passed. New bounds are pre-validated; no prompt fixtures were changed.

## 6. Proposed, needs approval

- Batch slot-cap lock (S).
- `max_active_operations` hard global cap (S).
- Record `ai_runs` row on ValidationException (S; observability).

## 7. Checked and fine

- Drafts always require explicit merchant approval before publishing; storefront direction input is rebuilt server-side; buyer content enters prompts only under shared guardrails; no merchant name/keys in prompts; credential detection unchanged; AI errors never echo raw provider payloads to the browser.

## 8. Unverified / needs manual check

- Provider payload sizes in production (check Prism logs for 413/400 on image sends).
- Whether `withoutGlobalScopes` on `AiOperation` claim has a cross-tenant read path (claim uses `whereKey` after `refresh` on an already-loaded model — no cross-tenant path found).

## 9. Open questions

- Should AI image uploads be auto-resized to a fixed maximum edge, and what edge?
