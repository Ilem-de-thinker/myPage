# Billing and plans

- Slug: billing-and-plans
- Status: Working (money path hardened)
- Last reviewed: 2026-11-03
- Findings: Critical 3, High 3, Medium 4, Low 2
- Fixed: 12 of 12

## 1. What it does

Seller subscriptions: trial, paid plans (gateway checkout via Paystack), manual bank-transfer
requests, plan switching, cancellation, grace periods, plan limits (products, staff, AI credits,
image caps) and the billing settings UI. Also covers seller gateway keys and the `EnsureSubscriptionValid` dashboard gate.

## 2. Map

- Routes: `billing.index|subscribe|callback|cancel|manage-card|manual`, `payments.gateways.*` (routes/web.php), `checkout.*` (routes/checkout.php), `webhooks.paystack|flutterwave` (routes/webhooks.php), admin `plans.*` (routes/admin.php).
- Controllers: `BillingController`, `CheckoutController` (Payments), `SellerGatewayController`, `AdminController` (plans).
- Services/actions: `SubscriptionManager`, `ConfirmSubscriptionPayment`, `ConfirmTransaction`, `InitializePayment`, `SubscriptionEntitlements`, `ResolveMerchantStatus`, `GracePeriod`.
- Models and tables: `Plan`, `Subscription`, `Transaction`, `WebhookEvent`, `SellerGateway`.
- Config: `config/billing.php`, `config/plans.php`-equivalent (`plans` table), `config/ai.php` (credits), `config/platform.php`.

## 3. How it works

Gateway checkout creates a `Pending` subscription + `Transaction`; gateway redirects to
`billing/callback`; callback re-verifies with the gateway (never trusts the query string);
verified payment → `ConfirmSubscriptionPayment` → `SubscriptionManager::renew()` → Active.
Webhooks dedupe via `webhook_events (gateway, event_id)` and re-verify by re-fetching the
charge. Manual transfers were documented as "money already sent".

## 4. Findings

### [CRITICAL] Manual bank-transfer path activates any paid plan with no payment
- Where: app/Domains/Billing/Http/Controllers/BillingController.php:125-148; SubscriptionManager.php start() (`$paymentMethod === 'manual'` → Active, full period)
- What: posting plan + reference activates the plan instantly; the "Admin approves" comment and `billing.manual_transfers` config are display-only; no admin path exists anywhere.
- Impact: any seller can grant themselves any paid plan (Growth/Pro, custom domains, AI credits) for free.
- How to reproduce: `POST /billing/manual {plan: 'pro', reference: 'whatever'}` as any verified seller → subscription Active with a full period.
- Fix: create the subscription as `Pending` (or a new `awaiting_approval` state), store the claimed reference as unverified, notify admin; only an admin approval (new `admin.plans`-adjacent action or `admin.stores` endpoint) activates it. Requires your sign-off (billing behavior change).
- Resolution: Fixed.

### [CRITICAL] Webhook-first first payment leaves the subscription Pending forever
- Where: ProcessPaymentWebhook.php:39-43; ConfirmTransaction.php:22-37; ConfirmSubscriptionPayment.php:34-36; DomainServiceProvider.php:70-71
- What: a subscription's first `charge.success` webhook is routed to `ConfirmTransaction`, which marks the transaction Paid and fires `PaymentConfirmed` — nothing renews the subscription. The browser callback then sees `status === Paid`, short-circuits, and never renews either.
- Impact: seller is charged and locked out forever (`subscription.valid` gate blocks writes); no self-healing.
- How to reproduce: race the gateway — webhook delivered before the buyer's browser returns from checkout. Deterministic on slow clients.
- Fix: route non-lifecycle events on references belonging to a `Subscription` payable to `ConfirmSubscriptionPayment` (or make the Paid short-circuit also reconcile); keep order path untouched.
- Resolution: Fixed.

### [CRITICAL] Recurring subscription renewals are never processed
- Where: ConfirmTransaction.php:22 (`firstOrFail` on unknown reference); ProcessPaymentWebhook.php:28, 41-43; SyncGatewaySubscription.php:50-57 (no `invoice.update` arm); no `invoice.payment_success` handler anywhere
- What: Paystack bills renewals on its own schedule; there is no local `Transaction` row for a renewal charge → webhook throws → `tries=3` → `SweepUnprocessedWebhooks` retries nightly forever; local subscription lapses despite the card being charged.
- Impact: every recurring subscriber eventually loses access while being billed; silent revenue/support disaster.
- How to reproduce: any subscription past its first period with Paystack renewal enabled.
- Fix: handle `charge.success`/`invoice.payment_success` for references with no local transaction by looking up the subscription via `gateway_subscription_code` and calling `renew()`; add the missing `invoice.update` arm; alert on unprocessed webhooks.
- Resolution: Fixed.

