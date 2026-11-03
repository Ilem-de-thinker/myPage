# Per-plan Mercho limits and merchant usage meter

## Audit findings (verified against the code)

1. **Plans**
   - Limits live in `plans.max_products` and `plans.max_staff` (`database/migrations/2026_10_11_090000_create_billing_tables.php`, `2026_11_01_191159_add_max_staff_to_plans_table.php`).
   - Seeded from `config/billing.php` by `database/seeders/PlanSeeder.php`.
   - Actual slugs: `trial`, `starter`, `growth`, `pro`.
   - `Plan` has no AI columns today.

2. **AI credit resolution**
   - `AiCreditService` selects the seller subscription's plan slug, but then reads `config("ai.plans.{$plan}.monthly_credits")`.
   - `config/ai.php` only has `free`, `trial`, and `paid`. The claimed bug is real: `starter`, `growth`, and `pro` all fall through to the `paid` value of 500.
   - The current fallback uses `(int) (... ?? config('ai.plans.paid...'))`, so a limit of `0` is also treated as unlimited.

3. **Image cap enforcement**
   - Global only: `config/ai.php` `limits.max_photo_items`, `AiLimits::maxPhotoItems()`.
   - Hard-coded `max:12` in `AiController::photoListing()` and `suggestPhotoGroups()`.
   - `photoGroups()` hard-codes `max:12` for group and per-group size.
   - `photoListing()` also caps allowed operations with `maxPhotoItems()`.
   - `suggestPhotoGroups()` checks count after validation but before storage.
   - No per-plan image cap exists.

4. **Usage recording**
   - `PrismAiManager::record()` writes `ai_usage_ledgers` only when a provider attempt succeeds.
   - Failed attempts write `ai_runs`, not ledger rows. Usage is therefore counted after success and can be overrun by concurrent queued work.

5. **Subscriptions disabled**
   - `AiCreditService::estimate()` returns `plan = platform`, `limit = null`, and `remaining = null` when `SubscriptionAvailability` is disabled. Monthly credits are unlimited today.

6. **Existing UI**
   - The AI launcher shows the estimate text (`used of limit`, `allowed of requested`).
   - Billing shows plan, price, products, trial, and subscription dates. It does not show AI usage.
   - Admin Plans screen shows product/staff limits and price controls, but no AI limits.

## Implementation design

### Data and config
- Add `plans.ai_monthly_credits` (nullable unsigned integer, null = unlimited).
- Add `plans.ai_max_images_per_request` (unsigned integer).
- Add the same keys to `config/billing.php`, `PlanSeeder`, and `Plan` fillable/casts.
- Seed new AI limits on first creation only so admin edits survive deploys.
- Migration backfills existing rows:
  - `trial`: 50 credits, 4 images
  - `starter`: 250 credits, 8 images
  - `growth`: 1000 credits, 12 images
  - `pro`: 5000 credits, 20 images
  - Unknown existing plans: conservative 25 credits, 4 images.
- Add `ai.limits.absolute_max_photo_items = 20`, `ai.limits.default_photo_items = 4`, and `ai.limits.fallback_monthly_credits = 25`.
- Keep the legacy `max_photo_items` as an additional safety cap, default 20.

### Plan resolution
- Add `AiPlanLimits` to resolve the seller's actual plan row first.
- Fall back to `config/ai.php` for `free`/`trial`/`paid`.
- A missing configuration uses the conservative configured fallback and logs a warning; it never becomes unlimited.
- `null` on a plan row means unlimited; `0` means no credits.
- Effective monthly limit = `min(plan credits, feature cap)` with null handled as "no cap".
- Effective image limit = `min(plan image limit, absolute ceiling, legacy photo cap)`.

### Enforcement
- `AiLimits::imageLimit(StorePage)` and `assertImageCount(StorePage, int)`.
- Dynamic image validation in `photoListing()` and `suggestPhotoGroups()`.
- Plan-aware group bounds in `photoGroups()`.
- Image count is checked before `AiImageService::stage()`.
- Exhausted monthly usage is rejected at queue time and in `PrismAiManager::assertAvailable()`.
- Batch operations continue to use the full requested count for the estimate before queueing.

### Usage summary and UI
- Add `AiUsageSummary` with plan, period start/end, limit, used, remaining, percent, unlimited, per-task breakdown, image limit, billing state, and state (`normal`, `warning`, `exhausted`, `unlimited`).
- Add `GET /ai/usage`, scoped to the authenticated seller's page.
- Add `resources/views/components/ai/usage-meter.blade.php` using existing design tokens and accessible progressbar attributes.
- Add the meter to the billing plan card.
- Add a compact meter to the launcher header, loaded from the endpoint, with exhausted actions disabled and an upgrade link.

### Phase 2 (after phase 1 is green)
- Add usage reservations so queued work counts before success.
- Count pending + used.
- Atomic gate with a transaction/row lock.
- Release on failure, cancellation, timeout, and worker restart.

## TODO

- [x] Add migration for plan AI columns and backfill existing plans.
- [x] Update `config/billing.php`, `config/ai.php`, `PlanSeeder`, `Plan`, and `PlanFactory`.
- [x] Add `AiPlanLimits` and refactor `AiCreditService` to use plan rows with safe fallback.
- [x] Add `AiLimits::imageLimit()` and `assertImageCount()`, and update AI image endpoints.
- [x] Add `AiUsageSummary` and the `/ai/usage` endpoint.
- [x] Add the usage meter component, billing placement, and launcher integration.
- [x] Add admin plan AI limit editing.
- [x] Write and run phase 1 tests.
- [ ] Implement phase 2 reservations and their tests.
- [ ] Run Pint and the affected test suite.
