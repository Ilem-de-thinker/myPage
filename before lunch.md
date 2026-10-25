# mypage.ng — before lunch

Consolidated working state, 2026-10-16, with a payment-architecture decision
recorded on 2026-10-23. Each section is marked with where it came from so nothing
here reads as more settled than it is.

**Read this first if you only read one thing:** the *Payment architecture* section
immediately below. It reverses the escrow, wallet, commission and payout design
that the rest of this document spends a great deal of effort on. Everything after
it that concerns buyer money describes how the code got here, not where it is
going.

Second: the *Launch gate* at the end of §2, rewritten on the same date. It is
shorter than it was, because three of its items were about moving money the
platform no longer touches.

Two things deliberately **not** merged, because they are long references rather
than working state:

- `MYPAGE.NG — Implementation Plan (Phase 0 to Launch) (1).md` — the build plan
- `mypage-ng-laravel13-project-plan (1).md` — directory structure and schema

Also still on disk and still current: `docs/booking-system.md`,
`docs/ui-ux-spec.md`, `AGENTS.md`, `CLAUDE.md`, `README.md`, `todo.md`.
`docs/escrow.md` is now historical — see its banner and the section below.

> `README.md` is still the stock Laravel readme. It is listed as current above
> because it was, not because it says anything about this product. Worth replacing
> before launch.

---

<!-- source: decision, 2026-10-23 -->

## Payment architecture — decided 2026-10-23

This supersedes every escrow, wallet, commission and payout statement in this
file and in the implementation plan. Where they disagree with this section, this
section wins. Nothing in the older sections has been deleted: it is history, and
it explains why the code looks the way it does.

Two money flows, and they never meet.

1. **Seller → mypage.ng.** The platform's own Paystack account, for the monthly
   subscription. This is the **only** money mypage.ng holds. Everything in
   *"Required from you" §A* still applies unchanged: plan codes, the webhook URL,
   a running queue worker, live keys and a correct `APP_URL`.
2. **Buyer → seller.** The **seller's own** Paystack account, using credentials
   the seller enters in their dashboard settings. The money lands in the seller's
   Paystack balance. mypage.ng never holds it, never splits it, never escrows it
   and never pays it out.

**Bank transfer is refused as a buyer payment method.** It is not offered, not
recorded, and cannot be accepted as proof of payment. No order on mypage.ng can
reach `paid` by a seller ticking a box. Every buyer payment the platform knows
about is a gateway transaction with a verifiable reference. This is a tracking
decision first and a fraud decision second: an untracked transfer is a payment
nobody can reconcile or refund.

### What this deletes

- **Escrow, in every form** — hold, release, auto-release, freeze, dispute
  arbitration, buyer release tokens, and the whole OTP-on-release security plan.
  There is no platform-held money to release.
- **Wallet, ledger and commission.** `wallets`, `wallet_ledger`,
  `CommissionCalculator`, the double-entry rules. The seller's balance is in
  their own Paystack account; the platform has no balance to track.
- **Payouts.** `RequestPayout`, `ProcessPayout`, payout bank details, the payout
  queue, the withdrawal screen. There is nothing to withdraw from.
- **Platform-executed refunds.** A refund happens in the seller's own Paystack
  account. The platform can record that one was agreed; it cannot move the money.
- `FEATURE_ESCROW` and the platform-wide buyer `PAYMENT_GATEWAY` setting.

Removing these is a **deletion, not a flag**. The lesson recorded under BLOCKER 1
applies exactly: a dead control left in place will be "fixed" by someone later.

### What survives

- `Order` and `Booking`, their statuses, history, events and notifications. Order
  tracking, order inbox, abandoned carts and the customer book are unaffected.
- Checkout — but it initialises against the **seller's** credentials, on the
  **seller's** subdomain, and the callback returns there.
- Webhooks — but **per tenant**, each verified with that seller's own secret.
- Subscription billing, exactly as built.
- The verified badge — now a buyer-facing trust signal, not a money gate.

### New requirements this creates

1. **Subdomain routing is enforced.** `{store}.mypage.ng` is the canonical
   storefront address, so the seller's name comes first and the buyer is plainly
   buying from that business. `STOREFRONT_PATH_ROUTES` goes to `false`.
   `{slug}.mypage.ng` must be resolvable by host, and the apex routes must not
   answer on a tenant host. This also removes the custom-domain TLS problem for
   every seller on a subdomain (one wildcard certificate).
2. **Seller gateway credentials.** A dedicated store, encrypted at rest, written
   only by a dedicated action, hidden from serialisation, with the key proven
   against the gateway before it is saved. See the storage rules under *"HIGH 4"*
   below, which now describes a different thing.
3. **Per-tenant webhooks.** With per-seller keys, an event can only be verified
   with that seller's secret, so delivery lands on
   `{store}.mypage.ng/webhooks/paystack` and the tenant is resolved from the host.
   **This is the operational cost of the decision**: every seller must paste that
   URL into their own Paystack dashboard, and the ones who do not will have paid
   orders that never settle. It needs a dashboard checklist item and a visible
   warning on the seller's gateway settings screen.
4. **A buyer-facing trust marker on unverified sellers.** Today the badge renders
   *nothing* when a seller is unverified, and silence reads as "nothing to see".
   It must be explicit: "Not verified by mypage.ng" on the page, on product
   cards, and at the moment of ordering.
5. **A way for a buyer to report a store to mypage.ng, and a way to reach the
   store's own support.** Both are prerequisites for a marketplace that no longer
   arbitrates payment disputes: reporting is the only route a wronged buyer has to
   the platform. See *"Reporting and support"* below.

### Verification changes meaning

An unverified seller keeps full access to their dashboard, can build their shop,
and **can still take payments**. Verification is a trust signal, not a payment
switch. Confirmed in the 2026-10-23 conversation.

- Checkout still works for an unverified seller. The buyer is told, plainly and
  before paying, that the store is **not yet verified by mypage.ng** — the money
  goes to the seller, not to the platform, so the platform cannot vouch for it.
- Their public page, product cards, checkout and order screen carry an unmissable
  "Not verified by mypage.ng" marker. Silence must not read as approval.
- Verification still buys something worth having: a visible tick, higher
  directory placement (the directory already sorts on `verified_at`), and the
  absence of the warning at checkout.

The warning is the whole mechanism. It has to be legible to someone who is about
to enter card details, which means it belongs next to the pay button, not in a
footer.

### Reporting and support

- **Report a store to mypage.ng.** A report form reachable from every storefront
  and every order. Categories: fraud, goods never arrived, not as described,
  misleading listing, payment problem, other. It writes a row the seller cannot
  see, with the reporter's identity withheld from the seller. The admin panel
  gets a queue with counts; a store that accrues unresolved reports is visible
  platform-wide, which is the thing the old admin had no view of.
- **Support to the store.** The store's own contact routes — WhatsApp, public
  email, contact form — are the buyer's first line and must be present on every
  page of both themes, not only the contact page.
- **Support to mypage.ng.** A platform contact and an address on the legal pages,
  plus the report form, is the minimum. A ticket inbox is not required for launch.
- A report is **not** a dispute and the platform does not adjudicate money. It is
  a moderation input: it can lead to suspension, never to a transfer.

---




<!-- source: lwft.txt -->

## Reviews: what landed, and the launch assessment