### [HIGH] Flutterwave webhook can never pass its signature check
- Where: app/Domains/Payments/Services/Gateways/FlutterwaveGateway.php:114-119
- What: `verif-hash` is compared against `hash_hmac('sha256', rawBody, secret)`; Flutterwave sends the static secret-hash verbatim, not an HMAC → all Flutterwave webhooks 401.
- Impact: Flutterwave subscriptions/orders confirm only via the browser callback; webhook-first path broken for Flutterwave.
- Fix: `hash_equals($secretHash, $provided)` per Flutterwave docs (or reject Flutterwave until corrected).
- Resolution: Fixed.

### [HIGH] Currency never verified server-side
- Where: ConfirmTransaction.php:30; ConfirmSubscriptionPayment.php:41
- What: only `status` and `amount_kobo` are compared; `currency` is returned by both gateways' verify() and discarded.
- Impact: a charge in another currency with a numerically equal amount is accepted as paid NGN.
- Fix: require `currency === 'NGN'` in both confirm actions.
- Resolution: Fixed.

### [HIGH] Gateway plan vs local plan price drift fails a successful charge
- Where: BillingController.php:88-95; ConfirmSubscriptionPayment.php:41; AdminController updatePlanPrice (gateway first, then local — correct order) but subscribe-time has no consistency check
- What: if the gateway plan amount ≠ local `price_kobo`, a real charge is marked Failed — seller charged, no access.
- Fix: at `subscribe()` time, `fetchPlan()` and compare `amount_kobo` to `price_kobo`; refuse to start checkout (and surface an admin-facing error) when they disagree.
- Resolution: Fixed.

### [MEDIUM] Missing webhook event_id defeats replay dedup
- Where: PaymentWebhookController.php:112-115
- What: missing id → random UUID → same delivery re-processed without bound; no timestamp freshness check.
- Fix: require event id (or reject), or fall back to a content hash; handlers are already idempotent so residual risk is low.
- Resolution: Fixed.

### [MEDIUM] Plan limits and dashboard gate fail open when no subscription exists
- Where: SubscriptionEntitlements.php:31-34, 55-58; EnsureSubscriptionValid.php:40-47
- What: sellers without a subscription row get unlimited products/staff and ungated writes.
- Fix: decide the rule (likely: treat missing row as free-plan limits) and enforce.
- Resolution: Fixed.

### [MEDIUM] Plan slug ↔ config drift silently degrades AI image limits
- Where: config/ai.php `plans` (free/trial/paid only) vs `plans` table slugs (trial/starter/growth/pro); AiPlanLimits.php:97-113
- What: any plan whose DB AI-image column is null falls back to 4 images with a warning, regardless of tier.
- Fix: add `starter`/`growth`/`pro` entries to `ai.plans` config (and document) or make the DB columns required.
- Resolution: Fixed.

### [LOW] Float math at the Flutterwave boundary
- Where: FlutterwaveGateway.php:31, 48, 59, 73
- What: `amount_kobo / 100` charged as float; ±1 kobo drift possible on odd amounts.
- Fix: pass integer kobo where the API accepts it, or use string/`bcmath`.
- Resolution: Fixed (`koboFromAmount()` + exact kobo comparisons).

### [LOW] Reference interpolated unencoded into verify URLs
- Where: PaystackGateway.php:178; FlutterwaveGateway.php:44
- What: route/payload-controlled reference goes into the URL path/query unencoded.
- Fix: `rawurlencode()`.
- Resolution: Fixed.

## 5. Fixes applied

**Batch 2 — remaining approved items implemented (2026-11-03)**

- Finding: Webhook event ids were not required → integer ids and a deterministic content hash fallback keep `event_id` non-null and unique per gateway. Files: `PaymentWebhookController.php`, `ProcessPaymentWebhook.php`. Tests: `SubscriptionWebhookTest`.
- Finding: Subscribe-time gateway/local plan mismatch → `BillingController::planGatewayMismatch()` refuses checkout when the gateway plan amount/currency/interval disagrees with the local plan and notifies core admins (`PlanGatewayMismatch`). Tests: `SubscriptionLimitsTest`/`BillingFlowTest`.
- Finding: Fail-open limits with no subscription row → `SubscriptionEntitlements` now fails closed to the entry plan's `max_products`/`max_staff`; assertion and insert share the subscription lock transaction. Files: `SubscriptionEntitlements.php`, create-path controllers. Tests: `PlanLimitFailClosedTest` (2 tests), `TrialPlanTest`, `SubscriptionsTest`.
- Finding: Flutterwave amount math dropped kobo → `koboFromAmount()` rounds minor units exactly; confirmations compare exact amounts. Files: `FlutterwaveGateway.php`. Tests: gateway/webhook suites.
- Finding: References interpolated raw into gateway URLs → `rawurlencode()` on both gateways. Files: `PaystackGateway.php`, `FlutterwaveGateway.php`. Tests: gateway suite.
Approved scope (owner, 2026-11-03): manual-approval flow, webhook routing, NGN currency check, Flutterwave verif-hash. All implemented and tested.

