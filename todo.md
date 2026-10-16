# MYPAGE.NG — Build Status (todo.md)

> Source of truth: "MYPAGE.NG — Implementation Plan (Phase 0 to Launch) (1).md".
> [x] = implemented, [ ] = not done.

## Answer to the /page question

`/page` (StorePageController + dashboard/page-editor.blade.php) edits **your storefront page** — the
sections of the current theme (home, products, about, contact, cart, checkout, booking, etc.),
their order, enable/disable, heading/body/image. For product CRUD use the sidebar
**Products & Stock** (/products); for the Service theme use **Services** (/services).
It is NOT a page-builder for products or services: those are managed on their own screens and
rendered from real data on the storefront.

## Previously blocked — now fixed

A service seller had no way to set opening hours anywhere in the UI. `AvailabilityService` read
`working_hours` and nothing outside tests wrote to it, so a published service page showed a real
booking widget that could never return a slot. Every booking test passed because each one seeded
`WorkingHour` rows directly, which hid the gap.

Fixed: `/availability` writes the schedule (`WorkingHourController` + `UpdateWorkingHours`), with
per-staff overrides on the same screen. `tests/Feature/Booking/AvailabilitySettingsTest.php` now
drives availability **only** through the dashboard, so the engine cannot pass while the UI is
broken again.

**Lesson worth keeping:** a green engine is not a working product. Tests that construct their own
fixtures can prove the arithmetic and miss the fact that a customer cannot reach it.

## Phase 0 — Project setup
- [x] Laravel 13 app, domain folders (Auth, Seller), DomainServiceProvider, config/mypage.php, config/themes.php
- [x] Vite + Tailwind 4 + Alpine
- [x] Pest + Pint installed
- [x] Public landing page at `/`: what the product is, both themes, live pricing
      read from the database with a config fallback, and an explicit line that
      browsing needs no account. Sign in is for page owners only.
- [ ] Horizon, Sanctum, medialibrary/media handling, activitylog, larastan CI
- [ ] CI workflow (lint → test → deploy), staging on Forge + wildcard DNS
- [ ] Reserved-slug list usage, *.mypage.test wildcard dev setup documented

## Phase 1 — Auth & onboarding
- [x] sellers, store_pages, store_sections migrations
- [x] Register/login/logout, email verification (OTP notification exists), password reset
- [x] PhoneNumber normalize + NigerianPhone rule, SafeSlug rule
- [x] 4-step onboarding wizard (business → theme → brand → review), session-held state, StorePage created as draft
- [x] EnsureSellerIsActive, EnsureOnboardingComplete middleware
- [x] Theme radio selector fixed: selection highlight + Back buttons on all steps
- [x] SellerRegistered → welcome email listener — **verified 2026-10-15**: `SendWelcomeEmail`
      exists, is queued with `afterCommit`, and is registered in `DomainServiceProvider`.
      This item was stale, not undone.
- [x] Reserved words check in SafeSlug (reads config/mypage.php reserved_slugs)

## Phase 2 — Multi-tenancy & routing
- [x] routes/storefront.php under {slug}.mypage.ng domain group
- [x] ResolveStorePage middleware + SlugResolver + cache
- [x] BelongsToSeller trait
- [x] StorePagePolicy
- [x] StorefrontRoutingTest
- [x] Architecture test for seller scoping — `tests/Feature/Tenancy/SellerScopingArchitectureTest.php`.
      Scans every model under app/Domains for a seller_id column with no BelongsToSeller
      scope. **9 models were found and each is documented**, not silently allowed:
      6 child/admin-read rows in the test's $exempt, 3 (Transaction, Payout, Subscription)
      in an explicit $adminScoped list. Verified none of them leak: every raw query on
      those three lives in AdminController/PlatformMetrics, where cross-seller reads
      are the job. The test also fails if a model gains the trait while still listed as
      exempt, so the documentation cannot go stale.
- [x] Tenancy leak tests for products/orders

## Phase 3 — Theme engine
- [x] config/themes.php registry (store + service, per-page section lists, font pairs)
- [x] StorefrontController serves themes.{store,service} pages: home, about, contact, products/product-show, services/service-show, cart, checkout, booking, order-tracking
- [x] Shared layout, brand colour CSS vars, whatsapp button, section components
- [ ] Home page sections and the service/staff pages still render static demo content (products/services list pages do pass real data)
- [ ] Page HTML cache (Redis tags) + observer invalidation
- [ ] SEO: title/meta/canonical/OG/JSON-LD per page
- [ ] Missing-section-view skip + published-only 404 behaviour verified

## Phase 4 — Dashboard shell
- [x] layouts/app, dashboard home
- [x] Page editor: tabs per page, sections list, enable checkbox, up/down reorder, heading/body/image, saves to store_sections
- [x] Publish flow: PublishStorePage/UnpublishStorePage, StorePagePublished/StorePageUnpublished
      events, per-theme readiness gate, blockers sent back to the checklist
- [x] Dashboard stats card row: orders this week (+ vs last week), revenue from
      paid orders only, customers, open abandoned carts — all scoped to the page
- [x] Getting-started checklist driven by the same PublishReadiness checks
- [ ] Sidebar nav driven by theme (config/dashboard_nav.php) — check layout
- [ ] UI kit components (x-ui.*) — currently hand-rolled per page
- [ ] Live-preview iframe via signed draft URL