Implementation complete. Here's what landed, then the launch answer.
What I just built
Reviews — a real subsystem replacing seller-typed testimonials. The existing reviews block on both themes was the seller writing their own customers' praise under a heading that said "Reviews". Worth keeping, but it was never a review and nothing aggregated it.
- reviews table — morph to business / product / service, rating 1–5, is_anonymous, is_featured, pending → approved → rejected, published_at
- Cached rating + review_count on pages, products and services. A cached column, not a COUNT per row — twelve products would otherwise be thirteen queries to draw a screen of stars
- HasRating trait — one recalculateRating() shared by all three reviewables, because "a count must only contain approved rows" has to hold identically on every one
- Everything lands pending. Buyer-authored text on a public page, with a score derived from it, is a legal problem- Recalculated on every status change including declining — a score that only ever climbs is the one number nobody should trust
- Forms on product detail, service detail, and the contact page of both themes
- /dashboard/reviews — status tabs with counts, Publish / Decline / Pin, nav badge with the waiting number
- Star summaries on product cards, service cards, discovery cards
- Home blocks now show real reviews first, falling back to typed testimonials labelled "written by {name}"
- Anonymous → "A customer", never an empty name
Three bugs I hit and fixed:
1. create() inserts immediately — associate() after it is an INSERT that fails before it can be corrected. Build-then-associate-then-save.
2. POST /reviews collided with the seller's GET /reviews inbox, which is registered first → 405. Moved to /reviews/business.
3. The nav badge counted reviews globally. Scoped to the page.
Testing left alone as asked — todo.md §16.10 tracks it. I've ticked everything in §16.9b.
Are we ready to launch?
Not yet — but it's close, and the gap is narrower than it was an hour ago. Honest split:
Ship-blocking
1. No tests on the reviews subsystem. 17 tests are written and unrun. Two of the paths I fixed above were caught by hand, not by CI — which is exactly the failure mode your own todo.md warns about: "a green engine is not a working product." Run them before this ships.
2. Three overlapping public surfaces. /, /directory, /discover. /directory and /discover both list the same businesses and are both behind separate flags. A cold visitor has no idea which to use, and the landing page now links both. Pick one to be canonical and either retire the other or give it a distinct job.
3. Admin has no view of the platform. Sellers moderate their own reviews; there's no cross-seller moderation, no review reporting, no way to see what's published platform-wide. First real abuse arrives in week one.
Should-fix before launch
4. The design debt in docs/ui-ux-spec.md §7. The two that actually cost money: no test coverage on the new listing pages, and the stock Laravel paginator — grey/blue, wrong palette, pasted into every list.
5. Review fraud is trivially easy. Five submissions a minute, per IP, forever, with no purchase verification. A competitor can bury you in one-star reviews. At minimum: rate-limit by more than IP, and add a purchase-token path for verified-buyer reviews.
6. is_featured has no admin UI. The column exists, the ordering honours it, nobody can set it except forceFill in the seeder.
Can wait
Sanctum API (intentionally last), TLS for custom domains (needs a real edge), WhatsApp Cloud API (deferred with its reasoning recorded), CSP enforcement.
My recommendation
Soft launch, not public launch. Turn on FEATURE_DISCOVERY for a handful of sellers, run the reviews tests, and decide the /directory vs /discover question — that one is a product decision only you can make, and everything else on the blocking list is a sprint.


---

<!-- source: SECURITY.md -->

## Security & compliance — known gaps

Written 2026-10-16, before launch. This is a list of what is **missing or
broken**, not a list of what works. Every item names the file that proves it, so
it can be re-checked rather than trusted.

Nothing here is a vulnerability report. It is the pre-launch inventory.

**Status key:** `BLOCKER` — cannot take real money or real users.
`HIGH` — exploitable or legally required, fix before launch. `MEDIUM` — fix
soon. `LOW` — housekeeping.

---

### BLOCKER 1 — `verified` middleware is registered nowhere, gates 56 routes

`routes/web.php` attaches `->middleware('verified')` to **56** routes: the whole
seller dashboard (`dashboard`, `orders`, `products`, `reviews`, `payouts`,
`billing`, `domains`, `customers`, …).

`bootstrap/app.php:74-82` registers seven aliases. `verified` is not one of them:

```
seller.active        EnsureSellerIsActive
onboarding.complete  EnsureOnboardingComplete
resolve.store        ResolveStorePage
subscription.valid   EnsureSubscriptionValid
admin                EnsureUserIsAdmin
feature              EnsureFeatureIsEnabled
forward.custom.domain ForwardCustomDomainToStorefront
```

Verified three ways: `grep -c "middleware('verified')" routes/web.php` → `56`;
`array_key_exists('verified', app('router')->getMiddleware())` → `false`; and
an authenticated seller whose `store_pages.verified_at` is `null` gets **200**
on `/dashboard`, `/orders`, `/reviews` and `/products`.

**So this is not a crash. It is a control that reads as enforced and is not.**
Every unverified seller reaches the entire dashboard.

Two things have to be decided before anything else in this file:

1. Is the badge meant to gate anything at all? Right now
   `app/Domains/Seller/Services/PublishReadiness.php:24-30` runs five
   publish-readiness checks and verification is not among them. If the badge is
   decorative, **delete** the 56 `->middleware('verified')` calls, because a
   dead control in 56 places will be "fixed" by someone eventually.
2. If it is meant to gate, write the middleware class, register the alias, and
   say what happens on failure (redirect to `/verified`? 403? auto-apply?).

Do not leave it as it is. Right now a reader of the routes would reasonably
believe the dashboard is behind a verification check.

---

#### BLOCKER 1 — corrected and resolved, 2026-10-23

**The diagnosis above is wrong on one fact, and the correction matters.**

`verified` *was* registered. It is Laravel's own alias for
`Illuminate\Auth\Middleware\EnsureEmailIsVerified`, and `Seller implements
MustVerifyEmail`. So the 56 routes were gated — on the seller's **email address**,
which is a working control. The check used above,
`array_key_exists('verified', app('router')->getMiddleware())`, returned `false`
because `Router::getMiddleware()` holds route middleware, not the alias map. The
alias map is `app(Illuminate\Contracts\Http\Kernel::class)->getMiddlewareAliases()`.

What was actually true, and is the real defect: the routes read as if they gated
*business* verification, and business verification gated nothing. An unverified
seller with a confirmed email reached the whole dashboard and could take money.

**Resolved as follows:**

- A second middleware, `App\Shared\Middleware\EnsureSellerIsVerified`, registered
  as **`business.verified`** — deliberately *not* `verified`, because overwriting
  Laravel's alias would silently drop email verification everywhere it is relied
  on. It reads `store_pages.verified_at` and redirects an unverified seller to the
  badge screen.
- The 56 inline `->middleware('verified')` calls are gone. The dashboard group
  states both controls once:
  `['verified', 'onboarding.complete', 'subscription.valid', 'business.verified']`.
- Badge and onboarding routes are exempt, or the redirect loops.
- `payouts` routes join the group; they had no verification at all.
- 15 tests in `tests/Feature/Security/VerifiedGateTest.php`, including two that
  assert email verification still works and that the alias map cannot regress.
- The duplicate `dashboard.orders.escrow-dispute` route is deleted.

**Superseded by the 2026-10-23 payment decision:** the dashboard must *not* be
gated on business verification. An unverified seller keeps full dashboard access
and builds their shop. `business.verified` is repurposed as the **payment gate**
on checkout, and the public marker becomes explicit. The middleware, the factory
states and the tests are kept; the group membership is not.

---

### BLOCKER 2 — No legal pages, and three links that already point at them

Zero of these exist as routes or views: Terms of Service, Privacy Policy,
Refund/Returns Policy, Seller/Merchant Agreement, Cookie Policy, Acceptable Use
Policy, Dispute Resolution, IP Policy, platform Contact/Imprint, Status page.

The application plan specified them and they were never built —
`PolicyController` plus `themes/shared/pages/policy.blade.php` appear at lines
3438-3445 of *"MYPAGE.NG — Implementation Plan (Phase 0 to Launch) (1).md"*.

Three surfaces already link to them and go nowhere:

- `resources/views/auth/register.blade.php:47` —
  `our <a href="#">Terms of Service</a> and NDPR compliant data privacy policy`
- `resources/views/components/platform/footer.blade.php:91` — `href="#privacy"`
- `resources/views/components/platform/footer.blade.php:92` — `href="#terms"`

Neither fragment target exists on the page. Both storefront footers
(`themes/store/footer.blade.php`, `themes/service/footer.blade.php`) and both
dashboard layouts (`layouts/app.blade.php`, `layouts/guest.blade.php`) carry **no**
legal links at all.

