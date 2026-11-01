# Trial Plan

## Goal

Give every new seller a single 14-day trial on a dedicated **Trial** plan, with
a deliberately smaller feature set. When the 14 days end the trial **stops**:
there is no grace period after a trial, so the dashboard becomes read-only and
the storefront stops accepting new orders until the seller buys a paid plan.

Paid plans are bought through the existing billing checkout and can be re-priced
from the admin panel (already implemented).

## Plan

| | Trial | Starter | Growth | Pro |
|---|---|---|---|---|
| Price | ₦0 | ₦3,500/mo | ₦9,500/mo | ₦25,000/mo |
| Length | 14 days, then stops | monthly | monthly | monthly |
| Listings | 10 | 25 | 200 | unlimited |
| Staff members | 1 | unlimited | unlimited | unlimited |
| AI credits / month | 50 | 500 | 500 | 500 |
| Custom domain | no | no | no | yes |
| Featured directory placement | no | no | yes | yes |
| Storefront, checkout, bookings, dashboard | yes | yes | yes | yes |

The trial plan is the `is_default` plan. It is not shown as a purchasable card on
the landing page or the billing upgrade list — it is only ever started
automatically.

## Behaviour

1. **Registration** creates the seller and starts a Trial subscription
   (`trialing`, `trial_ends_at = now + 14 days`).
2. During the trial the seller has full dashboard and storefront access, capped
   at 10 listings, 1 staff member and 50 AI credits. Custom domains and featured
   directory placement are unavailable because the trial plan slug is not in
   `billing.custom_domain_plans` or `billing.directory_featured_plans`.
3. **At the end of day 14** `ResolveMerchantStatus` returns `Expired` for a
   trialing subscription whose `trial_ends_at` has passed. No grace is derived
   from the trial. Only an explicit admin `grace_override_until` can extend it.
4. Expired means:
   - dashboard reads still work (orders, customers, data export);
   - dashboard writes are blocked and redirect to billing;
   - storefront stays visible but checkout/booking writes return 402.
5. **Buying a paid plan** starts a gateway checkout. The seller's existing
   subscription is left untouched until the payment is confirmed, so an
   abandoned checkout never changes their access. A confirmed payment activates
   the paid plan and clears the trial dates.

## Implementation

### Data

- Migration `add_max_staff_to_plans_table` (`max_staff` nullable = unlimited).
- `Plan` model: `max_staff` fillable/cast; `PlanSeeder`, `PlanFactory`.
- `config/billing.php`: add the `trial` plan, mark it `is_default`, remove
  `is_default` from Growth, set paid plans' `trial_days` to 0, add
  `billing.trial_plan`.
- `config/ai.php`: add `plans.trial.monthly_credits = 50`.

### Billing lifecycle

- `SubscriptionStatus`: add `Pending` (checkout started, nothing paid yet).
- `ResolveMerchantStatus`:
  - `Pending` → `Expired` (no writes) unless an admin override is active;
  - `Trialing` past `trial_ends_at` → `Expired` with **no derived grace**
    (admin override still honoured).
- `SubscriptionManager::start`:
  - `paymentMethod = 'trial'` always starts a trial;
  - a gateway checkout for a seller with no subscription and a trial-bearing
    plan starts a trial (legacy path);
  - a gateway checkout for a seller who already has a subscription keeps their
    current status and dates until the payment is confirmed;
  - a gateway checkout with no subscription and no trial is `Pending`.
- `SubscriptionManager::renew` clears `trial_ends_at` once paid.
- `RegisterSeller` starts the trial on the configured trial plan (skips silently
  if plans have not been seeded, so registration never fails for billing).

### Entitlements

- `SubscriptionEntitlements::assertCanCreateStaff` / `staffLimit`, enforced in
  `StaffMemberController::store`.
- `AiCreditService` resolves credits from the subscription's plan slug, falling
  back to the free/paid buckets for plans without an explicit entry.
- Onboarding starter drafts are capped to the plan's listing limit.

### Visibility

- `BillingController::index` and `PricingPlans` exclude the trial plan, so it is
  never offered as something to buy.

### Admin

Plan price editing already exists (`AdminController::updatePlanPrice` + admin
Plans screen) and continues to work for the paid plans. The trial plan is free
and is not connected to the gateway.

## Tests

`tests/Feature/Billing/TrialPlanTest.php`:

- registration starts a 14-day trial on the trial plan;
- the trial allows 10 listings and blocks the 11th;
- the trial allows 1 staff member and blocks the second;
- trial AI credits are 50, not the paid 500;
- custom domains and featured directory placement are unavailable on trial;
- day 14 is a hard stop: `Expired`, no writes, no grace;
- an admin override can still extend a trial;
- subscribing after the trial stays blocked until payment is confirmed, then
  becomes active;
- the trial plan is absent from the billing upgrade list.

Existing subscription tests are updated where the new "pay before access"
checkout flow changes their expectations.