## Phase 5 — Catalog (Store)
- [x] categories, products, product_images, product_variants, inventory_logs migrations + models
- [x] ProductFactory/CategoryFactory + Money helper (₦ ↔ kobo)
- [x] ProductController CRUD (dashboard: list/create/edit/delete + bulk delete), image upload to storage, owner check via BelongsToSeller
- [x] CategoryController CRUD + bulk delete, dashboard nav, CategoriesTest
- [x] ProductVariantController (shallow resource) + ProductVariantsTest
- [x] AdjustStock action with inventory_logs
- [x] Public store: category filter/search/pagination + product detail, all covered by StorefrontCatalogTest
- [x] Cart in localStorage + GenerateWhatsAppOrderLink order via WhatsApp (order persisted
      with the reference the buyer quotes, so it matches in the inbox)
- [ ] Multi-image processing job (WebP, thumbnails)
- [ ] CSV bulk import

## Phase 6 — Booking (Service)  ← COMPLETE
- [x] services, staff_members, service_staff, working_hours, time_offs, bookings,
      booking_status_histories migrations + models + factories
- [x] AvailabilityService: opening hours in the store's timezone, buffers,
      time off, half-open overlap, weekday boundaries, per-staff hours, notice
      window — 21 unit tests
- [x] CreateBooking with a lockForUpdate on the store page and a re-check of the
      slot under the lock, so two buyers cannot take one slot
- [x] BookingStatus transitions + BookingStatusChanged + history, mirroring orders
- [x] ExpirePendingBookings frees lapsed holds every minute
- [x] Deposit types (none / fixed / percent) and what the buyer actually owes
- [x] Booking guests land in the same customer book as orders, on one identity rule
- [x] **Opening hours screen** `/availability`: weekly grid, copy-one-day-to-all,
      store-wide hours plus per-staff overrides, saved wholesale so the grid is
      the source of truth
- [x] **Team screen** `/staff`: CRUD, assign staff to services, hide from the
      public page; removing someone drops their hours but keeps their bookings
- [x] **Dates off** `/availability/time-off`: all-day or part-day blocks, whole
      shop or one person
- [x] RescheduleBooking under the same row lock, refusing a slot taken meanwhile;
      CancelBooking frees the slot
- [x] CancellationPolicy: seller-defined window, stated on screen rather than
      applied silently, with the customer's number shown so the seller can ring
- [x] Reminders at 24h and 2h, tracked separately so neither can send twice;
      each carries a wa.me link the seller can forward by hand
- [x] WhatsApp booking request: records the request, holds **no** slot so it can
      never block a paying customer, lands in the inbox for the seller to confirm
- [x] Dashboard: services CRUD, bookings inbox (today/upcoming/past) + detail,
      and a week calendar loaded in a single query
- [x] Public booking widget: service → staff → date → slot (fetched from
      /availability) → details → pay or confirm, plus a confirmation page
- [x] Unified inbox for store (orders) and service (bookings) — both sides built

Still open in this area, all small:
- [ ] Booking reschedule for the *buyer* (today the seller moves it)
- [ ] Turn the two reminder emails into real WhatsApp sends — **BLOCKED ON PHASE 11,**
      which is post-MVP by owner decision 2026-10-15. Not actionable before launch;
      reminders stay email-only in the meantime.

## Phase 7 — Payments
- [x] PaymentGatewayInterface + Paystack/Flutterwave
- [x] transactions polymorphic payable, webhooks (HMAC, idempotency, queue)
- [x] wallets + wallet_ledger double-entry, CommissionCalculator
- [x] Payouts (hold in payouts pool, re-credit on failure)
- [x] Store checkout CreateOrderFromCheckout (re-price server-side)
- [x] Checkout thank-you view; gateway callback re-verifies server-side
- [x] Service checkout deposit/full: PublicBookingController charges a Booking through
      the same InitializePayment, free services confirm outright
- [ ] Verify gateway field names against live docs

## Phase 8 — Orders inbox / customers
- [x] orders, order_items, order_status_histories, OrderStatus enum (transition map)
- [x] OrderSource splitting WhatsApp vs on-platform orders
- [x] Dashboard orders inbox split into WhatsApp / On-platform tabs
- [x] Abandoned carts: captured when the buyer leaves their email at checkout,
      one open cart per buyer, closed out when they convert or the seller writes
      them off, expired after 30 days
- [x] RemindAbandonedCarts (hourly) sends one reminder, dashboard list with a
      WhatsApp nudge link and a recovery link
- [x] Notifications: seller on every new order, buyer on Paid/Shipped/Delivered/
      Cancelled/Refunded (quiet on Pending/Processing)
- [x] Order tracking actually looks an order up by reference + email/phone, so
      the link in the buyer's email works and cannot leak another buyer's order
- [x] Unified inbox for store (orders) and service (bookings) — both built, sharing
      the Blade table, status badges and detail drawer pattern
- [x] Customers: upsert by seller+phone (email fallback, records merge when a
      phone arrives later), LTV/order count/last activity, opt-in flag