The register line is the sharp one. It tells a new seller they agree to terms
that do not exist, by a link that goes nowhere. That is worse than silence.

---

### BLOCKER 3 — No record of consent, and no data-subject rights

`grep -rin "terms_accepted|accepted_terms|tos_version|agreed_terms"` across
`app/`, `database/`, `routes/`, `config/`, `resources/` → **0 matches**.

- `sellers` has no `terms_accepted_at`, no `tos_version`.
  `0001_01_01_000000_create_sellers_table.php:14-25` plus four later migrations
  add only OTP fields, `is_admin`, payout bank details and `google_id`.
- `app/Domains/Auth/Http/Requests/RegisterRequest.php:24-32` validates
  `name, email, phone, password`. No `accepted` rule.
- `app/Domains/Auth/Actions/RegisterSeller.php` persists five fields. No consent.
- `resources/views/auth/register.blade.php` renders **no checkbox**, so nothing
  is submitted even if you added a column.

Data-subject rights are absent entirely — `grep` for data export, deletion,
erasure, right to be forgotten, NDPR/NDPA across `app/`, `routes/`,
`resources/` → **0 matches**. NDPA 2023 ss.34-38 gives a data subject the right
to be informed, to access, to rectification, to object, to restrict processing,
to portability, and to be forgotten. None of the seven is implementable today.

`customers.opted_in` is **not** this. It is a seller-controlled broadcast flag
(`2026_10_09_060000_create_customers_table.php:22-24`) and is never presented as
platform consent.

Tracked at `todo.md:413` and still unticked.

---

### BLOCKER 4 — NDPC registration not done, and no legal entity on file

NDPA 2023 s.56 requires a Data Controller of Major Importance to register with
the Nigeria Data Protection Commission within six months of becoming one. The
NDPC Guidance Notice of 14 Feb 2024 sets the threshold at **200 data subjects in
six months**; a live marketplace crosses that on day one. Fees run ₦10,000
(MDP-OHL) to ₦250,000 (MDP-UHL).

The Federal High Court upheld NDPC's power to require this in
*Emmanuel Harunna v. NDPC* (FHC/L/CS/1116/2024, July 2026), and the Commission
directed all unregistered DCPMIs to register immediately. This is enforced, not
advisory.

You cannot complete that registration without a legal entity, and there is
nowhere to put one:

- `sellers.name` is the **account holder's personal name** — used for the auth
  greeting.
- `store_pages.name` is the **public display name**, max 120 chars, seller-editable.
- The only registered-name capture is `badge_applications.business_name`
  (`2026_10_10_163000_create_badge_and_admin_tables.php:20`), a free-text string
  **pre-filled with the display name**, which is never copied back and never
  reused. `document_type` is a category (`cac` | `national_id`), never a number.

No CAC/RC number, no TIN, no expiry, no re-verification. `verified_at` is set
once and never revisited.

---

### HIGH 1 — Payment gateways are on test keys, with no retry for dropped webhooks

Current `.env`:

```
APP_URL=http://localhost:8000
MAIL_MAILER=log
PAYMENT_GATEWAY=paystack
PAYSTACK_SECRET=sk_test_…
PAYSTACK_PUBLIC=pk_test_…
```

- `FLUTTERWAVE_SECRET` and `FLUTTERWAVE_SECRET_HASH` are **empty**.
- `PAYSTACK_PLAN_STARTER` / `_GROWTH` / `_PRO` are **absent** (blank in
  `.env.example:80-82`, read at `config/billing.php:44,55,66`).
- `APP_URL` is localhost, which is what every `callback_url` is built from.
- `required_from_you.md:139-142` records that the test keys were printed into a
  working session. **Treat them as disclosed and rotate**, test keys included.

**Dropped webhooks never recover.** `routes/console.php` schedules exactly eight
jobs — `ExpirePendingBookings`, `SendBookingReminders` (×2),
`RemindAbandonedCarts`, `CloseStaleAbandonedCarts`, `ExpireSubscriptions`,
`ReleaseAutoEscrow`, `domains:verify-pending`. None touches `webhook_events`.
`PaymentWebhookController.php:73-76` catches a queue failure and comments that
the row *"stays unprocessed for the nightly sweep"*. That sweep does not exist.

A single dropped webhook is a customer who paid and an order stuck unpaid, with
nothing scheduled to notice. Signature verification itself is correct and
implemented — HMAC-SHA512 for Paystack, HMAC-SHA256 for Flutterwave, constant-time
`hash_equals`, idempotent `WebhookEvent::firstOrCreate`, HTTP 200 acknowledgement
so Paystack does not retry for 72 hours.

Also: **Flutterwave recurring billing does not exist.** Nine methods in
`FlutterwaveGateway` throw `unsupported()` (`:121-159`), including every plan
and subscription method. `PAYMENT_GATEWAY=flutterwave` therefore has no billing.

Webhook URLs are a dashboard click each — Paystack's API has no endpoint that
sets one:

```
https://YOUR-DOMAIN/webhooks/paystack
https://YOUR-DOMAIN/webhooks/flutterwave
```

---

#### HIGH 1 — rescoped, 2026-10-23

This item was written when the platform held buyer money. Under the payment
decision it narrows to **subscription billing only**, which is a much smaller
surface — and it grows a second, harder requirement on the seller side.

Still required, unchanged:

- Live Paystack keys for the **platform's own** account, plan codes for
  starter/growth/pro, a running queue worker, and a correct `APP_URL`.
- Rotate the disclosed test keys.
- A sweep for unprocessed `webhook_events`. The missing nightly job is a real bug
  and it now affects subscription renewals rather than buyer orders. A lapsed
  subscription webhook that is dropped means a seller is cut off while they have
  paid.

New, and larger than the old item: **per-seller webhook delivery.** Buyer payments
now arrive on each seller's own Paystack account, so:

- The webhook URL is `https://{store}.mypage.ng/webhooks/paystack`, and the tenant
  is resolved from the host before the payload can be verified.
- Each seller must register that URL in their own Paystack dashboard. Nothing in
  Paystack's API can do it for them. A seller who skips this has a working
  checkout and orders that never settle.
- Retry/sweep must therefore work per tenant, not once per platform.

Flutterwave: the recurring-billing gap (`FlutterwaveGateway` throws
`unsupported()` on every plan and subscription method) still means
`PAYMENT_GATEWAY=flutterwave` has no billing. For buyer payments the gateway is
now the **seller's** choice, so this is a documentation and validation problem:
only offer gateways whose buyer-payment path is actually implemented.

---

### HIGH 2 — No transactional email is delivered, and no Mailable templates exist

`MAIL_MAILER=log`. Nothing reaches a buyer or a seller.

- **0** classes extend `Mailable`. There is no `resources/views/mail/`.
- 7 notifications exist and all use inline `MailMessage`, so they render on
  Laravel's stock Markdown template with no branding and **no legal footer**:
  `EmailOtpNotification`, `WelcomeNotification`, `NewOrderNotification`,
  `OrderStatusChangedNotification`, `AbandonedCartReminder`,
  `BookingStatusChangedNotification`, `BookingReminder`.
- No `List-Unsubscribe` header and no unsubscribe link anywhere. The buyer-facing
  `AbandonedCartReminder` is sent regardless.
- `store_pages.public_email` exists and is a **display address only**
  (`mailto:` in both footers). It is never a `Reply-To`, there is no
  `support_email` column, and no platform support address in config.

Domain authentication (SPF, DKIM, DMARC) cannot be verified from this
repository — check the live DNS zone directly.

---

### HIGH 3 — Admin cannot open the KYC document it is supposed to review

`app/Domains/Admin/Models/BadgeApplication.php:61-64`:

```php
public function documentUrl(): string
{
    return Storage::url($this->document_path);
}
```

The `local` disk (`config/filesystems.php:33-36`) has root
`storage/app/private` and **no `url` key**. Laravel 11+
`FilesystemAdapter::getLocalUrl()` (`vendor/…/FilesystemAdapter.php:870-888`)
does not consult the `serve` flag for `url()` — it returns an **unsigned**
`/storage/{path}`. The file is under `private/`, not `public/`, so the URL 404s.

