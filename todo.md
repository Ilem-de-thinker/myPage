# MYPAGE.NG — Build Status (todo.md)

> Source of truth: "MYPAGE.NG — Implementation Plan (Phase 0 to Launch) (1).md".
> [x] = implemented, [ ] = not done.

## Answer to the /page question

`/page` (StorePageController + dashboard/page-editor.blade.php) edits **your storefront page** — the
sections of the current theme (home, products, about, contact, cart, checkout, booking, etc.),
their order, enable/disable, heading/body/image. For product CRUD use the sidebar
**Products & Stock** (/products) — a basic dashboard exists now (Phase 5 first slice).
It is NOT a page-builder for products: the storefront's products/cart/checkout pages still
render static theme section content.

## Phase 0 — Project setup
- [x] Laravel 13 app, domain folders (Auth, Seller), DomainServiceProvider, config/mypage.php, config/themes.php
- [x] Vite + Tailwind 4 + Alpine
- [x] Pest + Pint installed
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
- [ ] SellerRegistered → welcome email job listener (event exists, listener/mail not wired?)
- [ ] Reserved words check in SafeSlug (uses config/mypage.php? verify)

## Phase 2 — Multi-tenancy & routing
- [x] routes/storefront.php under {slug}.mypage.ng domain group
- [x] ResolveStorePage middleware + SlugResolver + cache
- [x] BelongsToSeller trait
- [x] StorePagePolicy
- [x] StorefrontRoutingTest
- [ ] Confirm no other model has seller_id without the trait (architecture test)
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
- [ ] Sidebar nav driven by theme (config/dashboard_nav.php) — check layout
- [ ] UI kit components (x-ui.*) — currently hand-rolled per page
- [ ] Live-preview iframe via signed draft URL
- [ ] Publish flow (PublishStorePage action, required-sections check, cache bust)
- [ ] Dashboard stats, getting-started checklist

## Phase 5 — Catalog (Store)  ← IN PROGRESS
- [x] categories, products, product_images, product_variants, inventory_logs migrations + models
- [x] ProductFactory/CategoryFactory + Money helper (₦ ↔ kobo)
- [x] ProductController CRUD (dashboard: list/create/edit/delete), image upload to storage, owner check via BelongsToSeller
- [x] Dashboard nav links to Products, products feature tests
- [ ] Category CRUD UI (model/table exist, no controller yet)
- [ ] Multi-image processing job (WebP, thumbnails)
- [ ] Variants repeater UI
- [x] AdjustStock action with inventory_logs
- [ ] CSV bulk import
- [ ] Public store: category filter/search/pagination, product detail
      (code exists; `it_filters_products_by_category_and_search` currently fails)
- [x] Cart in localStorage + GenerateWhatsAppOrderLink order via WhatsApp
      (order is persisted with the reference the buyer quotes; a
       `localStorage` assertion in StorefrontCatalogTest still fails)

## Phase 6 — Booking (Service)  ← NOT STARTED
- [ ] services, staff_members, service_staff, working_hours, time_off, bookings migrations + models
- [ ] AvailabilityService (unit-tested, day boundaries, overlap)
- [ ] CreateBooking with lockForUpdate, slot expiry job
- [ ] Bookings inbox, status history, cancel/reschedule
- [ ] Public booking widget (service → staff → date → slot → details → WhatsApp/pay)

## Phase 7 — Payments
- [x] PaymentGatewayInterface + Paystack/Flutterwave
- [x] transactions polymorphic payable, webhooks (HMAC, idempotency, queue)
- [x] wallets + wallet_ledger double-entry, CommissionCalculator
- [x] Payouts (hold in payouts pool, re-credit on failure)
- [x] Store checkout CreateOrderFromCheckout (re-price server-side)
- [ ] Service checkout deposit/full
- [ ] Checkout thank-you view; verify gateway field names against live docs

## Phase 8 — Orders inbox / customers
- [x] orders, order_items, order_status_histories, OrderStatus enum (transition map)
- [x] OrderSource splitting WhatsApp vs on-platform orders
- [x] Dashboard orders inbox split into WhatsApp / On-platform tabs
- [ ] Unified inbox for store (orders) and service (bookings) — orders side done
- [ ] Customers (upsert by seller+phone, LTV, opt-in)
- [ ] Abandoned carts, notifications

## Phase 9 — Billing, plans, escrow  ← NOT STARTED
- [ ] plans, subscriptions, EnsureSubscriptionValid, ExpireSubscriptions
- [ ] Feature::enabled() helper
- [ ] Escrow ledger, ReleaseEscrow, disputes
- [ ] Verification badge application + admin review

## Phase 10 — Admin panel  ← NOT STARTED
## Phase 11 — Messaging (WhatsApp Cloud API)  ← NOT STARTED
## Phase 12 — Directory & SEO  ← NOT STARTED
## Phase 13 — Custom domains  ← NOT STARTED
## Phase 14 — Sanctum API  ← NOT STARTED (intentionally last)

## Phase 15 — Hardening & launch  ← NOT STARTED
- [ ] Rate limiting, CSP/security headers, upload validation review
- [ ] Horizon supervisors, Sentry, uptime, backups
- [ ] N+1 audit, page cache hit-rate
- [ ] Privacy/terms, NDPR consent, data export/delete
- [ ] Demo seed pages, private beta rollout

## Current state in one line
Phases 0–4 are partially built (auth, onboarding, subdomain storefront with themed pages,
section-based page editor). Catalog is usable; Payments and the Orders domain are built with
test coverage. Booking, Customers, Billing, Escrow, Admin, Messaging and Directory are not
started. The storefront's checkout page has no buyer-facing form yet, so a buyer cannot
actually pay on-platform; the POST endpoint exists and is tested. Five tests in
tests/Feature/Tenancy fail for reasons unrelated to orders or payments.