- [x] Dashboard customer list + search, detail with order history and notes
- [x] Lifetime value follows order status in (Paid) and back out (refunded,
      cancelled), via OrderStatusChanged

## Phase 9 — Billing, plans, escrow  ← DONE
- [x] plans, subscriptions, EnsureSubscriptionValid, ExpireSubscriptions
- [x] Feature::enabled() helper
- [x] Escrow ledger, ReleaseEscrow, disputes
- [x] Verification badge application + admin review
- [x] Escrow release link and dispute button in the buyer/seller UI
      - Buyer page at `/{slug}/escrow/{token}`, token-gated, no login required
      - Buyer releases early or freezes the money with a reason
      - Seller disputes from the order detail page
      - Release link surfaced on order tracking once reference + contact match
- [x] Fixed: `OpenEscrowAfterPayment` trusted the feature flag at job-run time
      instead of checking where the money actually went. A sale that settled
      straight to the seller could be recorded as escrowed, and the first release
      paid the seller a second time while the pool went negative.

## Phase 10 — Admin panel  ← DONE (minimum viable)
- [x] `is_admin` flag on sellers, `EnsureUserIsAdmin` returns 404 not 403
- [x] `CrossTenant` switch so the panel reads across seller_id scopes
- [x] Overview: GMV from settled orders only, commission, escrow held, disputes
- [x] Badge queue with all three states, approve/reject, rejection reason required
- [x] Verified tick rendered on both storefront themes
- [x] Escrow queue with dispute resolution, frozen escrow settles exactly once
- [x] Store moderation: suspend with a mandatory reason, reinstate to draft
- [x] Append-only audit log, immutable at the model layer
- [x] Seller withdrawals, self-service (no admin in the loop)
      - `/payouts`: available balance, bank details, request, history
      - Held escrow is shown but excluded from what is withdrawable
      - Bank account name confirmed with the gateway before it is saved
      - Gateway failure reads as a message, not a 500
- [x] Transaction browser: every payment across sellers, filter by status,
      gateway and reference, with ours and the gateway's reference side by side
- [x] Payout queue showing only failures and in-flight transfers, with the
      stranded balance. No retry button on purpose: a failed payout has already
      returned the money to the seller, who can request it again themselves
- [x] Demo seed now creates transactions and payouts so both screens are non-empty
- [ ] Feature-flag toggles from the panel
## Phase 11 — Messaging (WhatsApp Cloud API)  ← DEFERRED TO POST-MVP

**Re-confirmed deferred by the owner 2026-10-15.** All Meta Business, App Review,
Embedded Signup and Cloud API console work is post-MVP, not merely "later".
Nothing in Phases 0-10 or 12-16 may block on it, and the A/B/C decision above is
not to be revisited before launch.

**Knock-on:** Phase 13 (TLS) is no longer on the critical path for messaging —
it is only for custom domains now. Phase 6's reminder channels stay email-only.
The `wa.me` link hand-off remains the WhatsApp story until then.

**Deferred 2026-10-11, deliberately.** Not a technical blocker — the design was worked
out and a client was connecting. It is deferred because it only pays off with real
sellers on the platform, and because the pricing moved underneath us mid-build.

### Why it is not an MVP feature
- **There is no free tier on the Cloud API.** It needs a Meta Business account with a
  payment method. The free WhatsApp Business *app* has no API access at all.
- **Getting on it is slow and gated by Meta.** As a Solution Partner we need App Review
  plus Advanced access to `whatsapp_business_management`. That is a wait we do not control.
- **Until then, `wa.me` links do the same job for free.** A seller taps, the buyer
  initiates. Not equivalent, but not a hole in the product.

### Pricing facts, verified against Meta's docs (needed again when this restarts)
Charged per **delivered** message, not per API call — cost accounting must read delivery
webhooks, never count `POST /messages`. Rate is per recipient market, not where we are
registered. Marketing has no volume tiers; utility and authentication do.

Meta's **1 October 2026** change removed the free parts we had assumed:

| Message | Through 30 Sep 2026 | From 1 Oct 2026 |
|---|---|---|
| Inbound message from a customer | Free | Free |
| Free-form reply inside 24h window | Free | 1,000 free per number per month, then charged |
| Utility template inside the window | Free | **Charged from message #1** |
| Utility template outside the window | Charged | Charged |
| Marketing template | Charged | Charged |

So "order updates are basically free" is no longer true, and any cost model that treats
utility as the cheap tier is wrong.

### The decision that actually matters: whose number sends?
The buyer always sees the sending number. WhatsApp is phone-to-phone — there is no way to
fake the sender. So the only question is whose number it is, and it decides everything:

| | Buyer sees | Who pays Meta | Onboarding |
|---|---|---|---|
| **A. Seller's own number** (Embedded Signup) | The seller | Seller, direct to Meta | Seller verifies their number by SMS |
| **B. Platform number** | `mypage.ng` | Us, metered and billed on | Seller clicks connect |
| **C. Both** | Either | Either | A preferred, B as fallback |

**C is the recommendation.** A is the correct long-term answer: it keeps the buyer's
relationship with the seller and puts the cost on the seller, never on our books.
Embedded Signup exists precisely so a platform can onboard client business numbers.