The admin reviewing a verification application cannot see the document that the
decision is based on. Rendered at
`resources/views/admin/badges.blade.php:58`.

Fix is `Storage::disk('local')->temporaryUrl($path, now()->addMinutes(5))`.

---

### HIGH 4 — Seller KYC is a badge, not a gate, and gates nothing

The flow is genuinely built — upload (`dashboard/badge/index.blade.php`),
validation (`BadgeController.php:42-47`), admin queue
(`admin.badges`), idempotent decision with a **mandatory** rejection reason
(`ReviewBadgeApplication::reject()` `:34-43`), and an audit log
(`admin_audit_logs`).

What is missing: no legal entity identity, no document number, no expiry, no
re-verification, no document retention or deletion policy — and per BLOCKER 1,
verification gates nothing at all. An unverified seller with a payout account
number can take money.

---

#### HIGH 4 — rescoped, 2026-10-23

The last sentence is no longer true, and the item changes shape because of it.

There is no platform payout account and no platform-held money, so "an unverified
seller can take money from us" is gone. What remains is that the badge still
identifies **nothing about the business**: `badge_applications.business_name` is a
free-text field pre-filled with the display name, `document_type` is a category
rather than a number, and there is no CAC/RC number, TIN, expiry or re-check.

Under the payment decision, verification is what makes a seller's checkout work
and puts a tick in front of buyers. That makes identity capture *more* important,
not less — a seller's own Paystack account is where the money goes, and the only
thing tying that account to a real business is what the badge captured.

Required before the checkout is switched on for a seller:

- A registered legal name, distinct from the account holder's name and the public
  display name, plus a CAC/RC number or national ID number.
- The document number stored separately from the uploaded file, so it can be
  re-checked without opening the image.
- Retention and deletion rules for the uploaded document — it is personal data.
- Re-verification, or at minimum an expiry, so a badge is not permanent by
  accident. `verified_at` is currently set once and never revisited.

**Seller gateway credentials are the new HIGH 3-class problem** (see HIGH 3 for
the admin-can't-read-the-document bug, still open). Storing a per-seller secret
key is a security obligation in its own right:

- `text` column under an `encrypted` cast, with `APP_PREVIOUS_KEYS` documented so
  `key:generate` does not destroy every seller's key.
- Not fillable; written by one action. Hidden from `toArray()` and JSON.
- `#[SensitiveParameter]` on every method that receives it, never in a queue
  payload, never logged, never `Http::debug()`.
- Proven against the gateway **before** it is saved, and a later 401 marks it
  broken and notifies the seller.
- `sk_test_` rejected in production and `sk_live_` rejected outside it.
- `password.confirm` required to change it, so a hijacked session cannot redirect
  a seller's money.

Encryption at rest protects a database dump or a leaked backup. It does not
protect a fully compromised host, because `APP_KEY` sits beside the database.
That is defence in depth, not a vault; a KMS-wrapped key is the later step.

**Rotating `APP_KEY` without destroying saved seller keys:**

1. Generate the new key: `php artisan key:generate --show`.
2. Set `APP_KEY=<new>` and `APP_PREVIOUS_KEYS=<old>` in the environment. Laravel
   decrypts with the old key and writes with the new one.
3. Clear caches (`php artisan config:clear`, or rebuild `config:cache`) and
   restart queue workers and the web process.
4. Verify one seller's key still works from the Payment keys screen, then leave
   `APP_PREVIOUS_KEYS` set. Rows written before the rotation stay readable only
   while it is present.

There is no re-encryption command yet, so **retiring the old key means every
seller re-entering their key**. Do not remove `APP_PREVIOUS_KEYS` until either a
re-encryption pass exists or that cost has been accepted; without it, every
`seller_gateways.secret_key` becomes undecryptable.

---

### MEDIUM 1 — No cookie consent mechanism, because there is no analytics yet

No banner component, no consent storage: `grep` for `cookie_consent`,
`cookieConsent`, `consents` across `app/`, `resources/`, `routes/`, `config/`,
`database/` → **0 matches**. No `consents` table, no consent column.

This is currently defensible — `grep` for analytics, gtag, GTM, Facebook pixel,
Hotjar, Mixpanel, Plausible returns **0 matches** too. The only third-party
browser requests are Google Fonts and gateway redirects.

Cookies actually set: the session cookie (`config/session.php`, `SESSION_DRIVER=database`),
the 60-minute store preview cookie (`ResolveStorePage.php:16,84-95`), and the
theme-mode cookie in `components/ui/theme-boot.blade.php`. Any cookie policy
must describe those.

Note `SESSION_SECURE_COOKIE` is absent from `.env`.

**This becomes HIGH the moment analytics are added**, and the first analytics tag
must not ship without a banner and a consent record.

---

### MEDIUM 2 — TLS for seller custom domains is not wired up

Custom domains resolve and are DNS-verified, but there is no edge issuing
certificates. `todo.md:398-400` records that a live binding is served over the
platform. A seller pointing `shop.com` at mypage.ng gets a plaintext HTTP site
unless the edge is in front.

---

### LOW 1 — Dead and stale test claims

- ~~`todo.md:658` claims 562 tests pass.~~ Re-run 2026-10-23: **722 tests, 722
  passing, 2182 assertions.** The number in `todo.md` is now that one. Quoting a
  count is still a bad habit — a green number is not a working product, which is
  this file's own lesson.
- Six `ReviewsTest` failures documented at `required_from_you.md` C1 have since
  been fixed; that section is now out of date in the other direction.
- `tests/Feature/Security/VerifiedGateTest.php` was added on 2026-10-23 (15 tests)
  for the business-verification gate. It is green. Note that its *dashboard
  membership* is being reversed by the payment decision above; the alias,
  credentials and factory states stay.

---

### Launch gate

Rewritten 2026-10-23 for the payment architecture above. The old gate was
written for a platform that held buyer money; the last three items of the old
version (payout, refund, escrow reconciliation) no longer describe anything.

Do not take real money until:

1. **BLOCKER 2** resolved — real legal pages, real links, real content.
2. **BLOCKER 3** resolved — consent recorded, DSAR endpoints exist.
3. **BLOCKER 4** resolved — legal entity captured, NDPC registration filed.
4. **HIGH 2** resolved — real mailer, branded templates, SPF/DKIM/DMARC, and one
   real order's email verified end to end. This now blocks more than it did: a
   dropped webhook notification is the only way a seller learns an order exists.
5. **HIGH 3** resolved — admin can actually read the KYC document.
6. **Subdomain routing** enforced, with host-only cookies and a wildcard
   certificate. Every checkout callback and every webhook depends on the tenant
   host being right.
7. **Seller gateway credentials** built to the storage rules under HIGH 4, with
   the key proven before save.
8. **Per-tenant webhook delivery** built, with the seller-facing setup step and a
   visible "webhook not configured" state.
9. **The unverified-seller warning** live on both themes: on the page, on
   product cards, and beside the pay button. Checkout is *not* blocked.
10. **Report a store** and **store support contact** live on both themes.
11. **Subscription billing live**: platform live keys, plan codes, queue worker,
    correct `APP_URL`, rotated test keys, unprocessed-webhook sweep.
12. **Escrow, wallet, ledger, commission and payout code deleted** — not flagged
    off. A dead money path in a live app is the same defect as a dead
    authorization control.

Then, by hand, with something small:

- One real ₦ subscription charge to **mypage.ng**, reconciled in Paystack.
- One real ₦ order paid to a **seller's own Paystack account**, including the
  seller pasting the webhook URL, the order reaching `paid`, the stock moving and
  the buyer receiving their email.
- One seller spelling their own key wrong, and the app refusing it instead of
  silently failing.
- One buyer reporting a store, and the report arriving in the admin queue.

The old wording of the final line is kept here for the record: *"one real ₦
payment settled, one real payout to a bank account, and one real refund, all
reconciled by hand."* Payout and refund are no longer platform actions.

---

<!-- source: required_from_you.md -->

## Required from you

Everything in this application that **cannot be finished without a decision, a
credential, or an action on your side**. Written while implementing recurring
Paystack billing; each item says exactly what is needed and what breaks if it
is skipped.

Nothing here is a suggestion. Section A is blocking — real money cannot move
until it is done.

> **Rescoped 2026-10-23.** Section A now concerns **subscription billing only**:
> the seller paying mypage.ng. Buyer payments no longer touch a platform account,
> so the old implication that these keys gate every payment is gone. What is new
> is per-seller gateway configuration and per-tenant webhooks — see the payment
> decision section at the top of this file. Section A1's plans, A2's platform
> webhook, A3's queue worker, A4's live keys and A5's `APP_URL` all still apply.
>
> B1, B2, B3 and B4 remain open decisions for subscription billing. C1 is stale
> and has been fixed; C2 is still open.

---

### A. Blocking — payments cannot work without these

#### A1. Create the three plans on Paystack and give me the codes

The plans are only local rows until they exist on Paystack. Recurring billing
does nothing without them.

Create each at <https://dashboard.paystack.com/#/plans> (or `POST /plan`), with
**exactly** these values — they come from `config/billing.php`:

| Config key | Plan name | Amount | Interval |
|---|---|---|---|
| `starter` | Starter | ₦3,500 | monthly |
| `growth` | Growth | ₦9,500 | monthly |
| `pro` | Pro | ₦25,000 | monthly |

Then put the codes in `.env`:

```
PAYSTACK_PLAN_STARTER=PLN_xxxxxxxxxxxx
PAYSTACK_PLAN_GROWTH=PLN_xxxxxxxxxxxx
PAYSTACK_PLAN_PRO=PLN_xxxxxxxxxxxx
```

Then `php artisan db:seed --class=PlanSeeder`.

> **This one CAN be automated** — unlike A2. `POST /plan` is a real API
> endpoint, and `PaystackGateway::createPlan()` is already implemented for it
> (currently unused). I can add a command that reads `config/billing.php`,
> creates all three plans on Paystack, and prints the `PLN_...` codes for you to
> paste into `.env`. It deliberately does *not* auto-run on deploy — creating
> plans against a live merchant account is an action you should take once,
> deliberately. Say the word.

> **The amount and interval must match.** Paystack *overrides* the amount this
> application sends with the plan's own. A stale code charges the wrong sum and
> nothing in the code will notice.

**If skipped:** every plan falls back to charging one period at a time by hand.
Safe, but no seller is ever charged automatically.

---

#### A2. Register the webhook URL

Paystack cannot confirm payments until it knows where to send events.

Add to <https://dashboard.paystack.com/#/settings-and-developments> (separate
URL per environment):

