# Platform Access and Selling Plan

Four features, implemented in this order:

1. Subscription status and grace period
2. Core/staff admin roles and audit log
3. Product and service sale pricing
4. Admin-managed AI settings

Do not start a step until the previous one is reviewed and confirmed.

## Status

- **Plan 1 — subscription status and grace: implemented, awaiting review.**
  Read-only public storefront was chosen; the platform grace default and
  per-merchant override are in place and audited.
- **Plan 2 — core/staff admin roles and audit: implemented, awaiting review.**
  `admin_role` is backfilled from `is_admin`; staff keep operations, core-only
  routes cover settings/plans/features/gateway/team/grace; role changes are
  locked, audited, and cannot remove the last active core admin.
- **Plan 3 — sale pricing: implemented, awaiting review.**
  `price_kobo` remains the charged price and `compare_at_kobo` the original;
  nullable sale windows are evaluated on read. Product/service dashboards,
  storefront cards and detail pages, search, AI grounding, JSON-LD, WhatsApp,
  directory cards, booking widget, and Help Me Buy all use the charged price and
  show the original only while a sale is live.
- **Plan 4 — AI settings: implemented, awaiting review.**
  One global provider/model/key drives every task, with env fallback. The key is
  encrypted at rest, write-only and masked, settings are core-admin only and
  audited without secrets, connection tests are available, and per-feature
  monthly caps block only the capped task.

---

## 1. Subscription status and grace period

### Goal

Give every merchant a single, predictable status — **active**, **grace**, **expired**
(plus **cancelled** when they stop) — derived from real dates and payment truth,
with an admin override and a platform default. The seller always knows where they
stand and what happens next.

### Current state

- Statuses live in `app/Domains/Billing/Enums/SubscriptionStatus.php`:
  `trialing | active | past_due | cancelled | expired`.
- Grace already exists through `grace_ends_at`, `SubscriptionManager::markPastDue()`,
  `endTrial()`, and `ExpireSubscriptions`.
- Recurring payments are already confirmed from signed, deduplicated webhooks in
  `PaymentWebhookController`, `ProcessPaymentWebhook`, `WebhookEvent`.
- Access logic is scattered:
  `Subscription::canWrite()/isExpired()`, `EnsureSubscriptionValid`,
  `AiCreditService`, `DirectoryPlacement`, `DomainBindingController`,
  `ExpireSubscriptions`.
- There is no per-merchant grace override, no platform-level grace setting, and
  the public storefront is never gated.

### Design decisions

- The UI/status layer uses `MerchantStatus`: `active`, `grace`, `expired`, `cancelled`.
  The database keeps `SubscriptionStatus` for gateway mapping and history.
- One action, `ResolveMerchantStatus`, is the only authority for current status.
  Jobs may still update the stored status, but gating never waits for a job.
- Grace **allows writes**; expired and cancelled do not.
- Public storefront on expiry is **read-only** (recommended): pages and catalogue
  stay visible, a notice is shown, and cart/checkout/booking writes are blocked.
  No 404 and no mutation of the seller's store row. (Alternative: hide the store
  with a 404 — confirm before implementation.)

### Changes

1. **Migration** `add_access_overrides_to_subscriptions`
   - `grace_override_until` nullable timestamp.
2. **Enum** `app/Domains/Billing/Enums/MerchantStatus.php`
   - `Active`, `Grace`, `Expired`, `Cancelled` with label, badge classes, and
     `allowsWrites()`.
3. **Action** `app/Domains/Billing/Actions/ResolveMerchantStatus.php`
   - No subscription row → `Active` (fail open, same as today).
   - Active/trial and the paid period still running → `Active`.
   - Trial or paid period ended, `past_due`, or payment failed, within
     `grace_override_until` / `grace_ends_at` → `Grace`.
   - Grace elapsed → `Expired`.
   - Cancelled while the paid period still runs → `Active`; afterwards `Cancelled`.
   - `grace_override_until` always extends, never shortens, access.