Two constraints that are not optional:
- Meta requires that **"opt-in must clarify your business name and intent"**. Consent text
  must name the seller (*"Ada Textiles will message you about your order"*), never us.
- Under **B**, every message must carry the seller's name and real contact, otherwise the
  buyer cannot reach the seller and cannot reply to them. This is why the template design
  must carry seller name + contact from day one even under A — it makes a later B→A switch
  painless.

**The risk with B**, which is why it is only a fallback: Meta's quality rating is **per
phone number**. One shared number broadcasting to thousands can get rate-limited or banned,
and that takes down *every* seller on it, not just the offender.

### Access
Starter plan and above, with a monthly message allowance per plan, since every message now
costs money.

### What was built and then removed
A working slice existed — connection model with the access token encrypted at rest,
templates with category tagging, message log with forwards-only status application, the
Cloud API client, and a signature-verified webhook. It was removed rather than left as
dead code, because it was untested and its cost model was already wrong. The decisions
above are what was expensive to work out; the code was not.
## Phase 12 — Directory & SEO  ← IN PROGRESS
- [x] Directory index at `/directory`, published pages only
- [x] Filters by theme, state, city, category and search; clean URLs like
      `/directory/lagos` that survive being pasted into a WhatsApp chat
- [x] Verified pages sort above unverified, then newest
- [x] Fixed: `Feature::enabled()` returned false whenever the platform default was
      off, so a per-seller row could only ever turn a feature *off*. The docblock
      promised the opposite and every dark feature would have made the new admin
      toggle a control that does nothing. A per-seller row now wins in both
      directions, and `FEATURES_LOCKED` is a separate panic switch that overrides
      everything
- [x] `sitemap:generate`, sharded above 50k URLs with a sitemap index
- [x] robots.txt served by a controller so the Sitemap line carries the real host
      and the disallow list is derived rather than remembered
- [x] JSON-LD in both themes: `OnlineStore` with real offers and prices for the
      store theme, `HealthAndBeautyBusiness` with an offer catalogue for services
- [x] Fixed: a filtered or paginated listing still described the whole catalogue,
      so a search snippet advertised products the page was not showing. The
      storefront now passes what it is actually rendering
- [x] Fixed: `store_pages.verified_at` was never written by anything. Approving a
      badge now stamps the page, which is what the directory sort, the sitemap
      priority and the structured data all read
- [x] Canonical URL and OpenGraph tags on both storefront themes
- [ ] OG images per seller

## Phase 12b — Storefront rebuild to the Part 9 theme model  ← DONE

Part 9 of the plan supersedes Phases 3–4: each theme is a fixed multi-page site, and the only
design setting is the brand colour. There is no page builder, no section switches and no drag
handles. What a seller edits is content, and each block declares only the fields it uses.

- [x] `store_sections` became `page_blocks`. The `position` and `is_enabled` columns are gone;
      a block that is empty does not render, which replaces the switch a seller used to flip
- [x] `Theme::sections()`/`pageSections()` became `blockKeys()`/`pageBlocks()`. `StoreSection`
      and its factory are deleted
- [x] `BlockRenderer::forPage()` resolves theme order and drops empty blocks;
      `editableForPage()` still returns the empty ones, because the settings form is the one
      place a block needs an input before it has content
- [x] `StorefrontSettingsController` replaces the page builder. Fields are saved under their
      own names — they used to share a single `string` input, so a hero's headline and button
      label overwrote each other and only the last one submitted survived
- [x] Validation rules are derived from the block schemas, so a block cannot declare a field
      the controller never checks
- [x] Fixed: a review row with a name and no quote survived as a nameless quote's author.
      `quote` is marked required and such rows are dropped on save
- [x] Brand tab: colour, logo and cover photo. These are page settings rather than blocks, so
      they survive whichever tab is being edited and feed the directory listing and JSON-LD.
      The logo renders in the nav and the cover is the store hero
- [x] Onboarding no longer writes 21 empty rows per seller. A new page starts blank
- [x] Fixed: the booking widget, the booking confirmation and the service listings were
      rendered as blocks, so an empty block silently removed them from the page. They are
      layout now, and only the introduction above them is content
- [x] `StorefrontPageSmokeTest` walks every page of both themes and asserts on content, because
      a page that renders nothing still returns 200
- [x] Service home blocks are real components, not one generic heading-and-body shared by
      all of them. `services` and `gallery` list the seller's active services, `team` their
      active staff, `booking` signposts at the booking page, `hero` sends its call to
      action at `/book` rather than the product list, and `about` and `contact` mirror the
      store theme. Each one hides itself when the seller has nothing to show, and each is
      covered by a test that proves both halves
- [x] The gallery reuses the photos already uploaded against services rather than adding a
      second upload form. One set of images, used twice
- [x] Service detail page renders the service itself, not an optional intro block
- [x] Contact page renders the seller's business details even with nothing written there
- [x] Business details tab: tagline, WhatsApp, address, city, state, public email
- [x] Social handles: six platforms, stored normalised as URLs, rendered in footer and
      contact page, added to JSON-LD sameAs for both themes
- [ ] Per-seller OG images

## Phase 13 — Custom domains  ← DONE (DNS verified, TLS issuance pending a real edge)

A seller's own hostname, CNAMEd at us and served once DNS proves it.