```
https://YOUR-DOMAIN/webhooks/paystack
```

**This one genuinely cannot be automated.** It is worth being clear about why,
because everything else on this list can:

- Paystack's API has **no endpoint that sets a webhook URL**. The full API
  reference has no `POST /webhook`. The "Webhook Events" endpoints —
  `GET /webhook_events`, `POST /webhook_events/resend`, and the matching-event
  variant — only *read* and *replay* deliveries to the URL that is already
  configured. They cannot change where deliveries go.
- The `Integration` endpoints are only `Fetch/Update Payment Session Timeout`.
- Paystack's support documentation says it directly: *"paste the link on your
  server in the appropriate webhook URL field."*

So this is one dashboard click. It is also the only item here that genuinely
has to be done by hand.

Requirements Paystack enforces:

- Must be **publicly reachable**. `localhost` will never receive an event.
- Must return **200 OK**. Anything else is treated as a failed attempt and the
  event is retried for 72 hours, then dropped — a paid order silently never
  settles. *(This was a real bug: the endpoint returned 204. Fixed.)*
- Respond fast. Long-running work happens in a queued job.

**If you skip it:** checkouts still work *when the buyer returns to the success
page*, because the callback verifies the payment independently. What you lose is
the case where they do not return — closed the tab, lost signal, phone died.
Those payments stay unpaid with no retry. Paystack's own docs recommend webhooks
over callbacks for exactly this reason, so treat it as required for production,
not optional.

Also register Flutterwave if you intend to use it:

```
https://YOUR-DOMAIN/webhooks/flutterwave
```

Optional but recommended — IP whitelisting, if you have a firewall:

```
52.31.139.75
52.49.173.169
52.214.14.220
```

---

#### A3. A running queue worker

`ProcessPaymentWebhook` is a **queued job**. Without a worker, webhook events
are stored and never processed, so payments are never confirmed.

```bash
php artisan queue:work
```

In production this must survive reboots — supervisor, systemd, Horizon, or a
Laravel Cloud background process.

**If skipped:** sellers pay, the order stays unpaid forever.

---

#### A4. Live credentials, and rotate the test keys

Set for production:

```
PAYMENT_GATEWAY=paystack
PAYSTACK_SECRET=sk_live_...
PAYSTACK_PUBLIC=pk_live_...
```

**Please rotate your test keys.** I printed `sk_test_…` and `pk_test_…` into this
working session while diagnosing a payment failure, so treat them as disclosed.
They are not committed (`.env` and `bootstrap/cache/config.php` are both
untracked, verified), but rotate them anyway — it costs one click.

---

#### A5. `APP_URL` must be correct in production

`APP_URL` is the apex (`https://mypage.ng`). It is what `StoreUrl::platform()` and
every platform link (login, dashboard, pricing) are built from, so a wrong value sends
sellers to a host that does not exist.

Payment callbacks are a **17.3** item, not finished. Today `InitializePayment` builds
the callback with `route('checkout.callback')`, which resolves to the seller's
**subdomain**. That is reachable, but on a custom-domain checkout it walks the buyer to
`shop.mypage.ng`; because cookies are host-only, anything session-backed after the
return (cart, flash) is lost. 17.3 must build the callback from the storefront
request's own host and register the callback on custom domains too.

That gives two hard requirements now:

```
APP_URL=https://mypage.ng
```

- The wildcard `*.mypage.ng` certificate must cover every seller subdomain.
- Custom domains must also be reachable over HTTPS. A custom-domain checkout breaks
  independently of the apex, so it cannot be deferred.

In-shop links are relative (`StoreUrl::path()` / `homePath()`) on purpose: that is what
keeps a buyer on the seller's custom domain instead of walking them to the subdomain.
Platform links use `StoreUrl::platform()`, which always points at the apex.


---

### B. Decisions I need from you

#### B1. The 14-day free trial is now effectively gone for card signups

This is the one place where the option you chose has a cost worth deciding on.

`config/billing.php` gives `starter` and `growth` a `trial_days => 14`. But
Paystack's "charge first, reuse the auth code" path charges **immediately** —
so a card signup skips the trial, while a manual-transfer signup still gets it.
Two different trials for the same plan.

I built it this way because you chose "first period charges". Three ways out:

| Option | What it costs |
|---|---|
| **Accept it** (current) | Card sellers lose the 14-day trial. Nothing to do. |
| **Delete the trial** | Honest and consistent. `trial_days` becomes `0` on all plans. |
| **Restore the trial** | Needs `POST /subscription` with `start_date` instead. But that needs an authorization code, which needs a prior charge — so it does not work without charging something first. Messy. |

**My recommendation: accept it now, delete `trial_days` later once trials are
revisited deliberately.** Say which.
Ask me when it is time to implemnet this feature 
---

#### B2. Should plans be created by this app instead of by hand?

`PaystackGateway::createPlan()` exists and works, but nothing calls it — plans
are created by hand in the Paystack dashboard (A1).

| Option | Trade-off |
|---|---|
| **Manual** (current) | Prices live in two places; they can drift silently. |
| **Auto-create on seed** | One source of truth, but a deploy touching the live Paystack account is its own kind of risk, and it cannot be undone by deleting a row here. |