4. **Model**
   - `Subscription::merchantStatus()`, `canWrite()` delegate to the resolver.
   - Keep `isExpired()` as a compatibility shim over the resolver.
5. **Platform default grace**
   - Move the default from `config('billing.grace_days')` to
     `PlatformSetting('billing.grace_days')` with config fallback.
   - Read it in `SubscriptionManager::markPastDue()` / `endTrial()`.
   - Editable in `resources/views/admin/settings.blade.php`.
6. **Per-merchant override**
   - Input on the admin stores screen (`resources/views/admin/stores.blade.php`).
   - Applied through a small action, audited with `RecordAdminAction`.
   - Cleared automatically on a confirmed renewal.
7. **Gating**
   - `EnsureSubscriptionValid`: use the resolver; allow grace, block
     expired/cancelled with 402.
   - `AiCreditService`, `DirectoryPlacement`, `DomainBindingController`: use
     `merchantStatus()` instead of raw status checks.
   - Public storefront: middleware on checkout/booking/write routes in
     `routes/checkout.php` and `routes/storefront.php` blocks writes when the
     status is expired/cancelled; a shared notice is shown on storefront pages.
   - Keep dashboard reads available so sellers can export data and pay.
8. **Seller clarity**
   - Status badge and "X days left" on the dashboard and
     `resources/views/dashboard/billing/index.blade.php`.
   - Email when grace starts and when it is about to end (reuse existing
     notification patterns, honour `billing.notifications.grace_reminder_days`).
   - Empty/unknown subscription stays active, as today.
9. **Webhooks**
   - Keep signature verification and event-id deduplication.
   - Ensure subscription lifecycle events clear overrides on renewal.
   - Add regression tests for invalid signatures and replays.

### Files

- `app/Domains/Billing/Enums/SubscriptionStatus.php`
- `app/Domains/Billing/Enums/MerchantStatus.php` (new)
- `app/Domains/Billing/Actions/ResolveMerchantStatus.php` (new)
- `app/Domains/Billing/Actions/SubscriptionManager.php`
- `app/Domains/Billing/Actions/SyncGatewaySubscription.php`
- `app/Domains/Billing/Actions/ConfirmSubscriptionPayment.php`
- `app/Domains/Billing/Models/Subscription.php`
- `app/Domains/Billing/Jobs/ExpireSubscriptions.php`
- `app/Domains/Billing/Services/SubscriptionAvailability.php`
- `app/Domains/Billing/Http/Controllers/BillingController.php`
- `app/Domains/Shared/Middleware/EnsureSubscriptionValid.php`
- `app/Domains/Ai/Services/AiCreditService.php`
- `app/Domains/Directory/Services/DirectoryPlacement.php`
- `app/Domains/Domains/Http/Controllers/DomainBindingController.php`
- `routes/checkout.php`, `routes/storefront.php`
- `resources/views/dashboard/billing/index.blade.php`
- `resources/views/admin/settings.blade.php`
- `resources/views/admin/stores.blade.php`
- `resources/views/themes/**` (shared storefront notice)

### Tests

- Resolver matrix: no row, trial, active, period ended, past due, grace override,
  cancelled with paid time left, cancelled after period, expired.
- Grace allows dashboard writes; expiry blocks writes with 402.
- Expired public storefront is read-only (or hidden, per decision).
- Admin override extends access, is audited, and clears on renewal.
- Platform grace default is honoured and editable.
- Invalid webhook signature returns 401; a replayed event is processed once.
- Subscription lifecycle webhooks move status correctly.

---

## 2. Core/staff admin roles and audit log

### Goal

Replace the single `is_admin` boolean with two roles — **core_admin** and
**staff_admin** — enforced on the server, with every sensitive action audited.

### Current state

- Admin access is `Seller.is_admin` only.
- `EnsureUserIsAdmin` aborts 404 for non-admins and enables `CrossTenant`.
- `AdminAuditLog` is immutable and written through `RecordAdminAction`.
- The audit UI already exists at `admin/audit`.
- There is no team page and no role distinction.