- [x] `domain_bindings` table. Unique on `host`, not on the pair: a domain can
      only ever resolve to one seller, and that constraint is what stops two
      sellers claiming the same hostname
- [x] `Hostname` support class. Canonicalises what a seller types — strips a
      scheme, lowercases, drops a trailing dot — and refuses what is not a bare
      domain: an IP, credentials, a port, a path. The host is the security
      boundary for the whole feature, so it is normalised in one place and
      stored only in canonical form
- [x] `ReservedHostnames`. The apex and anything under it are refused, so a
      seller cannot serve a page from `mypage.ng` and impersonate the platform's
      own sign-in and checkout. A host that merely ends with the same letters,
      like `notmypage.test`, is not caught by this and should not be
- [x] `DomainVerifier` behind a `DnsResolver` contract. Only a binding whose
      CNAME resolves to our published target goes live. Behind the contract
      because this is the security boundary and has to be provable in tests
      without touching real DNS
- [x] A live domain is re-checked for drift, and suspended rather than left
      serving a shop the seller no longer controls
- [x] `VerifyDomainDns` job, unique per binding with backoff, plus an hourly
      sweep, because DNS propagation is measured in hours and a seller who set
      the record and walked away should still find their shop live
- [x] `ForwardCustomDomainToStorefront`. On a seller's own domain there is no
      slug in the path, so `/products` is ambiguous with the seller's dashboard.
      The request is re-issued with the slug put back in front, which reuses the
      storefront that already exists instead of maintaining a second copy
- [x] Registered ahead of `auth` in the middleware priority list. Laravel forces
      `AuthenticatesRequests` ahead of any unregistered middleware, so without
      this a customer landing on `shop.com/products` was bounced to the seller
      login screen
- [x] Platform pages are not reachable from a seller's domain: `/login`,
      `/dashboard` and `/orders` 404 there rather than rendering ours
- [x] An unclaimed hostname gets a 404, not our marketing page. Serving the
      platform's front door on somebody else's domain is how a parked or
      hijacked domain ends up looking like an official mypage.ng page
- [x] Gated behind `FEATURE_CUSTOM_DOMAINS` and `billing.custom_domain_plans`,
      which is Pro
- [x] Seller dashboard at `/domains`: add a domain, see the exact CNAME record
      to create, check again on demand, remove
- [ ] TLS issuance. The edge (Caddy on-demand TLS or Cloudflare for SaaS) is not
      wired up, so a live binding is served over the platform. `ssl_active_at`
      exists for the edge to report back through, and is cleared on suspension
## Phase 14 — Sanctum API  ← NOT STARTED (intentionally last)

## Phase 15 — Hardening & launch  ← IN PROGRESS
- [ ] Rate limiting, CSP/security headers, upload validation review
  - [x] Baseline `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy` on web routes
  - [x] HTTPS-only HSTS and a report-only CSP with a sanitized, throttled `/webhooks/csp-report` endpoint
  - [ ] CSP enforcement, inline-handler/Alpine eval removal, and upload-validation review
- [ ] Horizon supervisors, Sentry, uptime, backups
- [ ] N+1 audit, page cache hit-rate
  - [x] `Model::preventLazyLoading()` outside production
  - [x] Fixed strict-mode hotspots in storefront block rendering and product-variant price fallback
  - [ ] Page cache hit-rate review
- [ ] Privacy/terms, NDPR consent, data export/delete
- [ ] Demo seed pages, private beta rollout

## Phase 16 — Discovery Hub & catalog filtering  ← IMPLEMENTED, tests deferred

Two briefs merged into one phase, because they share a query layer and must not drift:

- **A.** Backend filter/search/sort architecture for the two storefront themes.
- **B.** A public discovery hub that categorises the whole platform by business type.

All the building below is done and runs. The test work is deliberately left open —
see 16.9. Nothing here has been proved by CI, only by hand.

### 16.0 Schema reality (read before writing anything)

The brief said "add discovery fields to the existing `businesses` table". There is no
`businesses` table. The public business is **`store_pages`** (`App\Domains\Seller\Models\StorePage`),
owned by `sellers`, bound per request by `ResolveStorePage` + the `BelongsToSeller` global scope.
Adapting to the real schema rather than inventing a parallel table:

| Brief says | Actually | Decision |
|---|---|---|
| `businesses` table | `store_pages` | migration targets `store_pages` |
| `business_type` enum(store, service) | `theme` string(16), cast to `Theme` enum | reuse `theme`; no second type column |
| `city`, `address` | already present | not re-added |
| `is_directory_opted_in`, `price_tier`, `rating`, `review_count`, `is_featured` | absent | **added** |
| `view_count` (for "popular") | absent | **added** to pages, products, services |
| tags table for taxonomy filtering | no `tags` table exists | taxonomy filtering uses `categories`; SKU is searchable on products |
| ratings from reviews | no review source table exists | `rating`/`review_count` are **cached aggregates**; a seller cannot type their own |
| `dark:` classes | storefronts carry a seller-chosen surface mode via `ThemeTokens` | use runtime tokens (`var(--brand)`, `var(--paper)`, `var(--ink)`); `dark:` would fight the token system |
| `/discovery` | `/directory` exists behind `feature:directory` | new hub at **`/discover`** behind a separate `discovery` flag; the two must not be conflated |