**My recommendation: keep it manual.** Say if you disagree.   Ask me when you get here , you must ask me, when it is time to implemnet this

---

#### B3. `subscription.expiring_cards` — notify sellers, or ignore?

When a card expires, Paystack sends a monthly event listing affected
subscriptions. It is received and authenticated, but **currently does nothing**.

Telling the seller early is genuinely useful — it is the difference between
"suddenly past due" and "we noticed your card expires in three weeks". It needs
a decision on how: email, a dashboard banner, both, or neither.



The user must be notified that their card has expired, both on the platform and on their email 
---

#### B4. There is no sweep for webhooks the queue dropped

`PaymentWebhookController` has a comment saying an unprocessed row *"stays
unprocessed for the nightly sweep"*. **That sweep does not exist.** Only
`ExpireSubscriptions` is scheduled daily; nothing retries an unprocessed
webhook.

So if the queue is briefly down when a payment event arrives, that payment is
never confirmed and never retried. The row is there; nothing reads it.

Options: a daily job that re-dispatches unprocessed events, or a larger
idempotency key on the gateway side. The first is straightforward — say if you
want it and I will build it.

---

### C. Pre-existing problems I did not touch

Flagging rather than fixing, because both are outside what you asked for.

#### C1. Six failing tests in `ReviewsTest`

```
SQLSTATE[23000]: NOT NULL constraint failed: reviews.reviewable_type
```