### Role split

| Area | core_admin | staff_admin |
| --- | --- | --- |
| Dashboard, analytics, badges, stores, transactions, reports, support | yes | yes |
| Audit log (read) | yes | yes |
| Platform settings, plans, feature flags, gateway health | yes | no |
| AI settings | yes | view status only |
| Admin team and role changes | yes | no |
| Per-merchant grace override | yes | no |
| Suspensions / reinstatements | yes | yes |

### Changes

1. **Migration** `add_admin_role_to_sellers`
   - `admin_role` nullable string, indexed.
   - Backfill `is_admin = 1` → `core_admin`.
   - Keep `is_admin` as the access flag and sync it from the role during the
     transition; new code uses `Seller::isAdmin()/isCoreAdmin()/isStaffAdmin()`.
2. **Enum** `app/Domains/Admin/Enums/AdminRole.php`
   - `CoreAdmin`, `StaffAdmin`.
3. **Model**
   - `Seller::adminRole()`, `isAdmin()`, `isCoreAdmin()`, `isStaffAdmin()`.
   - Factory states for both roles.
4. **Middleware**
   - `EnsureUserIsAdmin` uses `isAdmin()` (unchanged 404 for non-admins).
   - New `EnsureUserIsCoreAdmin` alias `admin.core` → 403 for authenticated staff.
5. **Routes** `routes/admin.php`
   - Apply `admin.core` to settings, plans, features, gateway health, AI settings,
     admin team, role changes, grace overrides.
   - Staff keep the operational routes.
6. **Team management**
   - New admin page listing and searching admins.
   - Promote/demote through `AdminRoleManager` in a transaction with
     `lockForUpdate`.
   - Refuse to remove the last active core admin or self-demote when last.
   - Audit `admin.role.granted` / `admin.role.revoked` with the acting admin.
7. **Audit coverage**
   - Role changes, AI settings, plan/price changes, grace overrides, suspensions.
   - Reuse `RecordAdminAction`; never log secrets or API keys.
   - Audit is written before the action takes effect.
8. **2FA**
   - Deferred: the current auth stack is email OTP/password and has no 2FA
     infrastructure. Noted here rather than faked.

### Files

- `database/migrations/*_add_admin_role_to_sellers.php` (new)
- `app/Domains/Admin/Enums/AdminRole.php` (new)
- `app/Domains/Admin/Actions/AdminRoleManager.php` (new)
- `app/Domains/Admin/Http/Controllers/AdminTeamController.php` (new)
- `app/Domains/Admin/Middleware/EnsureUserIsCoreAdmin.php` (new)
- `app/Domains/Admin/Middleware/EnsureUserIsAdmin.php`
- `app/Domains/Seller/Models/Seller.php`
- `app/Domains/Seller/Models/StorePage.php` (only if role checks are needed)
- `routes/admin.php`
- `resources/views/admin/team.blade.php` (new)
- `resources/views/admin/layout.blade.php` (nav visibility)
- `bootstrap/app.php` (middleware alias)

### Tests

- Staff are blocked (403) from core routes; non-admins still get 404.
- Core can promote/demote; changes are audited.
- The last active core admin cannot be demoted; self-demotion is refused when last.
- Staff can still use operational admin routes.
- Audit entries remain immutable.

---

## 3. Product and service sale pricing

### Goal

Let sellers put a product or service on sale with an original price, a sale price,
and an optional sale window. The sale is used consistently on storefront pages,
search, WhatsApp messages, and Help Me Buy ranking.

### Current state

- Products already have `compare_at_kobo`, `isDiscounted()`,
  `discountPercent()`, and card/show rendering.
- There is no seller form field for compare-at and no sale window.
- Services have no compare-at price at all.
- Cart and checkout already charge `price_kobo`, which will remain the sale price.

### Data model

| Field | Products | Services |
| --- | --- | --- |
| `price_kobo` (sale/charged price) | exists | exists |
| `compare_at_kobo` (original) | exists | add |
| `sale_starts_at` | add | add |
| `sale_ends_at` | add | add |