### 16.1 Database  ← DONE
- [x] `add_discovery_fields_to_store_pages_table`: `is_directory_opted_in`, `price_tier` (1-4),
      `rating`, `review_count`, `is_featured`, `view_count` + discovery composite index
- [x] `add_discovery_fields_to_products_table`: `view_count` + `(store_page_id, status, price_kobo)`
- [x] `add_discovery_fields_to_services_table`: `view_count`, `is_featured`, `billing_unit`,
      `turnaround_label`, `deliverables` (json) + `(store_page_id, is_active, price_kobo)`
- [x] Backfill-safe: every new column defaults, so `migrate` runs against live rows
- [x] Reverse cleanly in `down()`

### 16.2 Enums  ← DONE
- [x] `Seller\Enums\PriceTier` — Budget/Standard/Premium/Luxury, label + naira glyph
- [x] `Catalog\Enums\ProductSort` — latest / price_low / price_high / popular, each owning its
      own query; `tryFromRequest()` falls back to `Latest`
- [x] `Booking\Enums\ServiceSort` — featured / latest / price_low / price_high / popular
- [x] `Booking\Enums\BillingUnit` — per project / per hour / per month, with its suffix

### 16.3 Query layer  ← DONE
- [x] `Catalog\Services\ProductCatalogQuery` — search (name, description, SKU; `%` and `_` escaped),
      category (single **or** multi), price band, sort, `withQueryString()`
- [x] Filter **bounds** (price min/max) and per-category **counts** for the sidebar, both derived
      from the same builder as the rows so they cannot drift
- [x] Bounds come from the whole active catalogue, not the filtered set — bounds that collapse with
      the filter remove the only control that can widen it again
- [x] `Booking\Services\ServiceCatalogQuery` — search, price band, max duration, billing unit,
      featured-only, sort, **tier breakdowns**, units actually in use
- [x] `Directory\Services\DiscoveryQuery` — the published + opted-in rule stated **once**, as
      `DirectoryQuery` already does, so no controller can forget it
- [x] Facets that only offer choices that actually return rows (cities, tiers, types)
- [x] Round-robin 3-way column split; empty columns dropped rather than left as a gap
- [x] `Popular` is one correlated subquery (`withSum`/`withCount`), never N per row, and counts
      only orders/bookings that count towards lifetime value

### 16.4 Form requests  ← DONE
- [x] `Catalog\Http\Requests\ProductFiltersRequest` — `q`, `category[]`, `min`, `max`, `sort`;
      naira in, kobo out; folds a scalar `category=3` into an array so old links still work
- [x] `Booking\Http\Requests\ServiceFiltersRequest` — `q`, `min`, `max`, `duration`, `unit`,
      `featured`, `sort`
- [x] `Directory\Http\Requests\DiscoveryRequest` — `q`, `type`, `city`, `tier`, `sort`
- [x] Nothing here can fail on a value a person typed. A stale bookmark must land on a page, not a
      422 — a visitor is looking at a shop, not filling in a form

### 16.5 Models  ← DONE
- [x] `Product` — `view_count` cast, `orderItems()`, `scopeSearch`, `scopeInCategories`,
      `scopePricedBetween`, `scopeSorted`, `isDiscounted()`, `discountPercent()`, `isSoldOut()`
- [x] `Service` — `billing_unit`/`deliverables` casts, `scopeSearch`, `scopePricedBetween`,
      `scopeWithinDuration`, `scopePricedPer`, `scopeFeaturedOnly`, `scopeSorted`,
      `deliverableList()`, `priceWithUnitLabel()`
- [x] `StorePage` — discovery casts, `scopeDiscoverable()`, `scopeOfTheme()`, `scopeFeaturedOnly()`,
      `isDiscoverable()`, `ratingLabel()`, `hasRating()`, `locationLabel()`, `products()`

### 16.6 Store theme — catalog & filter engine  ← DONE
- [x] `StorefrontController::products()` driven by `ProductCatalogQuery` (was an inline `when()` chain)
- [x] `themes/store/products.blade.php` — `lg:grid-cols-4`, sticky `col-span-1` rail, `col-span-3` grid
- [x] Mobile off-canvas drawer (Alpine, `x-cloak`, Esc to close)
- [x] `partials/catalog-filters.blade.php` rendered **once** and included twice, so the rail and the
      drawer cannot drift; a `$idPrefix` keeps their input ids distinct
- [x] Search box with a working clear button; the sort select rides in the same form
- [x] Category accordion (`x-show`, not `x-collapse` — that plugin is not in the bundle) with counts
- [x] Price min/max numeric inputs bounded by the real catalogue range
- [x] Active-filter chips, each removable on its own, plus Clear all; the mobile badge counts them
- [x] `partials/product-card.blade.php` — `aspect-square`, hover zoom, category pill, compare-at
      strikethrough guarded by `isDiscounted()`, discount badge, sold-out, add-to-cart spinner
- [x] Pagination preserves every active filter
- [x] Empty state distinguishes "nothing listed yet" from "nothing matched"