The tests call `Review::create([...])` without `reviewable_type`, which is
`NOT NULL` and deliberately excluded from `$fillable` (the model refuses to let
a request attach a review to someone else's product).

These failed before any of my changes and are unrelated to payments or theming.
Someone needs to decide: add a factory state the tests use, or allow the column
when not filling from a request. I did not guess.

#### C2. `font-display` and `shadow-subtle` produce no CSS

Both are used across the dashboard but have no `@theme` entry, so they emit
nothing. Dashboard headings fall back to Inter instead of Plus Jakarta.

Found while doing the dark-mode work; unrelated to it. Fixing means adding
`--font-heading` / `--shadow-rest` to the theme, which changes typography
across ~40 places. One word from you and I will do it.

---

### D. Quick reference

```bash
# A1 — after adding the codes
php artisan db:seed --class=PlanSeeder

# A3 — locally
php artisan queue:work

# Verify plans picked up their codes
php artisan tinker --execute 'App\Domains\Billing\Models\Plan::pluck("gateway_plan_code","slug");'

# Verify a plan is wired end to end
php artisan tinker --execute '
  Http::fake(["api.paystack.co/*" => Http::response(["status"=>true,"data"=>["authorization_url"=>"x"]])]);
  '

# Run the suite
php artisan test
```

Test card numbers for exercising a real subscription: `4084084084084081`
(accepts), with any future expiry and any CVV.

---

<!-- source: escrow_security_plan.md -->

## Escrow security plan (proposed)

> **WITHDRAWN 2026-10-23. Do not build any of this.**
>
> The payment architecture decision at the top of this file removed platform-held
> money entirely: buyers pay the seller's own Paystack account and mypage.ng never
> holds, releases or refunds a buyer payment. There is no escrow, so there is no
> buyer release to authorise, no OTP gate to add, and no dispute to arbitrate.
>
> This section is kept because it is a careful piece of thinking and because the
> code it describes still exists. It is history and a reference for the general
> shape of two-channel authorisation — not a plan. The next person to find the
> escrow tables should read the decision section first.

How a buyer proves they are the buyer before money moves.

**Status:** proposal, not started.
**Scope:** the buyer-facing escrow screens — `/{slug}/escrow/{token}` and its
release/dispute actions.

---

### 1. Decision

Adopt **email-anchored identity with a one-time code on every write**, delivered
over two channels, with the emailed artefact being a link to a code-entry page
rather than the release capability itself.

| | Today | After |
|---|---|---|
| Reach the escrow page | Possess the token in the URL | Possess the token in the URL |
| Release the money | Possess the token in the URL | Possess the token **and** a code from a second channel |
| Raise a dispute | Possess the token in the URL | Possess the token **and** a code |

The gate moves from the URL onto the action. Finding a link stops being enough.

---

### 2. Current state

Verified against the code, not assumed.

| Fact | Where |
|---|---|
| Token is 24 hex chars from an HMAC of the escrow id — unguessable, deterministic, **and it never expires** | `app/Domains/Billing/Models/Escrow.php:106` |
| Access is decided by scanning **every escrow on the page** and comparing in PHP — O(n) per request | `BuyerEscrowController::findEscrow()` |
| `GET /escrow/{token}` is **unthrottled** | `routes/storefront.php:45` |
| Release is throttled 10/min, dispute 5/min | `routes/storefront.php:46,49` |
| `track-order` is **unthrottled** and is itself a reference+contact guessing oracle | `routes/storefront.php:40` |
| Release is wrapped in `lockForUpdate()` and guarded by `isHeld()` — no double-release under race | `EscrowService::release():98` |
| A disputed escrow is admin-only | `EscrowService::release():105` |
| Escrow auto-releases after 7 days, bounding the buyer's window | `config/mypage.php` `escrow_auto_release_days` |
| Escrow is **off by default** (`FEATURE_ESCROW=false`) and per-seller gated | `config/mypage.php:21` |
| Order already captures `customer_name`, `customer_email`, `customer_phone` (all nullable) | `create_orders_table` |
| Buyers are already emailed on status change, via a queued listener | `NotifyBuyerOfStatusChange` |
| A proven hashed-OTP pattern already exists in this repo | `Seller::sendEmailVerificationNotification()`, `email_otp_hash` + `email_otp_expires_at` |
| A dispute is a `reason` string only — no attachment, from either side | `BuyerEscrowController::dispute():74` |
| **`dashboard.orders.escrow-dispute` is registered twice**, identically, at `routes/web.php:148` and `:149` | `routes/web.php` |

Two things follow. The escrow row has **no** stored token, no attempt counter
and no OTP fields, so everything below is net-new. And because escrow is off by
default, the realistic exposure today is limited to sellers an admin has
switched it on for — which argues for P0 first and no gold-plating.

The duplicated route is harmless today (Laravel matches the first) but it will
bite the next person who tries to change either one. Worth deleting while this
area is open.

---

### 3. Threat model

Who plausibly reaches an escrow page, and what they can do.

| Actor | Has | Wants | Blocked by |
|---|---|---|---|
| Buyer | Token + own inbox + own phone | Release or dispute | — |
| Someone who saw a link | Token, from a shared link, forwarded message, or a compromised inbox | Release funds to a seller who already got paid, or grief with a dispute | **P0 code + P0 lockout** |
| Someone who read the email only | Email, not the phone | Same as above | **P0 two-channel** |
| Someone who has the phone only | Phone, not the email | Same as above | **P0 two-channel** |
| A buyer with a typo'd email | Undeliverable mail | Release their own money | P1 bounce fallback |
| A casual guesser, no link | Nothing | Brute-force a 6-digit code | P0 lockout (and a code is worthless without the link) |
| A buyer who disputes then goes quiet | The dispute, and nothing else | Freeze the seller's money indefinitely | **§5.3 response deadline** |
| A seller who claims delivery and stalls | A delivery claim | Freeze indefinitely | §5.3, symmetric |
| A buyer or seller who fabricates evidence | The dispute form | Manufacture a decision in their favour | §5.4 admin cannot be the only reader of one side |

Explicit non-goal: defending against a compromised seller account. Out of scope.

---

### 4. Measures, in priority order

#### P0 — closes a realistic attack path

**P0.1 Attempt lockout on the code.**
A 6-digit code is 1,000,000 combinations. The release route is throttled at
10/min, so across a 72-hour escrow an attacker gets hundreds of thousands of
guesses. Cap at **5 attempts**, then invalidate the code and require a new one.
Five attempts is cryptographically sufficient and costs a real buyer nothing.
Add `otp_attempts` to the row; reset on a successful issue of a new code.

**P0.2 Second channel: send the code to the phone.**
`customer_phone` is already captured. Code to **email**, link to **phone** (or
the reverse) makes the two factors independently held. Reading one channel is
not enough. This is the single largest gain available and costs one extra send.
For a Nigerian buyer, WhatsApp is the reliable channel — the platform already
sends booking reminders by email, so the transport exists.

**P0.3 Email a link to a code-entry page, never the release capability.**
If the email contains both the token and the code, one compromised mailbox is
total compromise. Send `/{slug}/escrow/recover` instead. Then the token alone
does nothing and the code alone does nothing.

**P0.4 Notify both parties whenever money moves.**
Email the buyer **and** the seller on release, dispute and refund. This is
detection rather than prevention, and it is what turns a silent theft into a
visible one. Reuse `NotifyBuyerOfStatusChange`'s pattern — queued,
`Notification::route('mail', ...)`.

#### P1 — protects real buyers from being locked out

**P1.5 Bounce fallback to WhatsApp.**
`customer_email` is nullable and can bounce. If release is gated on email and
the mail bounces, the buyer cannot release at all and the money waits out the
7-day auto-release. Fall back to WhatsApp on bounce, and treat a hard bounce as
a signal to prompt the buyer for a working address.

**P1.6 Give the token an expiry.**
Order and booking links already expire after 30 days
(`BuyerLinks::EXPIRES_DAYS`), with a comment explaining why a permanent link is
a bad trade. The escrow link has no such protection and it is a *write* link.
Same 30 days, or the 7-day auto-release if that is shorter.

**P1.7 Weight release above dispute.**
Disputing freezes money and routes to admin — costly in support time, not money.
Releasing pays out. Both require the code; if friction should differ, put it on
release.

#### P2 — hardening, does not change an outcome alone

**P2.8 Stop the O(n) scan.** Store the token (or a lookup key for it) in an
indexed column and query it. `findEscrow()` currently loads the page's entire
escrow history on every request and grows with the seller's business.

**P2.9 Throttle `GET /escrow/{token}` and `track-order`.** Both are unauthenticated
lookup oracles. `throttle:60,1` on each is proportionate.

**P2.10 Code hygiene.** Hash at rest, reusing the existing
`email_otp_hash`/`email_otp_expires_at` pattern rather than inventing a second
one. Single use. **10-minute TTL**, not 30 — a code is a moment, not a key.
POST only: a code in a query string lands in access logs, browser history and
`Referer` headers. CSRF-protected form, no Alpine. Indistinguishable failures —
"that code isn't right" must read the same for wrong, expired and already-used,
or the form becomes an oracle.

**P2.11 Audit the access, not just the state.** `release()` records the state
change but not who made it. Add escrow id, action, buyer ip, user agent, attempt
count, and whether a code was presented. Copy `AdminAuditLog`'s pattern.

---

### 5. The dispute, and why it needs a shape of its own

#### 5.1 What is already correct

A disputed escrow is already handled the way you describe, and this should not
be changed:

- `EscrowService::raiseBuyerDispute()` moves the row to `Frozen`.
- `Frozen` is **admin-only**. `release()`, `refund()` and `resolve()` all refuse
  to touch it, so **no one — not the buyer, not the seller, not support — can
  move the money while it is frozen.** It is held by the platform.
- A seller can already raise the counter-case from the order screen via
  `dashboard.orders.escrow-dispute`.

The gap is not the freeze. It is everything around it.

#### 5.2 Evidence, on both sides

Today a dispute is `reason` only — a required string, 20–1000 characters
(`BuyerEscrowController::dispute():74`). No attachments, from either side.

| | Buyer | Seller |
|---|---|---|
| Trigger | Something wrong with what arrived | Delivered, buyer has gone quiet |
| Required today | `reason`, 20–1000 chars | `reason` |
| Required after | **Text + at least 1 photo** | **Text + at least 1 photo** |

**Delivery evidence is the part that is missing, and it is the part you are
right about.** A buyer who disputes and then stops replying currently holds the
seller's money until an admin bothers to look. The seller has no way to put
their case forward at all — no delivery photo, no courier receipt, no proof the
goods left their hands. That asymmetry is the actual problem, and it is worse
than the dispute itself.

Both sides see both sets of evidence, each labelled with who submitted it and
when. The other side is never allowed to delete or overwrite what was filed
while the dispute is open.

#### 5.3 A dispute cannot sit forever

There is already `ESCROW_AUTO_RELEASE_DAYS` (7) — but it applies to a `Held`
escrow, and it will **not** touch a `Frozen` one. So a dispute, once raised, is
currently permanent: no clock runs on it at all. Someone must always resolve it
by hand.

Add a response deadline, driven by the same knob, with the outcome stated up
front on the screen:

- **The party who opened the dispute** has N days to submit their evidence and
  answer the other side. Miss it and the escrow resolves to the other side.
- The counterparty has the same window to respond to *that*.
- Absent evidence from either side breaks the tie in the other party's favour.

This is the only mechanism that prevents the failure you describe, and it makes
stalling actively costly, which the current frozen-forever state does not.

#### 5.4 Evidence handling

- **Private storage, signed URLs.** These are photographs of a buyer's goods,
  their address, their packaging. Not public paths.
- **Validate content, and set `Content-Disposition: attachment` on download.**
  A `.jpg` that is really HTML is stored-XSS otherwise.
- **Re-encode on upload** (and strip EXIF, which carries GPS) rather than
  trusting what arrived. Stripping location from a photo of someone's doorstep
  is a privacy obligation, not a nicety.
- **Cap count and size**, and rate-limit the upload route.
- **Both sides' evidence is readable before either decides.** An admin
  resolving on one side's evidence alone is the failure mode, so the decision
  screen shows both.
- **Record the decision against the evidence** — which attachments were
  considered — so the audit trail survives later disputes about the decision.

#### 5.5 What must not be built

- **Do not release automatically when a dispute is opened.** That hands the
  attacker the money. Frozen is correct; the fix is a deadline, not a shortcut.
- **Do not let the party who opened the dispute also be the one who times the
  other side out.**
- **Do not require a code to *view* evidence.** P0's code gates money movement;
  evidence is read-only, and a code prompt there just produces support tickets.


Additive only; nothing is dropped.

---

### 6. Data model

Additive only; nothing is dropped.

```
escrows
  + otp_hash           nullable   hashed code, never the code
  + otp_expires_at     nullable
  + otp_attempts       unsigned smallint default 0
  + otp_last_sent_at   nullable   for rate limiting sends
  + release_token      nullable   indexed; replaces the O(n) scan
  + last_accessed_at   nullable
  + last_access_ip     nullable
  + frozen_at          nullable   starts the §5.3 deadline
  + evidence_deadline_at nullable  the other side's clock

escrow_evidence
  id, escrow_id, submitted_by (buyer|seller), submitted_by_id
  kind                   (damage | non_delivery | delivery_proof)
  body                   text
  created_at
escrow_evidence_attachments
  id, escrow_evidence_id, disk, path, mime, bytes, checksum, created_at
```

Attachments carry no public path. `checksum` exists so an identical image filed
by both sides is visibly identical rather than looking like two corroborating
pieces of evidence.

Hashed, following `Seller::verifyEmailOtp()`. The plaintext code exists only in
the outbound message.

---

### 7. Routes

```
GET  /{slug}/escrow/recover          new    reference + contact, mints a session
POST /{slug}/escrow/{token}/verify   new    submit the code, opens the page
GET  /{slug}/escrow/{token}          throttle:60,1   page still needs the code
POST /{slug}/escrow/{token}/release  throttle:10,1   + code required
POST /{slug}/escrow/{token}/dispute  throttle:5,1    + code + text + >=1 photo
GET  /{slug}/escrow/{token}/evidence/{id}/download  signed, private
```

Seller-side, for the missing half of §5.2:

```
GET  /dashboard/orders/{order}/escrow            evidence + deadline state
POST /dashboard/orders/{order}/escrow/delivery   text + >=1 photo, claims delivery
```

Both are behind `auth` + `verified` + the seller's own order, so tenancy scope
does the work.

The verified session is scoped to **one escrow**, never a buyer-level login. It
expires with the token, and a page refresh must not silently re-authorise.

---

### 8. Failure modes

| Case | Behaviour |
|---|---|
| Email bounces | Fall back to WhatsApp; prompt for a working address |
| No phone captured | Code by email only — weaker, but still two factors (link + code) |
| No email captured | Cannot gate on email. Fall back to today's token behaviour and flag the order for follow-up |
| More than 5 wrong codes | Invalidate, allow a re-send, rate-limit re-sends |
| Buyer loses both channels | Support path, same as any unverified buyer claim |
| Escrow auto-releases first | No action needed; the code simply expires unused |
| A dispute is raised and then ignored | Deadline in §5.3 resolves it automatically; nobody waits on an admin |
| A photo upload is rejected | Retryable, and the reason is visible on the form |
| Evidence is deleted | Not possible while the dispute is open; submission is append-only |

---

### 9. Tests

Security behaviour, not framework behaviour.

1. A correct code releases; a wrong one does not.
2. The 6th attempt is refused and invalidates the code.
3. A used code cannot be replayed.
4. An expired code is refused, and expires the same way as a wrong one (assert
   on the *message*, to catch an oracle).
5. A code issued to email does not work if only the phone was reached, and the
   page is unopenable without a code at all.
6. A code submitted by GET is never accepted.
7. A bounced email still leaves a working release path via WhatsApp.
8. Release notifies buyer and seller.
9. A disputed escrow still cannot be released by anyone, admin included.
10. Concurrent releases move the money once (guards `lockForUpdate`).
11. `findEscrow` is a single query, not a page scan.
12. A dispute with no photo is refused; with text and a photo it succeeds.
13. A seller can file delivery evidence after the buyer disputes.
14. Both sides see both evidence sets, each labelled with its author.
15. A frozen escrow ignores the 7-day auto-release — frozen means frozen.
16. A dispute past the response deadline resolves to the other party, and
    *only* to the other party.
17. An upload that is really HTML cannot execute when downloaded.
18. An attachment is not publicly addressable without a valid signature.

---

### 10. Sequence

| Step | Work | Ship alone? |
|---|---|---|
| 1 | P0.1 lockout, P0.3 code-entry page, P0.4 notifications | Yes — meaningful alone |
| 2 | P0.2 second channel (needs a WhatsApp transport decision) | Yes |
| 3 | P1.5 bounce fallback, P1.6 token expiry | Yes |
| 4 | P2.8–P2.11 hardening | Yes |
| 5 | §5.2 evidence on both sides | Yes — this is the fairness fix, independent of security |
| 6 | §5.3 response deadline | Needs step 5, or the clock runs on nothing |

Steps 1 and 2 are the 80/20 of the *security* work. Step 5 is the fairness fix
and should not wait on security at all — the seller currently has no way to
present their case, and that is a fairness hole, not a security one. Everything
in P2 can ship at any time without changing behaviour.

---

### 11. Open decisions

1. **WhatsApp transport.** Needed for P0.2 and P1.5. Which provider, and is
   sending buyer messages something support can see? This is the only piece
   requiring a decision outside this codebase.
2. **Does checkout require a working email?** Cheap to skip, and skipping keeps
   conversion. A bounce is then handled by P1.5 rather than at the door.
   Recommend skipping the hard gate.
3. **Does the buyer see a short code at all?** A short code is a *readability*
   affordance, not a security one — standalone it must be high-entropy, and so
   is a long token again. Its only real value is being read out over the phone.
   Recommend showing the reference beside the link, not inventing a code.
4. **Where does the current link get sent today?** If buyers already receive it
   by WhatsApp, P0.2 is nearly free. If it is emailed, the two-channel split
   needs a different transport.
5. **How long should the dispute response window be?** `ESCROW_AUTO_RELEASE_DAYS`
   (7) is the obvious default, but a long-distance delivery may need longer and
   a perishable order less. Per-plan, or one number?
6. **Private disk for evidence** — is there one already, or does this need
   configuring? Public S3 or a private bucket changes the signed-URL work.

---

<!-- source: docs/escrow.md (current-state buyer authorisation) -->

## Escrow — current buyer authorisation (what exists today)

> **HISTORICAL 2026-10-23.** `docs/escrow.md` and this section describe a subsystem
> that is being removed. Buyers now pay the seller directly, through the seller's
> own gateway account, and the platform holds nothing. The security question this
> section raises — how a buyer proves they are the buyer before money moves — no
> longer has an answer on this platform, because the platform does not move the
> money.
>
> What survives from the thinking here is the general principle, not the code: a
> capability that travels in a URL is a capability that gets forwarded.

### Proving it is you

Order tracking asks for two things. The escrow page asks for one.

A buyer reaches `/{slug}/escrow/{token}` by having the token, and the token is
the whole authorisation — for reading the page *and* for releasing the money.
The token is 24 hex characters of HMAC, so it cannot be guessed; what it cannot
survive is being passed around. It ends up in a WhatsApp thread, a screenshot,
a shared phone, a forwarded email. Anyone holding it can release the money to
the seller.

So the honest summary of the current posture:

| Action | Needs |
|---|---|
| See the order | reference + contact, or the token |
| **Release the money** | **the token** |
| **Raise a dispute** | **the token** |

Two of the three gates that matter are a single factor, and that factor is a
string designed to be shared.

The fix moves the gate from the URL onto the action: a one-time code, delivered
over a second channel to the email and phone already captured at checkout,
required on release and on dispute. The emailed artefact becomes a link to a
code-entry page rather than the release capability itself, so a compromised
inbox yields nothing on its own. Full design, threat model and sequencing are in
[`escrow_security_plan.md`](../escrow_security_plan.md).

Three smaller things are wrong today and are worth knowing regardless of whether
that plan ships:

**The token never expires.** Order and booking links expire after 30 days
(`BuyerLinks::EXPIRES_DAYS`) precisely because a permanent link to somebody's
address and order history is not worth a convenience. The escrow link is a
*write* link and has no such protection.

**`GET /escrow/{token}` is unthrottled.** Release is capped at 10/min and
dispute at 5/min, but the page itself has no limit at all, so a leaked token can
be probed indefinitely. `track-order` is unthrottled too, and it is itself a
reference-and-contact guessing oracle.

**`findEscrow()` loads every escrow on the page** and compares tokens in PHP:

```php
return Escrow::withoutGlobalScopes()
    ->where('store_page_id', $page->getKey())
    ->get()
    ->first(fn (Escrow $escrow) => hash_equals($escrow->releaseToken(), $token));
```

Correct, and `hash_equals` keeps it timing-safe — but it is O(n) in the seller's
whole escrow history, on every page view, forever. Storing the token in an
indexed column makes it one query.

### When a dispute is raised

Frozen means frozen: `release()`, `refund()` and `resolve()` all refuse to move
anything that is not `held`, so the money stays with the platform until a human
decides. That part is right and should not change.

The gaps are around it.

**There is no evidence to look at.** A dispute is a `reason` string, 20–1000
characters, from one side only. The seller has no way to put their case forward
at all — no delivery photo, no courier receipt, no proof the goods ever left
their hands. So the section above says a human breaks the tie "looking at the
evidence", and in practice the evidence is one text box written by the party
with something to gain.

**A dispute is permanent.** `ESCROW_AUTO_RELEASE_DAYS` applies to a `held`
escrow and will not touch a `frozen` one. Nothing is scheduled. A buyer who
disputes and then stops replying holds the seller's money until an admin
notices, and an admin noticing is not a mechanism.

**The plan** gives both sides symmetric evidence — text plus at least one photo
— on private storage with signed URLs, and adds a response deadline so a dispute
resolves itself when a party goes quiet. Neither is a security control; the
second is a fairness one, and it is arguably the more urgent of the two, since
today the seller cannot present a case at all.

---