### Changes

1. **Migrations**
   - products: `sale_starts_at`, `sale_ends_at`.
   - services: `compare_at_kobo`, `sale_starts_at`, `sale_ends_at`.
2. **Shared sale behaviour** (`App\Shared\Support\HasSalePrice` trait or
   equivalent helper)
   - `isOnSale()`: compare-at > charged price and now within the optional window.
   - `originalPriceKobo()`, `effectivePriceKobo()`, `saleEndsAt()`,
     `saleLabel()`, `discountPercent()`.
   - Computed on read; no scheduled job and no stale flags.
3. **Seller forms and validation**
   - `resources/views/dashboard/products/form.blade.php`
   - `resources/views/dashboard/services/form.blade.php`
   - `ProductController`, `ServiceController`:
     compare-at > 0, sale price < compare-at, start before end, positive prices.
4. **Rendering**
   - Store product card/show: crossed-out original, "Sale" badge, optional
     "ends in" (already partly present — make it window-aware).
   - Service cards/show and home blocks.
   - Store search results and AI store search.
   - Help Me Buy/directory payloads (`price`, `priceValue`) so ranking uses the
     effective price.
   - AI grounding (`StoreGrounding`) includes original/effective/ends.
   - WhatsApp order/share messages and any structured data/JSON-LD.
   - Dashboard listing tables.
5. **Budget filters**
   - Keep filtering on the charged `price_kobo`; not on compare-at.
6. **Expiry**
   - An expired or future sale simply renders and ranks at the normal price.

### Files

- `database/migrations/*_add_sale_window_to_products.php` (new)
- `database/migrations/*_add_sale_pricing_to_services.php` (new)
- `app/Domains/Catalog/Models/Product.php`
- `app/Domains/Booking/Models/Service.php`
- `app/Shared/Support/HasSalePrice.php` (new)
- `app/Domains/Catalog/Http/Controllers/ProductController.php`
- `app/Domains/Booking/Http/Controllers/ServiceController.php`
- `resources/views/dashboard/products/form.blade.php`
- `resources/views/dashboard/services/form.blade.php`
- `resources/views/themes/store/partials/product-card.blade.php`
- `resources/views/themes/store/product-show.blade.php`
- `resources/views/themes/service/**` (cards, show, blocks)
- `app/Domains/Ai/Http/Controllers/StoreSearchController.php`
- `app/Domains/Ai/Services/StoreGrounding.php`
- `app/Domains/Directory/Http/Controllers/MarketplaceAssistantController.php`
- `app/Domains/Seller/Http/Controllers/StorefrontController.php` (search result)
- `app/Domains/Orders/Actions/GenerateWhatsAppOrderLink.php`
- `app/Domains/Catalog/Http/Requests/*` (if sale filters are added)

### Tests

- Validation rejects sale ≥ original and end before start.
- Active sale shows both prices and the badge; future/expired sale does not.
- Services support sale pricing end to end.
- Search, HMB payload, AI grounding, and WhatsApp messages use the effective price.
- Budget filters still use the charged price.
- Existing product tests keep passing.

---

## 4. Admin-managed AI settings

### Goal

Let a core admin change the AI provider, model, and API key from the admin panel
without a deploy, while keeping keys encrypted, write-only, masked, testable, and
audited. Cap AI usage per merchant per feature.

### Current state

- Provider/model/key come only from env (`config/ai.php` and Prism's package
  config). Keys are not stored in the database.
- `PrismAiManager` already has fallbacks, timeouts, retries with backoff,
  structured output validation plus one repair retry, and `AiRun` logging.
- `AiCreditService` caps by **monthly total per merchant**, not per feature.
- `AiFeatureRegistry` maps features to tasks, plans, and business types.

### Settings