### 16.7 Service theme — directory & tier cards  ← DONE
- [x] `StorefrontController::services()` driven by `ServiceCatalogQuery`, still passing `$blocks`
- [x] `themes/service/services.blade.php` — hero with integrated debounced search + quick pills
- [x] Duration pills (30/60/120/180 min) and the billing units actually in use
- [x] `grid-cols-1 md:grid-cols-3 gap-8` tier grid with the tier breakdown above it
- [x] `partials/service-card.blade.php` — Featured ribbon, deliverable tick-list (4 shown, rest
      counted), duration, turnaround, billing suffix, deposit note, staff count, CTA to
      `/services/{slug}`
- [x] Removable filter chips, because the pills that set them are up in the hero and out of sight
- [x] Empty state

### 16.8 Discovery hub  ← DONE
- [x] `Directory\Http\Controllers\DiscoveryController::index()`
- [x] Full page on first load; Blade **partial** when `X-Requested-With: XMLHttpRequest` or
      `X-Partial: results` — same URL, so pagination and shared links still work
- [x] `discovery/index.blade.php` — hero, type switcher with live counts, keyword input, city
      dropdown, tier pills, live swap between marquee and grid
- [x] Every filter control is a real `<a href>`; the Alpine layer only intercepts the click and
      falls back to a real navigation on any failure
- [x] `discovery/partials/marquee.blade.php` — 3 columns, col 1 up / col 2 down / col 3 up, pauses
      on hover **and** on focus, middle column offset
- [x] `discovery/partials/business-card.blade.php` — glassmorphism on hover, `/{slug}` CTA,
      🛍️ Store vs 📅 Booking badge, rating + review count, price tier, Featured flag
- [x] `discovery/partials/results.blade.php` — responsive grid, chips, pagination, empty state
- [x] Marquee keyframes in `resources/css/app.css`, with `prefers-reduced-motion` stopping the
      animation outright rather than slowing it
- [x] The duplicated card list is `inert`, not just `aria-hidden`, so it is not a second tab stop
- [x] Below `lg` the marquee is replaced by a static grid: a moving list on a touch screen is a
      list you cannot tap
- [x] `routes/web.php` at `/discover` behind `feature:discovery`; `config/mypage.php` flag added
- [x] Empty-hub state that says so plainly rather than showing a blank grid

### 16.9 The part that makes it real: a seller can actually set any of this  ← DONE
A migration that adds columns nothing writes is a feature that cannot work.
- [x] `StorefrontSettingsController` — new **Directory** tab: opt-in checkbox + price tier.
      Tab only appears while `feature:discovery` is on
- [x] `rating`, `review_count` and `is_featured` deliberately **not** seller-editable — a rating a
      seller types themselves is worth nothing to the next person who reads it
- [x] `ServiceController` + service form — `billing_unit`, `turnaround_label`, a one-per-line
      `deliverables` textarea, and `is_featured` as a switch separate from `is_active`
- [x] `RecordProductView`, `RecordServiceView`, `RecordStorePageView` — `view_count` is incremented,
      so "Best Sellers" and "Most booked" are not permanently empty
- [x] Counters are raw `UPDATE`s: a read-modify-write would add a SELECT and a hydration to every
      page render for a number nothing reads back in that request
- [x] `DemoSellerSeeder` — both demo businesses opted in at different tiers, and the service seller
      has deliverables, turnarounds, one featured service and one per-hour service, so the hub is
      not empty on a fresh `migrate:fresh --seed`

### 16.9b Reviews — a real subsystem, replacing seller-typed testimonials  ← DONE
The `reviews` home block on both themes was always a seller-typed testimonial
repeater: the seller writing their own customers' praise, under a heading that
said "Reviews". Worth having, and not a review. Nothing aggregated it, and no
product or service had any rating at all.

- [x] `create_reviews_table` — morph to business / product / service, `author_name`,
      `author_location`, `rating` 1–5, `body`, `is_anonymous`, `is_featured`,
      `status` (pending → approved → rejected), `published_at`
- [x] Cached `rating` + `review_count` on `store_pages`, `products` and `services`.
      A cached column, not a COUNT per row: a grid of twelve products would
      otherwise be thirteen queries to draw a screen of stars
- [x] `Shared\Traits\HasRating` — one `recalculateRating()` and one
      `published()` scope shared by all three reviewables, because "a count must
      only contain approved rows" has to hold identically on every one of them
- [x] **Everything lands pending.** Buyer-authored text on a public page, plus a
      score derived from it, is a legal and reputational problem — not a nicety
- [x] Recalculated on *every* status change including declining one. A score that
      only ever climbs is the one number nobody should trust
- [x] Public write endpoints, all throttled 5/min, all resolving the reviewable
      from the route so a crafted request cannot review a competitor's product:
      `POST /{slug}/reviews/business`, `/reviews/product/{slug}`, `/reviews/service/{slug}`
- [x] `x-ui.stars` — the single place a star rating is drawn. The count is never
      omitted when there is a score, and zero renders nothing at all rather than
      "0.0 out of 5", which is a claim rather than an absence
- [x] `x-ui.review-form` — five real radio buttons visually replaced by stars, so
      the keyboard model and the screen-reader narration are the browser's
- [x] `x-ui.review-list` — approved-only, parametrised for embedding
- [x] Mounted on: product detail, service detail, and the contact page of both
      themes (the business itself)