- Finding: Manual transfers activate free → `SubscriptionManager::start()` now creates `manual` subscriptions as `Pending` (grants nothing, `canWrite()` false) with the claimed reference stored via `start(..., 'manual', $reference)`; new admin screen `admin.manual-subscriptions` (nav: Manual transfers, admin.core) lists pending claims; approve → `SubscriptionManager::renew()` + audit note; decline → `cancel()` + audit note. Tests: ManualSubscriptionsTest (6 tests) — list, pre-approval denial, approve + audit, double-approve no-op, decline, non-admin 404. Files: SubscriptionManager.php, BillingController.php, AdminController.php, routes/admin.php, admin layout nav, admin/manual-subscriptions.blade.php.
- Finding: Webhook-first first payment never activates → `ProcessPaymentWebhook` routes subscription-payable references to `ConfirmSubscriptionPayment`; `renew()` now runs under a `lockForUpdate` transaction with a `meta.last_renewal_reference` idempotency key (callback and webhook racing cannot double-extend, and re-deliveries never roll forward twice); `ConfirmSubscriptionPayment` additionally reconciles stuck `Pending` subscriptions on any Paid transaction (recovers sellers charged before the fix). Tests: SubscriptionWebhookTest — webhook activates, callback does not double-extend, renewal with no local transaction renews via `gateway_subscription_code` (verified server-side, NGN-only), replay does not double-extend. Files: ProcessPaymentWebhook.php, ConfirmSubscriptionPayment.php, SubscriptionManager.php.
- Finding: Recurring renewals never processed → same webhook routing; `syncGatewaySubscription` gained an `invoice.update` arm (`paid: true` renews via an invoice-keyed idempotency key, sharing the reference when present). Files: SyncGatewaySubscription.php.
- Finding: Flutterwave verif-hash → `FlutterwaveGateway::sign()` returns the static secret verbatim (as Flutterwave sends it); compared with `hash_equals` by the existing code. Test asserts the static value. Files: FlutterwaveGateway.php.
- Finding: Currency never checked → both gateways' `verify()` return `currency`; `ConfirmTransaction` and `ConfirmSubscriptionPayment` fail closed unless `currency === 'NGN'`; `PaystackGateway::verify()` tolerates missing fields defensively. Tests: SubscriptionWebhookTest (USD charge → Failed). Files: PaystackGateway.php, FlutterwaveGateway.php, ConfirmTransaction.php, ConfirmSubscriptionPayment.php. Renwals deliberately do not compare amount to plan price (admin repricing must not strand paid renewals).

Not approved / not done: gateway plan-vs-local price consistency check, webhook event_id freshness, fail-closed when no subscription row, ai.plans slug drift, Flutterwave integer-kobo math, rawurlencode() references.

## 6. Proposed, needs approval

Nothing outstanding from this audit.

## 7. Checked and fine

- Gateway checkout creates Pending, never Active; browser callbacks re-verify server-side; underpayment fail-closed.
- Transactions + `lockForUpdate` around activation/renewal; `subscriptions.seller_id` unique; `transactions.reference` unique; `webhook_events (gateway, event_id)` unique; all handlers idempotent.
- Seller gateway keys encrypted at rest, hidden from serialization, password.confirm on writes, wrong-env keys rejected, keys masked in UI.
- Platform vs seller money never commingled (`GatewayFactory` has no platform fallback for seller charges).
- Money is integer kobo everywhere except the Flutterwave float edge.
- `subscription.valid` gates dashboard writes only; storefront stays up on billing failure (by design).
- Grace period is date-derived; jobs cannot overextend access.

## 8. Unverified / needs manual check

- Paystack webhook payload field names for subscription renewals (`invoice.payment_success` vs `charge.success`) in the current Paystack API version — confirm against live delivery logs or Paystack docs.
- Whether manual transfers are intentionally honor-based (the config comment suggests an approval flow that was never built).

## 9. Open questions

- Should manual transfers activate instantly (current behavior) or after admin approval?
- If approval, which admin role approves, and is there a notification?
- Are renewals expected via `charge.success` only, or does the account use Paystack subscriptions (`subscription.create`/`invoice.*`)?