| Key | Type | Notes |
| --- | --- | --- |
| `ai.enabled` | boolean | master switch |
| `ai.provider` | text | Groq, OpenRouter, etc. |
| `ai.model` | text | active model |
| `ai.api_key` | encrypted | write-only, masked on read |
| `ai.timeout` | integer | seconds |
| `ai.retries` | integer | attempts with backoff |
| `ai.feature_limits` | json | optional per-feature monthly caps |

Environment variables remain the fallback when a setting is unset.

### Changes

1. **Settings storage**
   - Extend `PlatformSetting` with an `encrypted` type using
     `Crypt::encryptString` / `decryptString`.
   - Cache is cleared on every write, as today.
2. **`AiSettings` service**
   - Read effective values with env fallback.
   - Expose `provider`, `model`, `enabled`, `timeout`, `retries`, `limits`,
     masked key, and `configured` flag.
   - Never expose the raw key outside the manager.
3. **Admin UI**
   - Core-only AI settings page (inside admin settings or its own page).
   - Provider select, model, API key password field (blank = keep existing),
     timeout, retries, enabled toggle, per-feature limits.
   - "Test connection" action that performs a minimal provider call and reports
     success or the provider error.
   - Staff admin can see the active model/status only; no key, no save.
   - Every change is audited without the key value.
4. **Runtime wiring**
   - `PrismAiManager` applies `AiSettings` before each run:
     - override `ai.tasks.*.provider/model`;
     - override `config('prism.providers.<provider>.api_key/url')`;
     - apply timeout and retries.
   - Fall back to existing env/config when a setting is absent.
5. **Per-feature caps**
   - `AiCreditService::assertAvailable($page, $task)` counts `AiUsageLedger`
     for the current month by task.
   - Limits resolve from `AiSettings` / `config('ai.feature_limits')`.
   - Return 429 naming the feature; do not block unrelated features.
   - Keep the existing monthly total cap as an outer bound.
6. **Reliability (keep existing)**
   - Structured output validation and repair retry.
   - Timeouts and retries with backoff on 429/5xx.
   - No secrets in logs, audit entries, or error messages.
7. **Audit**
   - `ai.settings.updated` with changed keys only (no values for the key).
   - `ai.settings.tested` with provider/model and result, no secrets.

### Files

- `app/Domains/Admin/Models/PlatformSetting.php`
- `app/Domains/Ai/Services/AiSettings.php` (new)
- `app/Domains/Ai/Services/PrismAiManager.php`
- `app/Domains/Ai/Services/AiCreditService.php`
- `app/Domains/Ai/Http/Controllers/AiSettingsController.php` (new)
- `app/Domains/Ai/Support/AiFeatureRegistry.php` (limit keys)
- `config/ai.php` (limit defaults, env fallback)
- `routes/admin.php`
- `resources/views/admin/settings.blade.php` or
  `resources/views/admin/ai-settings.blade.php` (new)
- `resources/views/admin/layout.blade.php` (nav visibility)
- `.env.example` (document that settings override env)

### Tests

- Core admin can save settings; staff gets 403.
- Key is encrypted in the database and never rendered in full.
- A blank key leaves the existing key unchanged.
- Masked value and configured flag are correct.
- Cache is cleared immediately after save.
- Env fallback works when no DB setting exists.
- Test connection reports success and failure without leaking the key.
- Per-feature cap blocks only the capped feature.
- Audit entries are written and contain no secret.

---

## Implementation order and checkpoints

1. **Subscription status + grace** — implement, run
   `vendor/bin/pint --dirty --format agent` and the Billing/Admin tests, then
   **stop for confirmation**.
2. **Roles + audit** — implement, test, then **stop for confirmation**.
3. **Sale pricing** — implement, test, then **stop for confirmation**.
4. **AI settings** — implement, test, then final review.

## Open decisions

1. Expired public storefront: **read-only** (recommended) or hidden with a 404.
2. AI settings: one global provider/model/key for all tasks (recommended), with
   env per-task fallback, or per-task provider/model pickers.

## Verification

- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/pest --compact <affected paths>`
- `npm run build` only if frontend assets change.