- [x] Star summary on product cards, service cards and discovery cards
- [x] Seller moderation inbox at `/dashboard/reviews` — status tabs carrying their
      own counts, Publish / Decline / Pin, nav badge with the waiting count
- [x] Home `reviews` block now shows real approved reviews first, falling back to
      the seller's typed testimonials **labelled as such**
- [x] Anonymous reviews render as "A customer", never as an empty name
- [x] Body sanitised on write, so no reader of the row has to remember to escape it
- [x] Demo seeder writes approved reviews on both businesses and three products,
      plus one left pending in each inbox — a demo where everything is already
      published never exercises the only part of this feature that is load-bearing

### 16.10 Tests  ← **DEFERRED, DELIBERATELY**
Written, run and green when they were built. Left here as the next thing to pick up rather than
being treated as finished, because the seller-facing surfaces in 16.9 and the view counters were
added *after* they were written and have no coverage at all.
- [ ] `tests/Feature/Catalog/StorefrontFiltersTest.php` (22 tests) — exists and passes. Note it
      asserts membership and ordering through the **paginator**, not `assertSee`/`assertDontSee`:
      these pages carry a JSON-LD block listing every item, so a filtered-out product is still
      present somewhere in the response
- [ ] `tests/Feature/Discovery/DiscoveryTest.php` (27 tests) — exists and passes
- [ ] **Missing:** the new **Directory** tab on `storefront.edit` — opt-in writes, opt-out writes,
      tier writes, and that a draft page still stays out of the hub with the switch on
- [ ] **Missing:** the new **service form** fields — `billing_unit`, `turnaround_label`, the
      deliverables textarea (including blank-line dropping), `is_featured`
- [ ] **Missing:** the three view counters, including that a product page view does not clobber a
      price being edited at the same moment
- [ ] **Missing:** the discovery tab must not appear while `feature:discovery` is off
- [ ] Existing suites (`tests/Feature/Seller`, `tests/Feature/Tenancy`, `tests/Feature/Directory`,
      `tests/Feature/Booking`) were re-run and pass; the full suite was green at 619/620 at the
      point the 16.9 surfaces were added. Re-run it.

### 16.11 Verified by hand, not by test
- [x] `vendor/bin/pint` clean
- [x] `npm run build` clean
- [x] `migrate:fresh --seed` runs
- [x] Every new and changed page returns 200 against the seeded demo data, in both the filtered
      and unfiltered state, for both themes
- [x] Marquee renders 3 columns with `up`/`down`/`up`, duplicate cards `inert`, badges present
- [x] Service page renders `/project`, `/hour`, tier glyphs, deliverables, "Featured", "Most booked"

## Demo data (so you can click around)

`php artisan migrate:fresh --seed` builds two sellers, one per theme, with real history on
every screen. Password for both accounts is **`password`**, and both are already email-verified
so the dashboard opens without a detour.

| Seller | Email | Theme | Storefront |
|---|---|---|---|
| Ada Textiles | `ada@mypage.test` | store | `/ada-textiles` |
| Kemi Braids | `kemi@mypage.test` | service | `/kemi-braids` |
| Ops Admin | `admin@mypage.test` | — | `/admin` |

The store seller has 8 products across 3 categories, 4 orders in mixed states, 4 customers with
lifetime value, one abandoned cart and an active subscription. The service seller has 5 services
with deposits, 3 staff, six days of opening hours, 4 bookings across past/today/upcoming, and a
Pro subscription. `DemoSellerSeeder` is safe to re-run — it wipes the demo tables first.

Both demo businesses are also opted into the discovery hub at `/discover` (Phase 16), at different
price tiers, with the service seller's card carrying deliverables, a turnaround, one featured
service and one priced per hour. Turn `FEATURE_DISCOVERY=true` on to see it.

Storefront routes need the subdomain or path prefix. `config('mypage.path_routes')` is true, so
`/{slug}/...` works without wildcard DNS.

## Current state in one line
Both themes are complete and usable by a real seller. Store: products → WhatsApp or paid checkout
→ orders → customer book → abandoned carts → tracking → notifications. Service: services → team →
opening hours and dates off → public booking widget → paid or confirmed booking → bookings inbox
with a week calendar → reminders → reschedule and cancel. Both share one payment flow, one customer
book and one identity rule, one billing/escrow ledger and one verified badge.
**562 tests pass.** Done: orders, customer book, abandoned carts, booking, plans and
subscriptions, escrow, verified badge, admin panel, landing page, demo seed, seller withdrawals,
directory, sitemap, robots.txt, JSON-LD, and the storefront rebuilt onto the Part 9 fixed-theme
model with per-block content fields, a brand colour/logo/cover tab, editable business
details, and social media links. Phase 15 has started with baseline security headers,
report-only CSP telemetry, and strict N+1 detection.
Not started: Sanctum API, and TLS issuance for custom domains pending a real edge. WhatsApp Cloud API is deferred to post-MVP with its
reasoning recorded in Phase 11.

Phases 12, 12b and 13 are complete. Phase 13 still needs an edge to issue
certificates against. Phase 15 hardening is underway; its remaining work is CSP
enforcement, reliability/observability, privacy/compliance, and launch rollout.
