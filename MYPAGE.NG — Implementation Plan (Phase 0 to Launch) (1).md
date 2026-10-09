# MYPAGE.NG — Implementation Plan (Phase 0 to Launch)

Laravel 13 · Blade-first · Two themes: **Store** (products) and **Service** (businesses)

> **Revision — read first:** Part 9 replaces the section-toggle, section-reorder and font-pair customisation described in Parts 1–8. Each theme is a **fixed, complete multi-page website**; the only thing a seller customises in the design is **brand colour** (plus their own content: text, images, products or services). Where Part 9 disagrees with an earlier part, **Part 9 wins**.

## 0. Decisions that shape everything

1. **Two themes only.** `store` for product sellers, `service` for businesses that take bookings. A store page has exactly one theme, chosen at onboarding. No theme marketplace, no per-section drag-and-drop in v1. Customisation = brand colour, logo, cover image, font pair, section toggles and ordering.
2. **Blade + Alpine first.** Every page (public, dashboard, admin) is server-rendered Blade. Alpine.js handles small interactions (cart drawer, modals, tabs, date pickers). Livewire is optional and limited to two or three genuinely dynamic screens (order inbox, booking calendar). If you want zero Livewire, those two screens use Alpine + fetch against small JSON endpoints.
3. **Themes are Blade component packs, not separate apps.** One data model, two view folders.
4. **Keep the domain structure from your plan** (`app/Domains/...`) and add one new domain: **Booking** (services, availability, appointments). Everything else in your plan stays.
5. **Money is integer kobo. Webhooks are idempotent. Gateways sit behind an interface.** These are non-negotiable from day one.

### What each theme must do

|  | Store theme | Service theme |
| --- | --- | --- |
| Core item | Product (variants, stock, images) | Service (duration, price, optional deposit) |
| Primary action | Add to cart → checkout, or order via WhatsApp | Book a slot → pay deposit/full, or request via WhatsApp |
| Page sections | Hero, featured products, categories, product grid, reviews, about, contact | Hero, services list, staff/team, availability/booking widget, gallery, reviews, about, contact |
| Dashboard inbox | Orders | Bookings |
| Inventory | Stock counts | Availability + working hours |
| Payment | Full payment, optional escrow | Deposit or full payment |

---

## Phase 0 — Project setup (Days 1–3)

**Goal:** an empty app that boots, lints, tests and deploys to staging.

1. Create the app: `composer create-project laravel/laravel mypage-ng`, PHP 8.4, MySQL 8, Redis.
2. Install packages:
   - `laravel/sanctum` (later, for API)
   - `laravel/horizon`, `predis` or phpredis
   - `pestphp/pest` + `pest-plugin-laravel`
   - `larastan/larastan`, `laravel/pint`
   - `spatie/laravel-medialibrary` (images) or plain Storage + Intervention Image
   - `spatie/laravel-activitylog` (audit)
3. Frontend: Vite, Tailwind CSS 4 (CSS-first config in `resources/css/app.css`), Alpine.js.
4. Create the domain folders from your plan and register them:
   - Autoload is already covered by `App\Domains\...` under PSR-4.
   - Create `app/Providers/DomainServiceProvider.php`; in it load each domain's routes, views (namespaced, e.g. `view('seller::dashboard')`) and migrations. Register it in `bootstrap/providers.php`.
5. Config files: `config/mypage.php` (fees, escrow days, feature flags), `config/themes.php` (theme registry, see Phase 3), `config/billing.php`.
6. Tooling: Pint, Larastan level 6+, Pest, GitHub Actions (lint → test → deploy staging).
7. Local env: Herd/Sail, with wildcard local domain `*.mypage.test` so subdomain routing can be tested from day one.
8. Staging on Forge with Cloudflare wildcard DNS `*.mypage-stg.ng`.

**Exit:** `pest` and `pint --test` green in CI; staging URL loads a placeholder landing page.

---

## Phase 1 — Auth and seller onboarding (Week 1)

**Goal:** a seller can register, verify, and pick a theme.

1. **Migrations:** `sellers`, `store_pages` (see schema below), `password_reset_tokens`, `sessions`.
2. **Auth:** Blade-based auth (Laravel's starter kit or hand-rolled controllers from your `Auth` domain): register, login, logout, email verification, password reset. Nigerian phone number is collected and normalised via `Shared/Support/PhoneNumber` and the `NigerianPhone` rule. Consider phone OTP via SMS/WhatsApp later; email first.
3. **Onboarding wizard (4 Blade steps, one controller, session-held state):**
   1. Business name + username (becomes `username.mypage.ng`). Validate with `SafeSlug` and a reserved-words list (`www`, `admin`, `api`, `app`, `mail`, `support`...).
   2. **Choose theme: Store or Service.** Two large cards with a preview image each.
   3. Brand: logo, colour, WhatsApp number, city/state, short bio.
   4. Review → creates `StorePage` in `draft` status.
4. **Middleware:** `EnsureSellerIsActive`, `EnsureOnboardingComplete`.
5. **Events:** `SellerRegistered` → welcome email job.

### Core schema (Phase 1)

```
sellers:      id, name, email, phone, password, email_verified_at, status, timestamps
store_pages:  id, seller_id, slug (unique), name, theme ENUM('store','service'),
              status ENUM('draft','published','suspended'), tagline, bio,
              logo_path, cover_path, whatsapp_number, address, city, state,
              brand_color, font_pair, settings JSON, published_at, timestamps
store_sections: id, store_page_id, key, position, is_enabled, content JSON
```

`settings` JSON holds theme options (checkout mode, booking rules, social links). `store_sections` powers enable/disable/reorder of fixed section keys; it is not a free-form page builder.

**Exit:** new seller can register and land on a dashboard with a draft page of the chosen theme.

---

## Phase 2 — Multi-tenancy and routing (Week 2)

**Goal:** `username.mypage.ng` resolves to the right store, and no query can leak across sellers.

1. **Routes:** `routes/storefront.php` loaded under a domain group `{slug}.mypage.ng` (use `config('app.domain')`). Dashboard/marketing routes stay on the apex domain.
2. **`ResolveStorePage` middleware:** reads host → `SlugResolver` → loads `StorePage` (cached in Redis, invalidated on publish/update) → binds it to the container (`app()->instance(StorePage::class, ...)`) and shares it with views. 404 for unknown or `draft`/`suspended` pages (owners can preview drafts via a signed preview URL).
3. **Tenancy scope:** `BelongsToSeller` trait with a global scope using the authenticated seller (dashboard) or the resolved store page (public). Apply to Product, Service, Order, Booking, Customer. Write a Pest test that fails if any model with `seller_id` lacks the trait.
4. **Policies** for every seller-owned model.
5. **Session/cookie:** `SESSION_DOMAIN=.mypage.ng` for dashboard only; public storefront needs no login for buyers.
6. Custom domains are deferred to Phase 13; keep the resolver interface ready for it (`DomainBinding` lookup by host).

**Exit:** two seeded sellers on two subdomains; tests prove neither can read the other's data.

---

## Phase 3 — Theme engine (Week 2–3)

**Goal:** one render pipeline that serves either theme.

### 3.1 Registry

`config/themes.php`:

```php
return [
    'store' => [
        'label' => 'Store',
        'view_namespace' => 'themes.store',
        'sections' => ['hero','featured','categories','products','reviews','about','contact'],
        'required' => ['products'],
    ],
    'service' => [
        'label' => 'Service',
        'view_namespace' => 'themes.service',
        'sections' => ['hero','services','team','booking','gallery','reviews','about','contact'],
        'required' => ['services','booking'],
    ],
];
```

### 3.2 View layout

```
resources/views/themes/
├── _shared/                 # used by both themes
│   ├── layout.blade.php     # <head>, OG tags, brand CSS vars, footer
│   ├── whatsapp-button.blade.php
│   ├── reviews.blade.php
│   ├── about.blade.php
│   └── contact.blade.php
├── store/
│   ├── page.blade.php       # loops enabled sections in order
│   ├── sections/ hero, featured, categories, products
│   ├── product-show.blade.php
│   ├── cart.blade.php
│   └── checkout.blade.php
└── service/
    ├── page.blade.php
    ├── sections/ hero, services, team, booking, gallery
    ├── service-show.blade.php
    └── booking-checkout.blade.php
```

### 3.3 Rendering

- `StorefrontController@show` picks `view("themes.{$page->theme}.page")`.
- `page.blade.php` iterates `$page->sections` (enabled, ordered) and does `<x-dynamic-component :component="'themes.'.$theme.'.sections.'.$section->key" :page="$page" :section="$section" />`.
- **Brand customisation without rebuilding CSS:** output CSS variables in `<head>` (`--brand`, `--brand-contrast`, `--font-heading`, `--font-body`), and write theme components using Tailwind classes that reference them (`bg-[var(--brand)]`, or define `--color-brand: var(--brand)` in the `@theme` block). Font pairs are a fixed list of 4–5, loaded self-hosted.
- **Performance:** cache rendered page HTML per store page (Redis, tag `store:{id}`), purge on any content change. Target under 100 KB HTML+CSS and lazy-loaded images; Nigerian mobile networks are the constraint.
- **SEO:** per-page title, meta description, canonical, OG image, and JSON-LD (`Store`/`Product` for the store theme; `LocalBusiness`/`Service` for the service theme).

**Exit:** a seeded page renders in both themes from the same controller, with working brand colour changes.

---

## Phase 4 — Dashboard shell (Week 3)

**Goal:** the seller control panel, in Blade.

1. `layouts/app.blade.php`: sidebar + topbar, responsive (sellers will mostly use phones). Sidebar items change by theme:
   - Store: Dashboard, Products, Categories, Orders, Customers, Payments, Page, Settings.
   - Service: Dashboard, Services, Bookings, Availability, Customers, Payments, Page, Settings.
2. Reusable Blade components: `x-ui.button`, `x-ui.input`, `x-ui.select`, `x-ui.modal` (Alpine), `x-ui.table`, `x-ui.badge`, `x-ui.empty-state`, `x-ui.toast`, `x-ui.image-upload`.
3. **Page editor (`store-page/`)**: plain forms in tabs: Branding, Sections (enable + reorder with Alpine sortable), SEO, Contact, Publish. A live-preview iframe loads the signed draft URL and refreshes after each save. No real-time builder.
4. **Publish flow:** `PublishStorePage` action checks the theme's `required` sections have content (≥1 product or service), then sets `published`, fires `StorePagePublished`, busts caches.
5. Dashboard home: simple stat cards (views, orders/bookings this week, revenue), a getting-started checklist.

**Exit:** seller can edit branding, toggle sections, preview and publish.

---

## Phase 5 — Catalog for the Store theme (Week 4–5)

**Goal:** product management and the public store experience.

### Schema

```
categories:        id, seller_id, store_page_id, name, slug, position
products:          id, seller_id, store_page_id, category_id, name, slug, description,
                   price_kobo, compare_at_kobo, sku, track_stock, stock_qty,
                   status ENUM('draft','active','archived'), is_featured, timestamps
product_images:    id, product_id, path, position, alt
product_variants:  id, product_id, name, options JSON, price_kobo, stock_qty, sku
inventory_logs:    id, product_id, variant_id, delta, reason, ref_type, ref_id, created_at
```

### Build order

1. Models, factories, policies, `Money` helper (`₦ 12,500` ↔ `1250000` kobo).
2. Dashboard CRUD (`ProductController` + form requests). Multi-image upload with client-side resize before upload (Alpine + canvas), server-side conversion to WebP in a queued job, thumbnails generated.
3. Variants (size/colour) as a simple repeater; keep v1 to one option group if time is tight.
4. `AdjustStock` action writes an `inventory_logs` row every time.
5. CSV bulk import (`BulkImportProducts`) as a queued job with an error report.
6. **Public Store theme:** product grid with category filter and search (query-string, server-rendered, paginated), product detail page with gallery and variant picker (Alpine), **cart in `localStorage` + Alpine**, synced to the server only at checkout.
7. **WhatsApp order button:** `GenerateWaMeLink` builds a prefilled message with item names, quantities, total and an order reference code. This delivers the P1 exit criterion before payments exist.

**Exit:** a buyer can browse a published store and send an order over WhatsApp.

---

## Phase 6 — Booking domain for the Service theme (Week 5–7)

**Goal:** services, availability, and bookable slots. This is the biggest addition to your original plan, so it has its own domain: `app/Domains/Booking/`.

### Schema

```
services:          id, seller_id, store_page_id, name, slug, description, image_path,
                   duration_min, price_kobo, deposit_type ENUM('none','fixed','percent'),
                   deposit_value, buffer_min, is_active, position, timestamps
staff_members:     id, seller_id, store_page_id, name, photo_path, bio, is_active
service_staff:     service_id, staff_member_id            -- pivot
working_hours:     id, store_page_id, staff_member_id NULL, weekday, start_time, end_time
time_off:          id, store_page_id, staff_member_id NULL, starts_at, ends_at, reason
bookings:          id, seller_id, store_page_id, service_id, staff_member_id NULL,
                   customer_id, reference, starts_at, ends_at, status
                   ENUM('pending_payment','confirmed','completed','cancelled','no_show'),
                   price_kobo, deposit_kobo, notes, source, timestamps
```

Store every timestamp in UTC and display in `Africa/Lagos`. Keep each store page's timezone in `settings` for future-proofing.

### Services

- `AvailabilityService::slotsFor(Service, Carbon $date, ?Staff)`: takes working hours, subtracts time off and existing confirmed or unexpired `pending_payment` bookings (plus buffers), then returns slots in `slot_step` increments. Unit-test heavily, including day boundaries and overlapping bookings.
- `CreateBooking` action: runs in a DB transaction with a row lock (`lockForUpdate` on the staff or store schedule) so two buyers cannot take the same slot. Pending bookings hold the slot for 15 minutes; a scheduled job `ExpirePendingBookings` frees it.
- `UpdateBookingStatus` writes a status-history row (mirror the order status history).
- Actions: `CancelBooking`, `RescheduleBooking` (respect a seller-defined cancellation window).

### Dashboard

- Services CRUD, Staff CRUD, Availability editor (weekly grid of hours per weekday with Alpine, plus time-off form).
- Bookings inbox: list (today / upcoming / past), status filters, a booking detail drawer, and a simple week-calendar view. This is one of the two spots where Livewire is worth considering; otherwise a Blade table plus Alpine drawer is enough for v1.

### Public Service theme

- Services list and detail pages.
- **Booking widget:** step 1 pick service, step 2 pick staff (if more than one), step 3 pick date (Alpine date strip) and slot (fetched from `GET /availability?service=&date=` JSON endpoint), step 4 name, phone, notes, step 5 pay deposit/full or send request via WhatsApp.
- WhatsApp fallback: `GenerateWaMeLink` for a prefilled "I'd like to book X on date at time" message, which also creates a `pending` booking request that the seller confirms manually.

**Exit:** a visitor can book a real slot, double-booking is impossible, and the seller sees it in the inbox.

---

## Phase 7 — Payments (Week 7–9)

**Goal:** first real ₦ collected for both themes, using one payment flow.

1. **Contracts first:** `PaymentGatewayInterface` (`initialize`, `verify`, `refund`, `transfer`) and implementations `PaystackGateway`, `FlutterwaveGateway`. Bind in `DomainServiceProvider`; active gateway chosen by config.
2. **Polymorphic payable:** `transactions` has `payable_type/payable_id`, so one table pays for an `Order` or a `Booking`. Fields: `reference` (unique), `amount_kobo`, `fee_kobo`, `commission_kobo`, `gateway`, `status`, `gateway_payload JSON`.
3. **Actions:** `InitializePayment` (creates a pending transaction, returns hosted checkout URL), `VerifyPaymentWebhook`.
4. **Webhooks (`routes/webhooks.php`):**
   - Verify signature (HMAC) before anything else; reject on mismatch.
   - Store `webhook_events(event_id unique, gateway, payload, processed_at)`; duplicate event id = no-op.
   - Dispatch a queued job; return 200 quickly.
   - On success: mark transaction paid, fire `PaymentConfirmed`. Listeners move the order to `paid` or the booking to `confirmed`, decrement stock, upsert the customer.
5. **Callback page** after the gateway redirect always re-verifies server-side; never trust the query string.
6. **Wallet + ledger:** `wallets`, `wallet_ledger` with double-entry rows written in one transaction; `CommissionCalculator` (basis points from `PLATFORM_COMMISSION_BPS`). Ledger rows are immutable (no update/delete; enforce with a model guard and DB permissions where possible).
7. **Payouts:** `RequestPayout` → `ProcessPayout` job → gateway transfer; seller bank-account verification via the gateway's resolve-account endpoint; minimum payout and a payout history screen.
8. **Store checkout:** `CreateOrderFromCheckout` re-prices every line from the database (never trust cart prices), reserves stock, creates the order and payment.
9. **Service checkout:** `CreateBooking` + deposit/full payment via the same `InitializePayment`.

**Tests (Pest, mandatory):** init, verify, duplicate webhook, bad signature, underpayment, commission math, ledger balances to zero, payout success/failure.

**Exit:** a real ₦ payment goes end-to-end on sandbox, then live with a small amount, in both themes.

---

## Phase 8 — Order inbox, bookings pipeline and customer book (Week 9–11)

1. **Orders** (Store theme): `orders`, `order_items`, `order_status_histories`; `OrderStatus` enum (`pending → paid → processing → shipped → delivered → cancelled/refunded`); `UpdateOrderStatus` is the only way to change status and always writes history and fires events.
2. **Unified inbox concept:** the dashboard "Inbox" is Orders for store sellers and Bookings for service sellers, both using the same Blade table component, status badges and detail drawer.
3. **WhatsApp-origin orders/bookings:** the reference code in the prefilled message lets the seller match an incoming chat to a pending record; a simple "Mark as received" action is enough before the Cloud API exists.
4. **Customers domain:** `UpsertCustomerFromOrder` / `...FromBooking`, unique on `seller_id + normalised phone`; lifetime value, order/booking count, last activity, notes, opt-in flag for broadcasts.
5. **Abandoned carts (Store only):** email/phone captured at checkout start → `RecordAbandonedCart` → `RemindAbandonedCart` job.
6. **Notifications:** email to seller on new order/booking; email or WhatsApp link to buyer on status change; `SendOrderUpdateMessage` stays a stub until Phase 11.

**Exit:** the ₦3,500/month product exists: pages, payments, inbox and customer book.

---

## Phase 9 — Billing, plans and escrow (Week 11–13)

1. **Plans and subscriptions:** `plans`, `subscriptions`; `SubscriptionManager` using Paystack plans or manual bank transfer approval. `EnsureSubscriptionValid` middleware gates dashboard writes (never the public storefront; do not take a paying seller's store offline silently, add a grace period). `ExpireSubscriptions` scheduled daily.
2. **Feature gating:** a single `Feature::enabled('escrow', $seller)` helper reading `config/mypage.php` plus DB flags, so each feature ships dark.
3. **Escrow (Store theme first, optional for Service):** funds credited to an escrow ledger account on payment; `ReleaseEscrow` on delivery confirmation (buyer link) or auto after `ESCROW_AUTO_RELEASE_DAYS` via `ReleaseAutoEscrow`; dispute flow freezes release and routes to the admin `DisputeResolver`. For bookings, release on `completed` status.
4. **Verified badge:** `BadgeApplication` (document upload: CAC/ID), admin review queue, `VerificationBadge` displayed in both themes.

**Exit:** subscriptions billing, escrow holds and releases, badges approved by admin.

---

## Phase 10 — Admin panel (Week 12–14, parallel with Phase 9)

Blade-based internal panel on `admin.mypage.ng` or `/admin`, separate guard and layout.

- Seller moderation (suspend/unsuspend a store page, with reason), transaction browser, dispute resolver, badge review, payout approvals (manual approval toggle for early days), feature-flag toggles, platform metrics (GMV, commission, active stores by theme).
- Every admin action writes to the activity log.

---

## Phase 11 — Messaging (Week 14–16)

1. `WhatsAppConnection` (seller connects a Business number via Embedded Signup or your platform number), template registry, `TemplateClassifier` (utility vs marketing).
2. `WhatsAppCloudApiClient`, inbound `WebhookController` (verify token + signature, idempotent).
3. Transactional messages first: order updates, booking confirmations and reminders (24h and 2h before, scheduled job).
4. `BroadcastComposer` with live ₦ cost from `MessageCostCalculator`; sends only to opted-in customers, `OptOutManager` honoured before every chunk; `SendBroadcastChunk` throttled.
5. Keep `wa.me` links as the free fallback everywhere.

---

## Phase 12 — Directory and SEO (Week 17–19)

1. `DirectoryListing` built from published pages; filters by theme (Products / Services), category, state and city; search via MySQL FULLTEXT first (Meilisearch/Scout later if needed).
2. `IndexStoreForSearch` fires on `StorePagePublished`; `ReindexDirectory` nightly.
3. Sitemaps (`GenerateSitemapCommand`, split per 10k URLs), `robots.txt`, canonical URLs, OG images.
4. Directory pages are server-rendered and cached; add pagination and clean URLs (`/directory/lagos/hair-salons`).

---

## Phase 13 — Custom domains (Week 19–20)

`DomainBinding`: seller adds a domain, you show a CNAME target, a verification job checks DNS, then certificate issuance (Cloudflare for SaaS custom hostnames, or Caddy on-demand TLS). `SlugResolver` falls back to host lookup. Gate behind a paid plan.

---

## Phase 14 — Optional Sanctum API

Only after the web product is stable: token auth for a future mobile app, versioned `/api/v1`, resource classes, rate limits. Do not build this earlier.

---

## Phase 15 — Hardening and launch (Week 20–22)

- **Security:** rate limiting on auth, checkout and availability endpoints; CSP and `SecurityHeaders` middleware; upload validation (mime, size, re-encode images); mass-assignment review; HTML-escape all seller content (descriptions, bios) and use a sanitizer if rich text is allowed.
- **Reliability:** Horizon supervisors, failed-job alerts, DB backups with a tested restore, Sentry, uptime monitoring on a sample storefront.
- **Performance:** page cache hit-rate check, N+1 audit (`Model::preventLazyLoading()` in non-production), image CDN.
- **Compliance:** privacy policy and terms pages, NDPR consent on checkout and booking forms, data export/delete for customers.
- **Content:** seed two polished demo pages (one per theme), onboarding emails, help docs.
- **Rollout:** private beta with 10–20 sellers per theme → fix → public launch behind feature flags.

---

## Timeline at a glance

| Phase | Scope | Weeks |
| --- | --- | --- |
| 0 | Setup, CI, staging | 0.5 |
| 1 | Auth, onboarding, theme choice | 1 |
| 2 | Tenancy and subdomain routing | 1 |
| 3 | Theme engine and brand variables | 1 |
| 4 | Dashboard shell and page editor | 1 |
| 5 | Catalog and Store theme public pages | 2 |
| 6 | Booking domain and Service theme | 2–3 |
| 7 | Payments, ledger, payouts | 2–3 |
| 8 | Inbox, customers, notifications | 2 |
| 9 | Billing, escrow, badges | 2 |
| 10 | Admin panel | 2 (parallel) |
| 11 | WhatsApp messaging | 3 |
| 12 | Directory and SEO | 2–3 |
| 13 | Custom domains | 1 |
| 15 | Hardening and launch | 2 |

Phases 5 and 6 can run in parallel with two developers since they share only the theme engine and the payment contract. For a solo build, do Store first (it is simpler and gets you revenue sooner), then Service.

## Build-order rule of thumb

Ship a **thin vertical slice** per theme before widening: Store = page → one product → WhatsApp order → paid checkout. Service = page → one service → one slot → paid deposit. Only then add variants, staff, escrow, broadcasts and the directory.

## Suggested minimum test suite per phase

- Tenancy leak tests (Phase 2).
- Theme render smoke tests for both themes, with all sections on and off (Phase 3).
- Availability engine unit tests and a concurrent double-booking test (Phase 6).
- Payment, webhook idempotency, ledger balance and commission tests (Phase 7).
- Status-transition tests for orders and bookings (Phase 8).
- Escrow release/dispute tests (Phase 9).

---

# Part 2 — Detailed Build Steps (commands, files, code skeletons)

This part walks each phase as concrete steps. Code is a **skeleton to adapt**, not a finished module. Check package versions and any Laravel 13 specifics against the official docs as you go.

## P0 — Setup, step by step

```bash
composer create-project laravel/laravel mypage-ng
cd mypage-ng

composer require laravel/horizon predis/predis spatie/laravel-activitylog intervention/image
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint

php artisan pest:install
php artisan horizon:install
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"

npm install
npm install alpinejs
npm install -D tailwindcss @tailwindcss/vite
```

**`vite.config.js`**

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
    tailwindcss(),
  ],
});
```

**`resources/css/app.css`**

```css
@import "tailwindcss";

@theme {
  --color-brand: var(--brand, #0f766e);
  --color-brand-contrast: var(--brand-contrast, #ffffff);
  --font-heading: var(--font-heading-stack, ui-sans-serif, system-ui);
  --font-body: var(--font-body-stack, ui-sans-serif, system-ui);
}
```

**`resources/js/app.js`**

```js
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();
```

**Create the domain folders**

```bash
for d in Auth Seller Catalog Booking Orders Payments Customers Messaging Billing Directory Admin; do
  mkdir -p app/Domains/$d/{Models,Actions,Http/Controllers,Http/Requests,Services,Jobs,Events,Policies,Enums,resources/views}
done
mkdir -p app/Shared/{Support,Rules,Middleware,Traits}
```

**`app/Providers/DomainServiceProvider.php`**

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    private array $domains = [
        'Auth','Seller','Catalog','Booking','Orders','Payments',
        'Customers','Messaging','Billing','Directory','Admin',
    ];

    public function register(): void
    {
        $this->app->bind(
            \App\Domains\Payments\Services\Contracts\PaymentGatewayInterface::class,
            fn () => match (config('mypage.gateway')) {
                'flutterwave' => new \App\Domains\Payments\Services\FlutterwaveGateway(),
                default       => new \App\Domains\Payments\Services\PaystackGateway(),
            }
        );
    }

    public function boot(): void
    {
        foreach ($this->domains as $d) {
            $base = app_path("Domains/{$d}");
            $this->loadViewsFrom("{$base}/resources/views", strtolower($d));
            if (is_dir("{$base}/database/migrations")) {
                $this->loadMigrationsFrom("{$base}/database/migrations");
            }
        }
    }
}
```

Add it to `bootstrap/providers.php`. Views are then called as `view('seller::dashboard')`. (If you prefer all migrations in `database/migrations`, drop the migrations line.)

**`config/mypage.php`**

```php
return [
    'domain' => env('APP_DOMAIN', 'mypage.test'),
    'gateway' => env('PAYMENT_GATEWAY', 'paystack'),
    'commission_bps' => (int) env('PLATFORM_COMMISSION_BPS', 150),
    'escrow_auto_release_days' => (int) env('ESCROW_AUTO_RELEASE_DAYS', 7),
    'features' => [
        'escrow' => env('FEATURE_ESCROW', false),
        'broadcasts' => env('FEATURE_BROADCASTS', false),
        'directory' => env('FEATURE_DIRECTORY', false),
        'custom_domains' => env('FEATURE_CUSTOM_DOMAINS', false),
    ],
    'reserved_slugs' => ['www','admin','api','app','mail','support','help','blog','static','cdn'],
];
```

**CI: `.github/workflows/ci.yml`** — steps: checkout, setup PHP 8.4, `composer install`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `php artisan test` (MySQL + Redis services).

---

## P1 — Auth and onboarding, step by step

```bash
php artisan make:model Domains/Seller/Models/Seller -m
php artisan make:model Domains/Seller/Models/StorePage -mf
php artisan make:model Domains/Seller/Models/StoreSection -m
```

Move the generated migrations if you keep per-domain folders.

**`app/Domains/Seller/Enums/Theme.php`**

```php
enum Theme: string
{
    case Store = 'store';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) { self::Store => 'Store', self::Service => 'Service' };
    }

    public function sections(): array
    {
        return config("themes.{$this->value}.sections");
    }
}
```

**`store_pages` migration**

```php
Schema::create('store_pages', function (Blueprint $t) {
    $t->id();
    $t->foreignId('seller_id')->constrained()->cascadeOnDelete();
    $t->string('slug')->unique();
    $t->string('name');
    $t->string('theme', 16);                       // store | service
    $t->string('status', 16)->default('draft');  // draft | published | suspended
    $t->string('tagline')->nullable();
    $t->text('bio')->nullable();
    $t->string('logo_path')->nullable();
    $t->string('cover_path')->nullable();
    $t->string('whatsapp_number', 20)->nullable();
    $t->string('city')->nullable();
    $t->string('state')->nullable();
    $t->string('brand_color', 7)->default('#0f766e');
    $t->string('font_pair', 32)->default('modern');
    $t->json('settings')->nullable();
    $t->timestamp('published_at')->nullable();
    $t->timestamps();
    $t->index(['status', 'theme']);
});
```

**`StorePage` model essentials**

```php
protected function casts(): array
{
    return ['theme' => Theme::class, 'settings' => 'array', 'published_at' => 'datetime'];
}

public function sections() { return $this->hasMany(StoreSection::class)->orderBy('position'); }
public function seller()   { return $this->belongsTo(Seller::class); }
```

**Registration action**

```php
class RegisterSeller
{
    public function handle(array $data): Seller
    {
        return DB::transaction(function () use ($data) {
            $seller = Seller::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => PhoneNumber::normalize($data['phone']),
                'password' => Hash::make($data['password']),
            ]);
            event(new SellerRegistered($seller));
            return $seller;
        });
    }
}
```

**Create-store action (called at the end of the wizard)**

```php
class CreateStorePage
{
    public function handle(Seller $seller, array $d): StorePage
    {
        return DB::transaction(function () use ($seller, $d) {
            $theme = Theme::from($d['theme']);
            $page = $seller->storePages()->create([
                'slug' => $d['slug'], 'name' => $d['name'], 'theme' => $theme,
                'brand_color' => $d['brand_color'] ?? '#0f766e',
                'whatsapp_number' => PhoneNumber::normalize($d['whatsapp']),
                'city' => $d['city'], 'state' => $d['state'], 'bio' => $d['bio'] ?? null,
            ]);

            foreach ($theme->sections() as $i => $key) {
                $page->sections()->create(['key' => $key, 'position' => $i, 'is_enabled' => true]);
            }
            return $page;
        });
    }
}
```

**Routes (`routes/web.php`, apex domain)**

```php
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware(['auth', 'verified'])->prefix('onboarding')->group(function () {
    Route::get('/{step}', [OnboardingController::class, 'show'])->whereIn('step', ['business','theme','brand','review']);
    Route::post('/{step}', [OnboardingController::class, 'store']);
});
```

**Slug validation rule**

```php
class SafeSlug implements ValidationRule
{
    public function validate(string $attr, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $value)) {
            $fail('Use 3–30 letters, numbers or hyphens.'); return;
        }
        if (in_array($value, config('mypage.reserved_slugs'), true)) {
            $fail('That name is reserved.');
        }
    }
}
```

Also add `Rule::unique('store_pages', 'slug')`.

**Tests:** register → verify email → wizard → `StorePage` exists with the right theme and sections count.

---

## P2 — Tenancy and routing, step by step

**`bootstrap/app.php`** — register the storefront group **before** the apex routes. Routes without a domain match any host, so order matters.

```php
->withRouting(
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware(['web', 'resolve.store'])
            ->domain('{slug}.'.config('mypage.domain'))
            ->group(base_path('routes/storefront.php'));
        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::middleware('web')->group(base_path('routes/checkout.php'));
        Route::prefix('webhooks')->group(base_path('routes/webhooks.php'));
    },
)
->withMiddleware(function (Middleware $m) {
    $m->alias(['resolve.store' => \App\Shared\Middleware\ResolveStorePage::class]);
})
```

(If `routes/web.php` is also loaded by the default `web:` option, remove it from there so it is not registered twice.)

**`ResolveStorePage` middleware**

```php
public function handle(Request $request, Closure $next)
{
    $slug = $request->route('slug');
    $request->route()->forgetParameter('slug');

    $page = Cache::remember("store:slug:{$slug}", 300,
        fn () => StorePage::with('sections')->where('slug', $slug)->first());

    abort_if(! $page, 404);

    $isOwnerPreview = $request->hasValidSignature();
    abort_unless($page->status === 'published' || $isOwnerPreview, 404);

    app()->instance(StorePage::class, $page);
    View::share('page', $page);

    return $next($request);
}
```

Invalidate `store:slug:{slug}` in an observer on `StorePage` saved/deleted.

**`BelongsToSeller` trait**

```php
trait BelongsToSeller
{
    protected static function bootBelongsToSeller(): void
    {
        static::addGlobalScope('seller', function (Builder $q) {
            if ($id = static::currentSellerId()) {
                $q->where($q->getModel()->getTable().'.seller_id', $id);
            }
        });

        static::creating(function ($m) {
            $m->seller_id ??= static::currentSellerId();
        });
    }

    protected static function currentSellerId(): ?int
    {
        if (app()->bound(StorePage::class)) return app(StorePage::class)->seller_id;
        return auth()->user()?->id;
    }
}
```

If no tenant is resolved (queue workers, console), the scope is skipped; in those contexts always query with explicit `withoutGlobalScopes()->where('seller_id', ...)` so intent is visible.

**Leak test (Pest)**

```php
it('never returns another seller\'s products', function () {
    $a = Seller::factory()->hasStorePages(1)->create();
    $b = Seller::factory()->hasStorePages(1)->create();
    Product::factory()->for($b)->create();

    $this->actingAs($a);
    expect(Product::count())->toBe(0);
});
```

Add an architecture test that scans models containing a `seller_id` column and asserts they use the trait.

Local testing: Herd or `dnsmasq` for `*.mypage.test`; set `APP_DOMAIN=mypage.test` and `SESSION_DOMAIN=.mypage.test`.

---

## P3 — Theme engine, step by step

**`config/themes.php`** (as in Part 1), plus per-theme font pairs:

```php
'font_pairs' => [
    'modern'   => ['heading' => "'Inter', sans-serif",        'body' => "'Inter', sans-serif"],
    'elegant'  => ['heading' => "'Playfair Display', serif", 'body' => "'Lato', sans-serif"],
    'bold'     => ['heading' => "'Poppins', sans-serif",     'body' => "'Open Sans', sans-serif"],
],
```

Self-host the font files in `public/fonts` and declare `@font-face` in `app.css`.

**Storefront controller**

```php
class StorefrontController extends Controller
{
    public function show(StorePage $page)           // resolved from container by middleware
    {
        $data = match ($page->theme) {
            Theme::Store   => ['products' => Product::active()->latest()->limit(24)->get(),
                               'categories' => Category::orderBy('position')->get()],
            Theme::Service => ['services' => Service::active()->orderBy('position')->get(),
                               'staff' => StaffMember::active()->get()],
        };

        return view("themes.{$page->theme->value}.page", $data + ['page' => $page]);
    }
}
```

Because `StorePage` is bound as an instance, type-hinting it injects the resolved page.

**`themes/_shared/layout.blade.php`**

```blade
@php $fonts = config('themes.font_pairs.'.$page->font_pair); @endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $title ?? $page->name }}</title>
  <meta name="description" content="{{ $description ?? $page->tagline }}">
  <link rel="canonical" href="{{ url()->current() }}">
  <meta property="og:title" content="{{ $page->name }}">
  <meta property="og:image" content="{{ $page->cover_path ? Storage::url($page->cover_path) : '' }}">
  <style>
    :root{
      --brand: {{ $page->brand_color }};
      --brand-contrast: {{ \App\Shared\Support\Color::contrast($page->brand_color) }};
      --font-heading-stack: {!! $fonts['heading'] !!};
      --font-body-stack: {!! $fonts['body'] !!};
    }
  </style>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  {{ $head ?? '' }}
</head>
<body class="font-[var(--font-body)] bg-white text-slate-900">
  {{ $slot }}
  <x-themes.shared.whatsapp-button :page="$page" />
</body>
</html>
```

Validate `brand_color` against `/^#[0-9a-f]{6}$/i` on save so nothing unsafe is printed into the `<style>` block.

**`themes/store/page.blade.php`** (the Service one is identical but under `service/`)

```blade
<x-themes.shared.layout :page="$page">
  @foreach ($page->sections->where('is_enabled', true) as $section)
    <x-dynamic-component
      :component="'themes.'.$page->theme->value.'.sections.'.$section->key"
      :page="$page" :section="$section" :products="$products ?? null" />
  @endforeach
</x-themes.shared.layout>
```

Put theme components in `resources/views/components/themes/{store,service,shared}/...` so `x-dynamic-component` resolves them without extra registration. Missing section view = skip silently, never 500.

**Page cache:** wrap `show` in `Cache::tags("store:{$page->id}")->remember(...)` (Redis supports tags). Flush the tag from observers on Product, Service, StoreSection and StorePage.

**Tests:** `get('http://demo.mypage.test')->assertOk()->assertSee($page->name)` for both themes, with every combination of sections enabled/disabled.

---

## P4 — Dashboard shell, step by step

1. `layouts/app.blade.php`: `<aside>` with nav from a single `config/dashboard_nav.php` keyed by theme, so the sidebar differs without duplicate templates.
2. Build the UI kit under `resources/views/components/ui/`: `button`, `input`, `select`, `textarea`, `modal`, `drawer`, `table`, `badge`, `toast`, `image-upload`, `empty-state`.
3. **Alpine modal skeleton**

```blade
<div x-data="{ open: false }" @open-modal.window="open = true">
  <div x-show="open" x-transition class="fixed inset-0 bg-black/50 flex items-center justify-center" @keydown.escape.window="open=false">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg" @click.outside="open=false">{{ $slot }}</div>
  </div>
</div>
```

4. **Page editor** controller `StorePageController@edit/update` with a tabbed form (Alpine `x-data="{tab:'branding'}"`). Sections tab: list with checkboxes and up/down buttons posting an ordered `sections[]` array, which `UpdateStorePage` writes back to `position`/`is_enabled`.
5. **Preview:** `URL::temporarySignedRoute('storefront.preview', now()->addHour(), ['slug' => $page->slug])` loaded in an `<iframe>`; reload it after save.
6. **Publish:** `PublishStorePage` verifies the theme's `required` sections have content, sets status, flushes cache, fires `StorePagePublished`.
7. Toast flash messages through `session('status')`.

---

## P5 — Catalog and Store theme, step by step

```bash
php artisan make:model Domains/Catalog/Models/Product -mfp
php artisan make:model Domains/Catalog/Models/Category -mf
php artisan make:model Domains/Catalog/Models/ProductImage -m
php artisan make:model Domains/Catalog/Models/ProductVariant -m
php artisan make:model Domains/Catalog/Models/InventoryLog -m
```

**`Money` helper**

```php
final class Money
{
    public static function toKobo(string|float $naira): int
    {
        return (int) round(((float) str_replace(',', '', (string) $naira)) * 100);
    }
    public static function format(int $kobo): string
    {
        return '₦'.number_format($kobo / 100, $kobo % 100 ? 2 : 0);
    }
}
```

Add a Blade directive or helper `money($kobo)`. Cast `price_kobo` as `integer`; use accessors for naira input on forms.

**`CreateProduct` action**

```php
class CreateProduct
{
    public function handle(StorePage $page, array $d, array $images = []): Product
    {
        return DB::transaction(function () use ($page, $d, $images) {
            $p = Product::create([
                'store_page_id' => $page->id,
                'name' => $d['name'], 'slug' => Str::slug($d['name']).'-'.Str::lower(Str::random(4)),
                'description' => $d['description'] ?? null,
                'price_kobo' => Money::toKobo($d['price']),
                'track_stock' => $d['track_stock'] ?? false,
                'stock_qty' => $d['stock_qty'] ?? 0,
                'status' => $d['status'] ?? 'draft',
            ]);

            foreach ($images as $i => $file) {
                $path = $file->store("products/{$p->id}", 'public');
                $p->images()->create(['path' => $path, 'position' => $i]);
                ProcessProductImage::dispatch($path);   // WebP + thumbnails on the queue
            }
            Cache::tags("store:{$page->id}")->flush();
            return $p;
        });
    }
}
```

**`AdjustStock`**

```php
public function handle(Product $p, int $delta, string $reason, ?Model $ref = null): void
{
    DB::transaction(function () use ($p, $delta, $reason, $ref) {
        $p = Product::lockForUpdate()->findOrFail($p->id);
        throw_if($p->track_stock && $p->stock_qty + $delta < 0, new OutOfStock($p));
        $p->increment('stock_qty', $delta);
        $p->inventoryLogs()->create(['delta' => $delta, 'reason' => $reason,
            'ref_type' => $ref?->getMorphClass(), 'ref_id' => $ref?->getKey()]);
    });
}
```

**Dashboard routes:** `Route::resource('products', ProductController::class)` inside `auth` + `verified` + `seller.active` middleware, plus `POST products/import`.

**Cart (client-side) with Alpine**

```js
Alpine.store('cart', {
  items: JSON.parse(localStorage.getItem('cart:'+location.host) || '[]'),
  add(p, qty = 1) {
    const i = this.items.find(x => x.id === p.id && x.variant === p.variant);
    i ? i.qty += qty : this.items.push({ ...p, qty });
    this.save();
  },
  remove(idx) { this.items.splice(idx, 1); this.save(); },
  get count() { return this.items.reduce((n, i) => n + i.qty, 0); },
  get total() { return this.items.reduce((n, i) => n + i.price_kobo * i.qty, 0); },
  save() { localStorage.setItem('cart:'+location.host, JSON.stringify(this.items)); },
});
```

The cart stores only IDs, variant and quantity for pricing purposes; **the server re-prices at checkout**.

**WhatsApp link action**

```php
class GenerateWaMeLink
{
    public function forOrder(StorePage $page, array $lines, int $totalKobo, string $ref): string
    {
        $text = "Hello {$page->name}, I'd like to order (ref {$ref}):\n";
        foreach ($lines as $l) $text .= "- {$l['qty']} x {$l['name']}\n";
        $text .= 'Total: '.Money::format($totalKobo);
        return 'https://wa.me/'.ltrim($page->whatsapp_number, '+').'?text='.rawurlencode($text);
    }
}
```

Create a `pending` order record **before** redirecting to wa.me so the reference exists in your inbox.

**Tests:** price re-calculation ignores tampered cart prices; stock cannot go negative; images are processed; page cache is flushed on product save.

---

## P6 — Booking domain and Service theme, step by step

```bash
php artisan make:model Domains/Booking/Models/Service -mfp
php artisan make:model Domains/Booking/Models/StaffMember -mf
php artisan make:model Domains/Booking/Models/WorkingHour -m
php artisan make:model Domains/Booking/Models/TimeOff -m
php artisan make:model Domains/Booking/Models/Booking -mf
php artisan make:migration create_service_staff_table
```

**`bookings` migration (key parts)**

```php
Schema::create('bookings', function (Blueprint $t) {
    $t->id();
    $t->foreignId('seller_id')->constrained();
    $t->foreignId('store_page_id')->constrained();
    $t->foreignId('service_id')->constrained();
    $t->foreignId('staff_member_id')->nullable()->constrained();
    $t->foreignId('customer_id')->nullable()->constrained();
    $t->string('reference', 16)->unique();
    $t->dateTime('starts_at');                 // stored in UTC
    $t->dateTime('ends_at');
    $t->string('status', 20)->default('pending_payment');
    $t->unsignedInteger('price_kobo');
    $t->unsignedInteger('deposit_kobo')->default(0);
    $t->timestamp('expires_at')->nullable();   // pending hold
    $t->text('notes')->nullable();
    $t->string('source', 16)->default('web');
    $t->timestamps();
    $t->index(['store_page_id', 'starts_at']);
    $t->index(['staff_member_id', 'starts_at']);
});
```

**`AvailabilityService`** — the heart of the Service theme.

```php
class AvailabilityService
{
    public function slotsFor(Service $service, CarbonImmutable $date, ?StaffMember $staff = null): Collection
    {
        $tz = $service->storePage->settings['timezone'] ?? 'Africa/Lagos';
        $day = $date->setTimezone($tz);

        $hours = WorkingHour::where('store_page_id', $service->store_page_id)
            ->where('weekday', $day->dayOfWeekIso)
            ->when($staff, fn ($q) => $q->where('staff_member_id', $staff->id))
            ->get();

        $busy = Booking::query()
            ->where('store_page_id', $service->store_page_id)
            ->when($staff, fn ($q) => $q->where('staff_member_id', $staff->id))
            ->whereBetween('starts_at', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])
            ->where(fn ($q) => $q->whereIn('status', ['confirmed'])
                ->orWhere(fn ($q) => $q->where('status', 'pending_payment')->where('expires_at', '>', now())))
            ->get(['starts_at', 'ends_at']);

        $slots = collect();
        $length = $service->duration_min + $service->buffer_min;

        foreach ($hours as $h) {
            $cursor = $day->setTimeFromTimeString($h->start_time);
            $end    = $day->setTimeFromTimeString($h->end_time);

            while ($cursor->addMinutes($length)->lte($end)) {
                $slotEnd = $cursor->addMinutes($service->duration_min);
                $clash = $busy->contains(fn ($b) =>
                    $cursor->utc()->lt($b->ends_at) && $slotEnd->utc()->gt($b->starts_at));

                if (! $clash && $cursor->gt(now()->addMinutes($service->settings['min_notice'] ?? 60))) {
                    $slots->push($cursor);
                }
                $cursor = $cursor->addMinutes(30);   // slot step
            }
        }
        return $slots->unique(fn ($s) => $s->toIso8601String())->values();
    }
}
```

Subtract `time_off` ranges the same way as `$busy`. Without a chosen staff member, treat availability as "at least one staff member free" and assign one at booking time.

**`CreateBooking` — prevents double-booking**

```php
class CreateBooking
{
    public function handle(Service $service, CarbonImmutable $start, ?StaffMember $staff, array $buyer): Booking
    {
        return DB::transaction(function () use ($service, $start, $staff, $buyer) {
            // Serialize competing requests for the same schedule
            StorePage::whereKey($service->store_page_id)->lockForUpdate()->first();

            $ok = app(AvailabilityService::class)->slotsFor($service, $start->startOfDay(), $staff)
                ->contains(fn ($s) => $s->equalTo($start));
            throw_unless($ok, new SlotUnavailable());

            $deposit = match ($service->deposit_type) {
                'fixed'   => $service->deposit_value,
                'percent' => intdiv($service->price_kobo * $service->deposit_value, 100),
                default   => 0,
            };

            $booking = Booking::create([
                'seller_id' => $service->seller_id,
                'store_page_id' => $service->store_page_id,
                'service_id' => $service->id,
                'staff_member_id' => $staff?->id,
                'reference' => 'BK'.strtoupper(Str::random(8)),
                'starts_at' => $start->utc(),
                'ends_at' => $start->addMinutes($service->duration_min)->utc(),
                'status' => $service->price_kobo > 0 ? 'pending_payment' : 'confirmed',
                'price_kobo' => $service->price_kobo,
                'deposit_kobo' => $deposit,
                'expires_at' => now()->addMinutes(15),
            ]);

            $booking->customer_id = app(UpsertCustomer::class)->handle($service->seller_id, $buyer)->id;
            $booking->save();
            return $booking;
        });
    }
}
```

Locking the store page row is a simple, safe v1 approach. Move to per-staff locks if contention appears.

**Scheduler (`routes/console.php`)**

```php
Schedule::command('bookings:expire-pending')->everyMinute();
Schedule::command('bookings:send-reminders')->everyFifteenMinutes();
```

**JSON endpoint:** `GET /availability?service={id}&date=2026-11-02&staff={id?}` returns `['slots' => ['09:00', '09:30', ...]]`, throttled `60,1`.

**Booking widget (Alpine)**

```blade
<div x-data="bookingWidget({ serviceId: {{ $service->id }} })">
  <input type="date" x-model="date" @change="loadSlots()" :min="today">
  <div class="grid grid-cols-3 gap-2">
    <template x-for="s in slots" :key="s">
      <button type="button" @click="slot = s"
        :class="slot === s ? 'bg-brand text-brand-contrast' : 'border'" x-text="s"
        class="rounded-lg py-2"></button>
    </template>
  </div>
</div>
```

```js
Alpine.data('bookingWidget', (cfg) => ({
  date: '', slot: null, slots: [], today: new Date().toISOString().slice(0, 10),
  async loadSlots() {
    const r = await fetch(`/availability?service=${cfg.serviceId}&date=${this.date}`);
    this.slots = (await r.json()).slots; this.slot = null;
  },
}));
```

**Tests:** overlapping bookings rejected, buffer respected, expired holds free the slot, two parallel requests for the same slot produce exactly one booking, timezone edge (booking at 23:30 Lagos time).

---

## P7 — Payments, step by step

**Interface**

```php
interface PaymentGatewayInterface
{
    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = []): array; // ['authorization_url' => ...]
    public function verify(string $reference): array;       // normalised: ['status','amount_kobo','paid_at','raw']
    public function refund(string $reference, ?int $amountKobo = null): array;
    public function transfer(string $recipientCode, int $amountKobo, string $reference): array;
}
```

**`PaystackGateway` (skeleton)**

```php
class PaystackGateway implements PaymentGatewayInterface
{
    private function http() {
        return Http::withToken(config('services.paystack.secret'))
            ->baseUrl('https://api.paystack.co')->timeout(20)->retry(2, 300);
    }

    public function initialize(string $reference, int $amountKobo, string $email, string $callbackUrl, array $meta = []): array
    {
        $r = $this->http()->post('/transaction/initialize', [
            'reference' => $reference, 'amount' => $amountKobo, 'email' => $email,
            'callback_url' => $callbackUrl, 'metadata' => $meta,
        ])->throw()->json('data');
        return ['authorization_url' => $r['authorization_url']];
    }

    public function verify(string $reference): array
    {
        $d = $this->http()->get("/transaction/verify/{$reference}")->throw()->json('data');
        return ['status' => $d['status'], 'amount_kobo' => $d['amount'], 'paid_at' => $d['paid_at'] ?? null, 'raw' => $d];
    }
    // refund() and transfer() follow the same pattern
}
```

`config/services.php`: `'paystack' => ['secret' => env('PAYSTACK_SECRET'), 'public' => env('PAYSTACK_PUBLIC')]`. Check endpoints and field names against current Paystack and Flutterwave docs before shipping.

**Tables**

```
transactions:   id, seller_id, payable_type, payable_id, reference (unique), gateway,
                amount_kobo, commission_kobo, status, paid_at, gateway_payload JSON
webhook_events: id, gateway, event_id (unique per gateway), payload JSON, processed_at
wallets:        id, seller_id (unique), balance_kobo, pending_kobo
wallet_ledger:  id, wallet_id, transaction_id NULL, direction ENUM('debit','credit'),
                amount_kobo, balance_after_kobo, reason, created_at   -- immutable
payouts:        id, seller_id, amount_kobo, status, bank_code, account_number, account_name, reference
```

**`InitializePayment`**

```php
public function handle(Model $payable, string $email): string
{
    $amount = $payable instanceof Booking ? ($payable->deposit_kobo ?: $payable->price_kobo) : $payable->total_kobo;
    $ref = 'MP'.strtoupper(Str::random(12));

    Transaction::create([
        'seller_id' => $payable->seller_id, 'payable_type' => $payable->getMorphClass(),
        'payable_id' => $payable->id, 'reference' => $ref, 'gateway' => config('mypage.gateway'),
        'amount_kobo' => $amount, 'status' => 'pending',
    ]);

    return $this->gateway->initialize($ref, $amount, $email, route('checkout.callback', $ref))['authorization_url'];
}
```

Register `Relation::morphMap(['order' => Order::class, 'booking' => Booking::class])` so payable types are stable strings.

**Webhook controller**

```php
public function paystack(Request $request)
{
    $sig = hash_hmac('sha512', $request->getContent(), config('services.paystack.secret'));
    abort_unless(hash_equals($sig, (string) $request->header('x-paystack-signature')), 401);

    $payload = $request->json()->all();
    $eventId = $payload['data']['id'] ?? Str::uuid();

    $event = WebhookEvent::firstOrCreate(
        ['gateway' => 'paystack', 'event_id' => (string) $eventId],
        ['payload' => $payload]
    );
    if (! $event->wasRecentlyCreated) return response()->noContent();   // duplicate delivery

    ProcessPaymentWebhook::dispatch($event->id);
    return response()->noContent();
}
```

Exclude `webhooks/*` from CSRF in `bootstrap/app.php` (`$m->validateCsrfTokens(except: ['webhooks/*'])`).

**`ConfirmTransaction` (used by webhook job and the callback page)**

```php
DB::transaction(function () use ($reference) {
    $tx = Transaction::where('reference', $reference)->lockForUpdate()->firstOrFail();
    if ($tx->status === 'paid') return;                       // idempotent

    $v = $this->gateway->verify($reference);
    if ($v['status'] !== 'success' || $v['amount_kobo'] !== $tx->amount_kobo) {
        $tx->update(['status' => 'failed', 'gateway_payload' => $v['raw']]); return;
    }

    $commission = CommissionCalculator::for($tx->amount_kobo);   // intdiv($amt * bps, 10_000)
    $tx->update(['status' => 'paid', 'paid_at' => now(), 'commission_kobo' => $commission, 'gateway_payload' => $v['raw']]);
    $this->ledger->credit($tx->seller->wallet, $tx->amount_kobo - $commission, $tx, 'sale');
    event(new PaymentConfirmed($tx));
});
```

Listeners on `PaymentConfirmed`: mark order `paid` / booking `confirmed`, decrement stock, upsert customer, notify seller.

**`LedgerService`** — every move writes two rows (seller wallet credit plus platform/escrow account debit) inside one transaction; lock the wallet row (`lockForUpdate`), compute `balance_after`, and refuse negative balances on debit. Never expose update/delete on `WalletLedger`.

**Payouts:** `RequestPayout` checks balance and minimum, debits the wallet into `pending`, queues `ProcessPayout`; on gateway success mark `paid`, on failure re-credit through the ledger. Resolve the bank account name before saving a recipient.

**Pest money tests:** valid signature, bad signature, replayed webhook, amount mismatch, double confirm, commission rounding (e.g. 150 bps on 1,999 kobo), ledger sums to zero, payout failure restores balance.

---

## P8 — Orders, bookings pipeline and customers, step by step

```bash
php artisan make:model Domains/Orders/Models/Order -mfp
php artisan make:model Domains/Orders/Models/OrderItem -m
php artisan make:model Domains/Orders/Models/OrderStatusHistory -m
php artisan make:model Domains/Customers/Models/Customer -mf
```

**`CreateOrderFromCheckout`**

```php
DB::transaction(function () use ($page, $cart, $buyer) {
    $products = Product::whereIn('id', collect($cart)->pluck('id'))->lockForUpdate()->get()->keyBy('id');
    $total = 0; $lines = [];

    foreach ($cart as $line) {
        $p = $products[$line['id']] ?? throw new InvalidCart();
        throw_if($p->status !== 'active', new InvalidCart());
        $unit = $p->price_kobo;              // price comes from the DB, never the client
        $total += $unit * $line['qty'];
        $lines[] = ['product_id' => $p->id, 'name' => $p->name, 'unit_kobo' => $unit, 'qty' => $line['qty']];
    }

    $order = Order::create([
        'seller_id' => $page->seller_id, 'store_page_id' => $page->id,
        'customer_id' => app(UpsertCustomer::class)->handle($page->seller_id, $buyer)->id,
        'reference' => 'OD'.strtoupper(Str::random(8)),
        'total_kobo' => $total, 'status' => OrderStatus::Pending,
    ]);
    $order->items()->createMany($lines);
    return $order;
});
```

Stock is decremented when payment confirms (or when the seller marks a WhatsApp order as received), using `AdjustStock`.

**`UpdateOrderStatus` — the only way to change status**

```php
public function handle(Order $o, OrderStatus $to, ?User $by = null, ?string $note = null): Order
{
    throw_unless($o->status->canMoveTo($to), new InvalidTransition($o->status, $to));
    DB::transaction(function () use ($o, $to, $by, $note) {
        $from = $o->status;
        $o->update(['status' => $to]);
        $o->history()->create(['from' => $from, 'to' => $to, 'by' => $by?->id, 'note' => $note]);
    });
    event(new OrderStatusChanged($o));
    return $o;
}
```

Define `canMoveTo()` in the enum with a transition map. Do the same for `BookingStatus` (`pending_payment → confirmed → completed`, plus `cancelled` and `no_show`).

**`UpsertCustomer`** — `Customer::firstOrCreate(['seller_id' => ..., 'phone' => PhoneNumber::normalize(...)], [...])`, then refresh `orders_count`, `lifetime_kobo`, `last_activity_at`. Add a unique index on `(seller_id, phone)`.

**Inbox UI:** one Blade table component (`x-inbox.table`) taking rows, columns and a status filter; clicking a row opens a drawer with the detail partial (`orders/_detail` or `bookings/_detail`). Poll with a small Alpine `setInterval` fetch (every 30s) for a new-item badge, or use Livewire `wire:poll` on this one screen.

**Notifications:** Laravel `Notification` classes (`NewOrderReceived`, `BookingConfirmed`) on the `mail` channel, queued; add a WhatsApp channel in P11.

---

## P9 — Billing and escrow, step by step

**Plans seeder:** e.g. `starter` (₦3,500/mo), `pro`, with limits in a JSON `features` column (product limit, staff limit, custom domain flag).

**Subscription gate**

```php
class EnsureSubscriptionValid
{
    public function handle(Request $r, Closure $next)
    {
        $sub = $r->user()->activeSubscription();
        if (! $sub || $sub->ends_at->addDays(3)->isPast()) {   // 3-day grace
            return redirect()->route('billing.index')->with('status', 'Please renew to keep editing.');
        }
        return $next($r);
    }
}
```

Apply to dashboard write routes only. Never make a public storefront return an error because of billing without a clear grace and warning flow.

**Escrow flow**

1. `PaymentConfirmed` with escrow enabled → credit the escrow ledger account instead of the seller wallet; `escrow_accounts` row holds `release_at`.
2. Buyer receives a signed "Confirm delivery" link → `ReleaseEscrow` moves funds to the seller wallet (minus commission).
3. `ReleaseAutoEscrow` job runs daily via the scheduler: releases rows past `release_at` that have no open dispute.
4. Dispute: buyer opens it before release → `escrow_accounts.status = 'disputed'` → admin `ResolveDispute` either releases to seller or refunds through the gateway.
5. For bookings, release when status becomes `completed` (or auto after the appointment plus N days).

**Badge:** `BadgeApplication` with uploaded documents on a private disk, admin approve/reject, `ApproveBadge` sets `store_pages.verified_at` and clears the cache.

---

## P10 — Admin panel, step by step

1. Separate guard (`admins` provider and table) and `layouts/admin.blade.php`; protect with `auth:admin` and optional IP allow-list.
2. Controllers from your plan; every state-changing action wrapped with `activity()->causedBy($admin)->performedOn($model)->log('...')`.
3. Screens in build order: sellers (search, suspend), transactions, payouts approval, disputes, badge queue, feature flags, metrics (GMV, commission, stores by theme, bookings per week).
4. `SuspendStorePage` sets `status = suspended`, flushes cache, and emails the seller with a reason.

---

## P11 — Messaging, step by step

1. Store WhatsApp credentials encrypted (`'token' => 'encrypted'` cast).
2. `WhatsAppCloudApiClient::sendTemplate($to, $template, $params)` using `Http::withToken(...)->post("https://graph.facebook.com/{version}/{phone_number_id}/messages", [...])`; confirm the current API version and payload format in Meta's docs.
3. Inbound webhook: GET for the verify-token handshake, POST with `X-Hub-Signature-256` HMAC check; dedupe by message id like the payment webhooks.
4. Reminder commands send booking reminders at 24h and 2h before `starts_at`; mark each sent in a `reminders_sent` JSON column to avoid repeats.
5. Broadcasts: `QueueBroadcast` snapshots recipients (opted-in only), `SendBroadcastChunk` jobs of \~50 with `RateLimited` middleware; `OptOutManager` checked right before each send. Show the estimated ₦ cost from `MessageCostCalculator` before confirming.

---

## P12 — Directory and SEO, step by step

1. Migration for `directory_listings` (store\_page\_id, theme, category, state, city, name, summary, rating, verified, `FULLTEXT(name, summary)`).
2. Listener on `StorePagePublished` / `Updated` dispatches `IndexStoreForSearch`; nightly `ReindexDirectory`.
3. Routes `/directory`, `/directory/{theme}`, `/directory/{state}/{city?}`; paginate 24 per page; cache per filter combo.
4. Sitemap: `GenerateSitemapCommand` writes `public/sitemaps/*.xml` chunks plus an index; schedule daily; reference in `robots.txt`.
5. Add JSON-LD in the theme layouts (`LocalBusiness` or `Store`).

---

## P13 — Custom domains, step by step

1. `domain_bindings` (store\_page\_id, host unique, status, verified\_at, ssl\_status).
2. UI shows a CNAME target; `VerifyDomainDns` job polls DNS (`dns_get_record`) with backoff.
3. TLS via Cloudflare for SaaS custom hostnames or Caddy on-demand TLS with an `ask` endpoint that checks the host exists in `domain_bindings`.
4. `ResolveStorePage` first tries the subdomain pattern, else looks up `domain_bindings.host`; add the fallback route group without the `{slug}.` prefix and guard so apex hosts never fall into it.

---

## P14 — Optional API

`php artisan install:api` (Sanctum), version routes under `/api/v1`, add `JsonResource` classes for Product, Service, Order, Booking, and rate limit with `RateLimiter::for('api', ...)`. Reuse the same Actions; controllers stay thin.

---

## P15 — Hardening and launch checklist

```bash
composer audit
php artisan optimize
php artisan route:cache && php artisan view:cache && php artisan config:cache
php artisan horizon:terminate    # on every deploy so workers reload
php artisan migrate --force
```

- Forge deploy script: pull, `composer install --no-dev -o`, `npm ci && npm run build`, migrate, optimize, `horizon:terminate`.
- Daemon for Horizon, scheduler cron `* * * * * php artisan schedule:run`.
- Add `Model::preventLazyLoading(! app()->isProduction())` and `Model::shouldBeStrict()` in non-production.
- Backups: nightly DB dump to S3-compatible storage with a monthly restore test.
- Alerts: Sentry, failed-jobs alert, uptime check on one demo storefront per theme, webhook failure alert.
- Load test the storefront and availability endpoint (k6 or similar) at expected peak.
- Seed the two demo pages (`DemoSellerSeeder`) and use them as the sales demos.
- Beta: 10 to 20 sellers per theme, weekly fix cycle, then public launch.

---

# Part 3 — Database Plan

## 3.1 Conventions

- Engine InnoDB, charset `utf8mb4`, collation `utf8mb4_unicode_ci`.
- Primary keys: `bigint unsigned` auto-increment internally. Public identifiers (order, booking, transaction) use a separate short `reference` column so IDs are never exposed in URLs.
- **Money:** `unsignedBigInteger` kobo columns named `*_kobo`. No decimals, no floats.
- **Time:** store UTC; show in `Africa/Lagos`. Use `timestamp`/`datetime` consistently (prefer `datetime` for `starts_at`/`ends_at` to avoid 2038 and timezone surprises).
- **Tenancy:** every tenant table has `seller_id` (indexed, FK). Child tables that always join through a parent (images, items) may skip it, but then must be queried only through the parent.
- **Soft deletes** on products, services, customers; **never** on ledger, transactions, webhook events.
- **Enums** stored as short strings validated by PHP backed enums (easier to migrate than DB enums).
- Foreign keys: `restrictOnDelete` for money tables, `cascadeOnDelete` for pure child data.

## 3.2 Migration order

```
01 sellers, password_reset_tokens, sessions, admins
02 store_pages, store_sections, domain_bindings
03 categories, products, product_images, product_variants, inventory_logs      (Store)
04 services, staff_members, service_staff, working_hours, time_off              (Service)
05 customers
06 orders, order_items, order_status_histories, carts                            (Store)
07 bookings, booking_status_histories                                            (Service)
08 transactions, webhook_events, wallets, wallet_ledger, escrow_accounts, payouts, bank_accounts
09 plans, subscriptions, badge_applications
10 whatsapp_connections, message_templates, broadcast_campaigns, broadcast_recipients, message_logs
11 directory_listings
12 reviews, activity_log, feature_flags, jobs/failed_jobs, notifications
```

## 3.3 Tables by domain

### Identity and pages

```
sellers
  id, name, email UNIQUE, phone UNIQUE NULL, password, email_verified_at,
  status (active|suspended), last_login_at, remember_token, timestamps

store_pages
  id, seller_id FK, slug UNIQUE, name, theme (store|service), status (draft|published|suspended),
  tagline, bio, logo_path, cover_path, whatsapp_number, address, city, state,
  brand_color, font_pair, settings JSON, verified_at NULL, published_at NULL, timestamps
  INDEX(status, theme), INDEX(state, city)

store_sections
  id, store_page_id FK, key, position, is_enabled, content JSON, timestamps
  UNIQUE(store_page_id, key)

domain_bindings
  id, store_page_id FK, host UNIQUE, status, verified_at, ssl_status, timestamps
```

One seller can own several pages later (`seller_id` is on `store_pages`); v1 UI limits to one.

### Catalog (Store theme)

```
categories        id, seller_id, store_page_id, name, slug, position            UNIQUE(store_page_id, slug)
products          id, seller_id, store_page_id, category_id NULL, name, slug, description,
                  price_kobo, compare_at_kobo NULL, sku NULL, track_stock, stock_qty,
                  status, is_featured, deleted_at, timestamps
                  UNIQUE(store_page_id, slug), INDEX(store_page_id, status), FULLTEXT(name, description)
product_images    id, product_id, path, position, alt
product_variants  id, product_id, name, options JSON, price_kobo NULL, stock_qty, sku NULL
inventory_logs    id, seller_id, product_id, variant_id NULL, delta, reason, ref_type, ref_id, created_at
```

### Services (Service theme)

```
services        id, seller_id, store_page_id, name, slug, description, image_path,
                duration_min, buffer_min, price_kobo, deposit_type, deposit_value,
                settings JSON, position, is_active, deleted_at, timestamps
staff_members   id, seller_id, store_page_id, name, photo_path, bio, is_active, timestamps
service_staff   service_id FK, staff_member_id FK         PRIMARY(service_id, staff_member_id)
working_hours   id, store_page_id, staff_member_id NULL, weekday (1-7), start_time, end_time
time_off        id, store_page_id, staff_member_id NULL, starts_at, ends_at, reason NULL
```

### Customers, orders, bookings

```
customers   id, seller_id, name, phone, email NULL, whatsapp_opt_in, opted_out_at NULL,
            orders_count, bookings_count, lifetime_kobo, last_activity_at, notes TEXT NULL, deleted_at, timestamps
            UNIQUE(seller_id, phone)

orders      id, seller_id, store_page_id, customer_id, reference UNIQUE, status, payment_status,
            subtotal_kobo, delivery_fee_kobo, total_kobo, delivery_address JSON NULL, note NULL,
            source (web|whatsapp), timestamps    INDEX(store_page_id, status, created_at)
order_items id, order_id, product_id NULL, variant_id NULL, name, unit_kobo, qty, total_kobo
order_status_histories   id, order_id, from_status, to_status, by_type, by_id NULL, note, created_at
carts       id, store_page_id, token UNIQUE, customer_phone NULL, items JSON, reminded_at NULL, timestamps

bookings    id, seller_id, store_page_id, service_id, staff_member_id NULL, customer_id,
            reference UNIQUE, starts_at, ends_at, status, price_kobo, deposit_kobo, expires_at NULL,
            notes, source, reminders_sent JSON NULL, timestamps
            INDEX(store_page_id, starts_at), INDEX(staff_member_id, starts_at), INDEX(status, expires_at)
booking_status_histories id, booking_id, from_status, to_status, by_type, by_id NULL, note, created_at
```

Snapshot product and service names and prices into `order_items` / `bookings` so later edits never change history.

### Money

```
transactions   id, seller_id, payable_type, payable_id, reference UNIQUE, gateway, amount_kobo,
               commission_kobo, gateway_fee_kobo NULL, status (pending|paid|failed|refunded),
               paid_at NULL, gateway_payload JSON, timestamps   INDEX(payable_type, payable_id)
webhook_events id, gateway, event_id, payload JSON, processed_at NULL, created_at   UNIQUE(gateway, event_id)
wallets        id, seller_id UNIQUE, balance_kobo, pending_kobo, timestamps
wallet_ledger  id, wallet_id, transaction_id NULL, direction (debit|credit), amount_kobo,
               balance_after_kobo, reason, meta JSON NULL, created_at         -- append-only
escrow_accounts id, transaction_id UNIQUE, seller_id, amount_kobo, status (held|released|disputed|refunded),
               release_at, released_at NULL, dispute_reason NULL, timestamps
bank_accounts  id, seller_id, bank_code, account_number (encrypted), account_name, recipient_code NULL, is_default
payouts        id, seller_id, bank_account_id, amount_kobo, status, reference UNIQUE, failure_reason NULL, timestamps
```

**Integrity rules**

- `CHECK (amount_kobo > 0)` on ledger and transaction rows (MySQL 8.0.16+).
- Revoke `UPDATE`/`DELETE` on `wallet_ledger` for the app DB user where practical, or enforce with a model that throws on update/delete.
- A nightly reconciliation command asserts `wallets.balance_kobo` equals the sum of ledger rows and flags any difference.

### Billing, messaging, directory, misc

```
plans                id, code UNIQUE, name, price_kobo, interval, features JSON, is_active
subscriptions        id, seller_id, plan_id, status, starts_at, ends_at, gateway_ref NULL, timestamps
badge_applications   id, store_page_id, status, documents JSON, reviewed_by NULL, reviewed_at NULL, reason NULL, timestamps
whatsapp_connections id, seller_id, phone_number_id, business_id, token (encrypted), status, timestamps
message_templates    id, seller_id, name, category (utility|marketing), language, body, status, external_id
broadcast_campaigns  id, seller_id, template_id, status, est_cost_kobo, scheduled_at, sent_count, timestamps
broadcast_recipients id, campaign_id, customer_id, status, sent_at NULL, error NULL
directory_listings   id, store_page_id UNIQUE, theme, category, state, city, name, summary, rating, verified, FULLTEXT(name, summary)
reviews              id, store_page_id, reviewable_type NULL, reviewable_id NULL, customer_id NULL, rating, body, status, timestamps
feature_flags        id, key UNIQUE, enabled, rollout JSON NULL
```

## 3.4 Indexing and performance notes

- Always index `(seller_id, created_at)` on inbox tables that sellers sort by recency.
- Storefront queries filter by `store_page_id` and `status`; keep composite indexes starting with those.
- Avoid `OFFSET` pagination on large inboxes later; plan cursor pagination (`->cursorPaginate()`).
- Use Redis for page cache, sessions and queues, not MySQL.

## 3.5 Seeders and factories

- `PlanSeeder` (starter and pro), `FeatureFlagSeeder`, `DemoSellerSeeder`.
- `DemoSellerSeeder` creates one published **Store** page (12 products across 3 categories, 5 orders in different states) and one published **Service** page (6 services, 2 staff, working hours Mon–Sat, 8 bookings).
- Factories for every model; use them in Pest tests and for staging data.

## 3.6 Backup and migration discipline

- Never edit a migration that has run in production; add a new one.
- Zero-downtime changes: add nullable column → backfill via job → add constraint later.
- Test `migrate:fresh --seed` in CI on every PR.

---

# Part 4 — Design Plan (Store and Service themes)

## 4.1 Design principles

1. **Mobile first.** Most buyers and sellers will be on phones, often on slow data. Design at 375px, then scale up.
2. **One obvious action per screen.** Store: Add to cart / Order on WhatsApp. Service: Book now.
3. **Light pages.** Budget: under 100 KB HTML + CSS, under 300 KB images above the fold, no render-blocking third-party scripts.
4. **Seller identity first.** Logo, name and brand colour carry the page; the platform stays quiet (small "Powered by mypage.ng" footer link).
5. **Trust cues:** verified badge, reviews, visible WhatsApp contact, clear return/cancellation text.

## 4.2 Design tokens

| Token | Value |
| --- | --- |
| Base unit | 4px spacing scale (4, 8, 12, 16, 24, 32, 48, 64) |
| Radius | 8px controls, 12px cards, full for pills |
| Brand | `--brand` (seller-chosen) and an auto-computed `--brand-contrast` (black or white by luminance) |
| Neutrals | Slate scale for text and borders |
| Type scale | 14 / 16 / 18 / 20 / 24 / 32 / 40; body 16px minimum |
| Font pairs | modern (Inter/Inter), elegant (Playfair Display/Lato), bold (Poppins/Open Sans), friendly (Nunito/Nunito) |
| Touch targets | at least 44×44px |

**Brand colour presets (offer 8 plus custom):** teal, emerald, blue, indigo, rose, orange, amber, slate. Reject custom colours that fail a 4.5:1 contrast check against the computed text colour.

## 4.3 Store theme — page layout (top to bottom)

1. **Header:** logo, store name, search icon, cart icon with count (sticky).
2. **Hero:** cover image, tagline, primary button "Shop now", secondary "Chat on WhatsApp".
3. **Categories strip:** horizontally scrollable pills.
4. **Featured products:** 2-column grid on mobile (4 on desktop).
5. **Product grid:** card = image (4:5), name, price, compare-at price struck through, quick-add button; filter and sort sheet on mobile.
6. **Reviews:** 3 latest, average rating.
7. **About + delivery info:** bio, areas served, delivery/returns text.
8. **Contact + footer:** WhatsApp, phone, address, social links, "Powered by".

**Product page:** image gallery (swipe), title, price, variant picker, quantity, sticky bottom bar on mobile with "Add to cart" and a WhatsApp icon; description; related products.

**Cart drawer:** line items with qty steppers, subtotal, delivery note, two buttons: "Pay now" and "Order via WhatsApp".

**Checkout (single page):** name, phone, email, delivery address (state select), order summary, pay button. Guest checkout only.

## 4.4 Service theme — page layout (top to bottom)

1. **Header:** logo, name, "Book now" button (sticky).
2. **Hero:** cover image or team photo, tagline, rating summary, "Book now" and WhatsApp.
3. **Services list:** cards with name, duration, price (and "from" prices), short description, "Book" button.
4. **Team:** staff photos, names, specialties.
5. **How it works / policies:** 3 steps, cancellation and deposit policy.
6. **Gallery:** 6–12 images in a lightbox.
7. **Reviews, About, Location + opening hours:** hours table, address, map link (a plain link or static image, no heavy embeds).
8. **Contact + footer.**

**Booking flow (full-screen sheet on mobile):** choose service → choose staff (optional, "Any available") → date strip + time slots → your details → pay deposit or confirm → confirmation screen with reference, "Add to calendar" (.ics download) and WhatsApp link.

## 4.4b What is different between themes (summary)

| Aspect | Store | Service |
| --- | --- | --- |
| Hero CTA | Shop now | Book now |
| Main list | Product grid | Service cards |
| Sticky element | Cart | Book button |
| Social proof | Product reviews | Service and staff reviews |
| Info block | Delivery and returns | Hours, location, cancellation policy |

## 4.5 Shared components

`x-themes.shared.whatsapp-button` (floating, bottom right, safe-area aware), `rating-stars`, `price`, `image` (lazy, srcset, fixed aspect ratio to avoid layout shift), `empty-section`, `verified-badge`, `social-links`, `opening-hours`, `review-card`.

## 4.6 Dashboard design

- **Navigation:** bottom tab bar on mobile (Home, Inbox, Items, Page, More), sidebar on desktop.
- **Home:** today's numbers (orders or bookings, revenue, visits), a "get started" checklist (add first product/service, add WhatsApp number, publish, share link), and the share box (copy link, QR code, WhatsApp share).
- **Inbox:** list with status chips, tap for drawer with customer info, items/booking details, status action buttons, and one-tap WhatsApp-the-customer.
- **Items (Products or Services):** simple list with thumbnail, price, toggle active; add form is a single scrolling page with image upload first.
- **Page editor:** tabs Branding / Sections / SEO / Contact / Publish with the live preview.
- **Empty states** with a single action and a short sentence; skeleton loaders on lists.

## 4.7 Accessibility and quality bar

- WCAG AA contrast, visible focus rings, labels on all inputs, `alt` text fields on images, semantic headings, reduced-motion respected.
- Forms: inline errors, input types for phone/email, `autocomplete` attributes, Nigerian phone format hint.
- Test devices: a low-end Android on throttled 3G, an iPhone Safari, desktop Chrome.
- Lighthouse targets: Performance 85+, Accessibility 95+, SEO 95+ on both demo pages.

## 4.8 Design deliverables and order

1. Tokens + UI kit (buttons, inputs, cards) in Tailwind.
2. Store theme: home, product, cart, checkout (4 screens).
3. Service theme: home, service detail, booking flow, confirmation (4 screens).
4. Dashboard: home, inbox, item form, page editor (4 screens).
5. Transactional emails (order, booking, payout) using a single email layout.
6. Marketing site: landing, pricing, directory.

---

# Part 5 — Testing and Launch Plan

## 5.1 Test strategy

| Layer | Tooling | Must cover |
| --- | --- | --- |
| Unit | Pest | Money, PhoneNumber, CommissionCalculator, AvailabilityService, status transitions |
| Feature | Pest + factories | Auth, onboarding, tenancy leaks, CRUD policies, checkout, booking, publish flow |
| Money paths | Pest, 100% | Init, verify, webhooks (signature, replay), ledger, escrow, payouts |
| Browser | Pest Browser or Dusk (smoke) | Register → create store → publish; buyer places order; visitor books slot |
| Integration | Gateway sandboxes | Paystack/Flutterwave test keys, WhatsApp test number |
| Static | Larastan, Pint | Level 6+ clean in CI |
| Load | k6 | Storefront and availability endpoint at expected peak |
| Security | Manual checklist + `composer audit` | See 5.3 |

## 5.2 Environments and release flow

`local` → `staging` (sandbox keys, seeded demo data, Telescope on) → `production`. Every PR runs CI; merge to `main` deploys to staging; production is promoted manually after a smoke checklist. Keep a rollback plan: previous release kept on the server and migrations written to be backward-compatible for one release.

## 5.3 Pre-launch security checklist

- [ ] CSRF on all web forms; webhooks excluded and signature-verified.
- [ ] Rate limits on login, register, availability, checkout, password reset.
- [ ] Authorization policy test for every seller-owned model.
- [ ] File uploads: size limit, mime check, re-encode images, private disk for badge documents.
- [ ] Seller-supplied text escaped in Blade (`{{ }}`); no `{!! !!}` on user content.
- [ ] Secrets only in env; bank account numbers and WhatsApp tokens encrypted.
- [ ] Admin behind separate guard, 2FA recommended, activity log on.
- [ ] Backups restored successfully at least once.
- [ ] Privacy policy, terms, and consent text on checkout/booking forms (NDPR).

## 5.4 Go-live checklist

1. Production env vars set; live Paystack/Flutterwave keys and webhook URLs registered.
2. Wildcard DNS and TLS for `*.mypage.ng` verified with a test subdomain.
3. Horizon running, scheduler cron active, failed-job alerting tested.
4. One real ₦ payment (store) and one real deposit (service) completed and reconciled, then refunded.
5. Payout of a small amount to a real bank account.
6. Transactional emails deliver (SPF, DKIM, DMARC set).
7. Sitemap and robots in place; demo pages indexed.
8. Status page and support contact visible.

## 5.5 Launch rollout

| Stage | Duration | Who | Goal |
| --- | --- | --- | --- |
| Private alpha | 2 weeks | 5 friendly sellers (3 store, 2 service) | Find blocking bugs; watch them onboard on a call |
| Closed beta | 4 weeks | 30–50 sellers | Collect first real payments; measure activation |
| Public launch | Week 8+ | Open signup | Paid plans on, features still flag-gated |

Keep escrow, broadcasts, directory and custom domains **off** at launch and enable them per cohort once the basics are stable.

## 5.6 Getting the first sellers (Nigeria-focused ideas)

- Recruit directly from Instagram and WhatsApp-based vendors; offer to set their page up for them in the first call.
- Pick two niches per theme to start (for example fashion and beauty products; salons and tutors/photographers) so demo pages and messaging can be specific.
- Referral reward: free month for each paid seller they bring.
- Campus and market-association partnerships; community WhatsApp groups of small businesses.
- Make the seller's own page the marketing: "Powered by mypage.ng" footer link with a signup URL and `ref` tracking (`TrackAffiliateReferral` middleware).
- Ask early sellers for a short testimonial and a permission to feature their page in the directory.

## 5.7 Metrics to watch

| Metric | Why |
| --- | --- |
| Signup → published page rate and time to publish | Onboarding quality |
| Pages with ≥1 product/service after 7 days | Activation |
| First order/booking within 14 days | Real value delivered |
| Payment success rate and webhook failure rate | Revenue health |
| Weekly active sellers, paid conversion, monthly churn | Business health |
| Median storefront load time on mobile | Experience |
| Support tickets per 100 sellers | Product friction |

## 5.8 Post-launch 90-day roadmap

- **Days 1–30:** fix beta issues, improve onboarding, add the top 3 requested theme options (colours, section tweaks).
- **Days 31–60:** turn on escrow for a cohort; enable WhatsApp order and booking notifications; publish the directory.
- **Days 61–90:** custom domains, broadcasts with cost estimator, referral programme, first analytics dashboard improvements. Decide on a third theme only if data shows sellers who do not fit either.

## 5.9 Key risks and mitigations

| Risk | Mitigation |
| --- | --- |
| Webhook failures lose payments | Verify on callback page too; nightly reconciliation job comparing gateway and DB |
| Double-booking | Locked transaction in `CreateBooking`, concurrency test |
| Cross-seller data leak | Global scope plus architecture test plus policy tests |
| Slow pages on weak networks | Page cache, WebP images, lazy loading, performance budget |
| WhatsApp pricing or policy changes | Keep `wa.me` fallback; cost calculator reads rates from config |
| Fraud or disputes | Escrow, verified badge, admin review queue, payout approval at start |
| Scope creep | Hold to two themes and the phase exit criteria; log requests in a backlog |

---

# Part 6 — Agent Build Playbook (for an AI coding agent)

This part is the **execution contract**. An agent should read Parts 1–5 for context, then build strictly by the task list in 6.5, in order. Each task has a *Done when* check that can be verified by a command or a visible behaviour.

## 6.1 Kickoff prompt (paste this to the agent)

```text
You are building MYPAGE.NG from scratch: a Laravel 13 + Blade + Tailwind 4 + Alpine app
(PHP 8.4, MySQL 8, Redis) that gives Nigerian sellers a hosted page at username.mypage.ng.
There are exactly TWO themes: "store" (products) and "service" (bookable businesses).

Read the full plan document (Parts 1-6). Then:
1. Create AGENTS.md in the repo root using the text in section 6.2.
2. Execute tasks in section 6.5 in order, one task at a time.
3. After each task: run the verification commands in 6.4, fix failures, commit with the
   message format in 6.3, then continue.
4. Do not skip ahead, do not add features outside the plan, do not add a third theme.
5. If a task is ambiguous or blocked, check 6.6 (default decisions). If still unclear, write
   the question to docs/OPEN_QUESTIONS.md, pick the safest default, and continue.
6. Before using any package or API (Laravel 13, Livewire, Paystack, Flutterwave, WhatsApp
   Cloud API), check its current official documentation; do not rely on memory for
   signatures, endpoints, or payload shapes.
7. At the end of each phase, update docs/PROGRESS.md with what was built and what remains.
```

## 6.2 `AGENTS.md` content (create at repo root)

```markdown
# AGENTS.md — MYPAGE.NG

## Stack
PHP 8.4, Laravel 13, MySQL 8, Redis, Blade, Tailwind CSS 4, Alpine.js, Pest, Horizon.
Livewire is allowed ONLY for: orders/bookings inbox and booking calendar (optional).

## Product scope
- Two themes only: `store` and `service`. A store page has one theme.
- Public pages at {slug}.mypage.ng; dashboard at apex domain.

## Architecture rules
1. Code lives in app/Domains/<Domain>/... (Actions, Models, Http, Services, Jobs, Events, Policies).
2. Controllers are thin: validate (FormRequest) -> call an Action -> return a view/redirect.
3. Cross-domain communication via Events or Service interfaces, never reaching into another domain's models from views.
4. Every seller-owned model uses BelongsToSeller. Never query tenant data without it.
5. Money = integer kobo, columns end with _kobo. Never floats.
6. Payment gateways only behind PaymentGatewayInterface.
7. Webhooks: verify signature, store event id (unique), process on queue, idempotent.
8. Any status change goes through its Action (UpdateOrderStatus / UpdateBookingStatus) and writes a history row.
9. Wallet ledger rows are append-only; each movement is two rows inside one DB transaction.
10. Slow work (images, email, WhatsApp, payouts, sitemap) goes on queues.
11. Escape all user content in Blade with {{ }}. No {!! !!} on user data.
12. Time stored in UTC, displayed Africa/Lagos.

## Commands
- Tests: `php artisan test` (or `vendor/bin/pest`)
- Format: `vendor/bin/pint`
- Static analysis: `vendor/bin/phpstan analyse`
- Frontend build: `npm run build`
- Fresh DB: `php artisan migrate:fresh --seed`

## Definition of done (every task)
- Code + tests written, `pest`, `pint --test`, `phpstan` pass, `npm run build` passes.
- Migrations run on a fresh database.
- No TODOs left in committed code unless logged in docs/OPEN_QUESTIONS.md.
- Commit message: `<type>(<domain>): <summary>` e.g. `feat(booking): add availability service`.

## Do not
- Add themes, page builders, or packages not listed in the plan without logging a reason.
- Hardcode secrets, keys, or tokens.
- Edit a migration that was already committed; add a new one.
- Call payment or WhatsApp APIs from controllers.
```

## 6.3 Git workflow

- One branch per phase: `phase/p0-setup`, `phase/p1-auth`, ... merged to `main` after its exit check.
- Commit after every task. Conventional commit types: `feat`, `fix`, `test`, `chore`, `docs`, `refactor`.
- Tag each completed phase: `v0.<phase>.0`.

## 6.4 Verification commands (run after each task)

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
npm run build
php artisan migrate:fresh --seed      # at least at the end of each phase
```

For UI tasks also load the page locally (`php artisan serve` plus a hosts entry or Herd for `demo.mypage.test`) and confirm the behaviour described in *Done when*.

## 6.5 Ordered task list

Format: **ID — Task** → files/areas → *Done when*.

### Phase 0 — Foundation

- [ ] **T0.1 — Create Laravel project and install packages** (see Part 2, P0). *Done when:* `php artisan about` runs; Pest, Pint, Larastan, Horizon installed.
- [ ] **T0.2 — Frontend toolchain** (Vite, Tailwind 4, Alpine). *Done when:* `npm run build` succeeds and a test Blade page uses a Tailwind class and an Alpine `x-data` toggle.
- [ ] **T0.3 — Domain folders and `DomainServiceProvider`**. *Done when:* a sample view in `app/Domains/Seller/resources/views` renders via `view('seller::test')`.
- [ ] **T0.4 — Config files** `mypage.php`, `themes.php`, `billing.php`, `services.php` entries, `.env.example` updated. *Done when:* `config('mypage.commission_bps')` returns 150.
- [ ] **T0.5 — CI workflow and tooling configs** (`phpstan.neon`, `pint.json`, GitHub Actions). *Done when:* workflow file exists and the same commands pass locally.
- [ ] **T0.6 — Shared helpers**: `Money`, `PhoneNumber`, `NgnFormatter`, `NigerianPhone`, `SafeSlug`, `Color::contrast`. *Done when:* unit tests cover `₦12,500` ↔ `1250000`, phone normalisation (`0803...`, `+234803...`, `234803...` all become `+234803...`), and slug rules.

### Phase 1 — Auth and onboarding

- [ ] **T1.1 — Migrations and models**: `sellers`, `store_pages`, `store_sections` + factories. *Done when:* `migrate:fresh` works and factories create valid records.
- [ ] **T1.2 — Registration, login, logout, email verification, password reset** (Blade views, FormRequests, throttling). *Done when:* feature tests pass for each flow.
- [ ] **T1.3 — `Theme` enum and `CreateStorePage` action**. *Done when:* test shows creating a `service` page seeds the 8 service section rows and a `store` page seeds 7 store rows.
- [ ] **T1.4 — Onboarding wizard** (business → theme → brand → review) with `EnsureOnboardingComplete` middleware. *Done when:* a new user is redirected into the wizard and ends with a draft page.
- [ ] **T1.5 — Dashboard skeleton page** showing the seller's page name and theme. *Done when:* test asserts guest is redirected and seller sees own page only.

### Phase 2 — Tenancy and routing

- [ ] **T2.1 — Route groups**: storefront domain group registered **before** apex routes in `bootstrap/app.php`. *Done when:* `demo.mypage.test` and `mypage.test` hit different routes locally.
- [ ] **T2.2 — `ResolveStorePage` middleware with cache and invalidation observer**. *Done when:* unknown slug 404s, draft 404s, published 200s, signed preview URL shows draft.
- [ ] **T2.3 — `BelongsToSeller` trait + architecture test**. *Done when:* test fails if a model with `seller_id` lacks the trait; cross-seller leak test passes.
- [ ] **T2.4 — Policies for StorePage and base policy pattern**. *Done when:* a seller gets 403 on another seller's page edit.

### Phase 3 — Theme engine

- [ ] **T3.1 — Shared layout, brand CSS variables, font pairs (self-hosted), OG/SEO tags**. *Done when:* changing `brand_color` visibly changes buttons on the storefront.
- [ ] **T3.2 — `StorefrontController` and dynamic section rendering** for both themes with placeholder section components. *Done when:* smoke tests for both themes pass with all sections on and with some off.
- [ ] **T3.3 — Page HTML cache with tags and flush observers**. *Done when:* second request hits cache; editing the page busts it.

### Phase 4 — Dashboard shell

- [ ] **T4.1 — App layout with theme-aware navigation (mobile bottom bar + desktop sidebar)**. *Done when:* nav differs between store and service sellers.
- [ ] **T4.2 — UI component kit** (`button, input, select, textarea, modal, drawer, table, badge, toast, image-upload, empty-state`). *Done when:* a `/dev/ui` route (local only) shows each component.
- [ ] **T4.3 — Page editor tabs** (Branding, Sections, SEO, Contact, Publish) + signed preview iframe. *Done when:* edits persist and preview refreshes.
- [ ] **T4.4 — `PublishStorePage` / `UnpublishStorePage` with required-section validation**. *Done when:* publish fails for an empty page with a clear message and succeeds when content exists.

### Phase 5 — Catalog and Store theme

- [ ] **T5.1 — Catalog migrations/models/factories/policies**. *Done when:* tenancy tests extended to Product and Category.
- [ ] **T5.2 — Product CRUD with multi-image upload, categories, variants (single option group), queued WebP conversion**. *Done when:* creating a product with 3 images stores files and a queued job processes them.
- [ ] **T5.3 — `AdjustStock` with inventory logs and locking**. *Done when:* test proves stock cannot go negative and each change writes a log.
- [ ] **T5.4 — CSV import job with error report**. *Done when:* a sample CSV of 50 rows imports and bad rows are listed.
- [ ] **T5.5 — Store theme sections and product page** (hero, featured, categories, grid with filter/search/pagination, product detail, reviews placeholder, about, contact). *Done when:* demo store renders with seeded products on a 375px viewport without horizontal scroll.
- [ ] **T5.6 — Alpine cart store + cart drawer**. *Done when:* cart persists across reload and shows correct count and total.
- [ ] **T5.7 — `GenerateWaMeLink` + pending order creation before redirect**. *Done when:* clicking "Order on WhatsApp" creates a pending order and opens a correct `wa.me` URL.

### Phase 6 — Booking and Service theme

- [ ] **T6.1 — Booking migrations/models/factories/policies** (services, staff, working\_hours, time\_off, bookings, status histories). *Done when:* `migrate:fresh` passes.
- [ ] **T6.2 — Services, Staff and Availability dashboard screens**. *Done when:* a seller can define Mon–Sat hours and time off.
- [ ] **T6.3 — `AvailabilityService` with full unit tests**. *Done when:* tests cover buffers, overlaps, time off, minimum notice, expired holds, Lagos timezone edge cases.
- [ ] **T6.4 — `CreateBooking` with row lock and hold expiry; `ExpirePendingBookings` command + schedule**. *Done when:* a concurrency test creates exactly one booking for the same slot.
- [ ] **T6.5 — `GET /availability` JSON endpoint (throttled) + booking widget (Alpine)**. *Done when:* picking date shows slots and submitting creates a pending booking.
- [ ] **T6.6 — Service theme sections and pages** (hero, services, team, booking, gallery, reviews, about/hours, contact). *Done when:* demo service page renders on mobile and desktop.
- [ ] **T6.7 — Bookings inbox + status actions + `.ics` download on confirmation page**. *Done when:* seller can confirm/cancel/complete and buyer gets an `.ics` file.

### Phase 7 — Payments

- [ ] **T7.1 — Payment migrations/models** (transactions, webhook\_events, wallets, wallet\_ledger, bank\_accounts, payouts) and morph map. *Done when:* migrations pass; ledger model blocks update/delete.
- [ ] **T7.2 — `PaymentGatewayInterface`, `PaystackGateway`, `FlutterwaveGateway`, binding by config**. *Done when:* gateway tests with HTTP fakes pass for init/verify.
- [ ] **T7.3 — `InitializePayment`, callback page, `ConfirmTransaction` (idempotent)**. *Done when:* paying twice or confirming twice credits the wallet once.
- [ ] **T7.4 — Webhook endpoints with signature check, event dedupe, queued processing, CSRF exclusion**. *Done when:* tests cover valid, invalid signature, replay.
- [ ] **T7.5 — `CommissionCalculator` + `LedgerService` (double entry)**. *Done when:* ledger sums to zero in tests; commission rounding tested.
- [ ] **T7.6 — Store checkout** (re-price server-side, create order, pay) **and Service checkout** (deposit/full payment). *Done when:* tampered cart prices are ignored; booking becomes `confirmed` after payment.
- [ ] **T7.7 — Bank account verification and payout request/processing**. *Done when:* payout success and failure paths pass tests with fakes.
- [ ] **T7.8 — Sandbox end-to-end run** (document in `docs/PROGRESS.md`). *Done when:* a sandbox payment completes for both themes.

### Phase 8 — Inbox and customers

- [ ] **T8.1 — Order models, enums, `UpdateOrderStatus` with transition map and history**. *Done when:* illegal transitions throw; history rows written.
- [ ] **T8.2 — Booking status enum and `UpdateBookingStatus`**. *Done when:* same tests as orders.
- [ ] **T8.3 — Customers: `UpsertCustomer`, customer list/detail, notes**. *Done when:* repeat buyers merge by phone and totals update.
- [ ] **T8.4 — Unified inbox UI (orders or bookings) with filters, drawer, new-item badge polling**. *Done when:* new paid order appears within the polling interval.
- [ ] **T8.5 — Email notifications** (seller + buyer) queued. *Done when:* `Notification::fake()` tests pass.
- [ ] **T8.6 — Abandoned cart capture and reminder (store)**. *Done when:* scheduled command finds carts older than the threshold and queues reminders once.

### Phase 9 — Billing and escrow

- [ ] **T9.1 — Plans, subscriptions, `SubscriptionManager`, `EnsureSubscriptionValid` with grace period, `ExpireSubscriptions`**. *Done when:* expired seller can still serve public page but cannot edit.
- [ ] **T9.2 — Feature flag helper and DB flags**. *Done when:* escrow code paths are inert when flag is off.
- [ ] **T9.3 — Escrow accounts, release, auto-release job, dispute freeze**. *Done when:* tests cover release, auto-release, dispute, refund.
- [ ] **T9.4 — Badge applications + admin approval**. *Done when:* approved page shows the badge in both themes.

### Phase 10 — Admin

- [ ] **T10.1 — Admin guard, layout, seed admin user**. *Done when:* non-admins get 403/redirect.
- [ ] **T10.2 — Moderation, transactions, payouts approval, disputes, badges, flags, metrics screens**. *Done when:* each action writes an activity log row.

### Phase 11 — Messaging

- [ ] **T11.1 — WhatsApp client, encrypted connection storage, webhook handshake and signature check**. *Done when:* tests with HTTP fakes pass.
- [ ] **T11.2 — Transactional messages** (order status, booking confirmation and reminders at 24h/2h). *Done when:* reminder command sends once per booking per window.
- [ ] **T11.3 — Broadcast composer with cost estimate, opt-in/out enforcement, chunked jobs**. *Done when:* opted-out customers never receive sends (test).

### Phase 12–13 — Directory, SEO, custom domains

- [ ] **T12.1 — Directory listing index + pages + filters**. *Done when:* published pages appear, drafts and suspended pages never do.
- [ ] **T12.2 — Sitemap command, robots.txt, JSON-LD in both themes**. *Done when:* sitemap validates and JSON-LD parses.
- [ ] **T13.1 — Domain bindings, DNS verification job, resolver fallback**. *Done when:* a verified custom host resolves to the right page and apex hosts are unaffected.

### Phase 15 — Hardening

- [ ] **T15.1 — Security checklist from Part 5.3 executed and ticked in `docs/SECURITY_CHECKLIST.md`**.
- [ ] **T15.2 — `DemoSellerSeeder`** (one store page, one service page). *Done when:* `migrate:fresh --seed` yields both demo pages that render.
- [ ] **T15.3 — Performance pass**: lazy-loading guard in non-prod, image sizes, Lighthouse notes recorded.
- [ ] **T15.4 — Deploy docs**: `docs/DEPLOY.md` with Forge script, env vars, scheduler, Horizon, backups.

## 6.6 Default decisions (use these instead of asking)

| Question | Default |
| --- | --- |
| Default gateway | Paystack; Flutterwave implemented behind the same interface |
| One page per seller in v1? | Yes (schema allows more) |
| Currency | NGN only, kobo integers |
| Timezone | Store in UTC, display `Africa/Lagos` |
| Auth method | Email + password; phone number collected and normalised |
| Livewire usage | Avoid; use Alpine + small JSON endpoints unless the inbox polling becomes awkward |
| Image storage | `public` disk locally; S3-compatible disk in production, WebP conversion on queue |
| Slot step | 30 minutes; minimum notice 60 minutes; hold 15 minutes |
| Commission | 150 bps unless env says otherwise |
| Escrow auto-release | 7 days |
| Subscription grace period | 3 days |
| Reviews in v1 | Simple moderated reviews table; display only approved |
| Email provider | Laravel mail with SMTP/Resend/Postmark via env |
| Guest checkout | Yes, no buyer accounts in v1 |
| Any unlisted feature | Do not build; add to `docs/BACKLOG.md` |

## 6.7 Route map (reference)

**Apex domain (`mypage.ng`)**

```
GET  /                         marketing home
GET  /pricing, /directory      marketing + directory
GET|POST /register, /login, /logout, /forgot-password, /reset-password
GET  /email/verify ...         verification routes
GET|POST /onboarding/{step}
GET  /dashboard
RESOURCE /dashboard/products, /dashboard/categories          (store theme)
RESOURCE /dashboard/services, /dashboard/staff               (service theme)
GET|POST /dashboard/availability
GET  /dashboard/orders, /dashboard/orders/{order}            (store theme)
GET  /dashboard/bookings, /dashboard/bookings/{booking}      (service theme)
GET  /dashboard/customers, /dashboard/payments, /dashboard/payouts
GET|PUT /dashboard/page/{tab}, POST /dashboard/page/publish
GET  /dashboard/billing, /dashboard/settings
GET  /admin/...                admin panel (separate guard)
POST /webhooks/paystack | /webhooks/flutterwave | /webhooks/whatsapp
GET  /checkout/callback/{reference}
```

**Storefront domain (`{slug}.mypage.ng`)**

```
GET  /                         StorefrontController@show
GET  /products/{product:slug}  (store)         GET /services/{service:slug}  (service)
GET  /cart, POST /checkout     (store)
GET  /availability             JSON slots (service)
POST /bookings                 create booking (service)
GET  /orders/{reference}, /bookings/{reference}   buyer status pages (signed)
GET  /preview                  signed draft preview
```

## 6.8 Master env variable list

```
APP_NAME, APP_ENV, APP_URL, APP_DOMAIN, SESSION_DOMAIN, SANCTUM_STATEFUL_DOMAINS
DB_*, REDIS_*, QUEUE_CONNECTION=redis, CACHE_STORE=redis, SESSION_DRIVER=redis
FILESYSTEM_DISK, AWS_* (S3-compatible)
MAIL_*
PAYMENT_GATEWAY=paystack
PAYSTACK_SECRET, PAYSTACK_PUBLIC, FLUTTERWAVE_SECRET, FLUTTERWAVE_PUBLIC, FLUTTERWAVE_ENCRYPTION_KEY
WHATSAPP_CLOUD_TOKEN, WHATSAPP_BUSINESS_ID, WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_WEBHOOK_VERIFY_TOKEN, WHATSAPP_APP_SECRET
PLATFORM_COMMISSION_BPS=150, ESCROW_AUTO_RELEASE_DAYS=7
FEATURE_ESCROW, FEATURE_BROADCASTS, FEATURE_DIRECTORY, FEATURE_CUSTOM_DOMAINS
SENTRY_LARAVEL_DSN
```

## 6.9 Known pitfalls the agent must avoid

1. **Route order:** routes without a domain match any host. Register the `{slug}.` group first or the apex routes will swallow storefront requests.
2. **CSRF on webhooks:** exclude `webhooks/*` from CSRF, but always verify signatures.
3. **Session domain:** `.mypage.ng` shares cookies across subdomains; keep buyer pages free of seller session data and never render dashboard controls on storefronts.
4. **Cache keys without tenant:** every cache key must include the store page id or slug.
5. **Queue context:** global scopes depend on auth/request; in jobs always pass IDs and set the tenant explicitly.
6. **Float math:** convert naira input to kobo with `Money::toKobo` immediately; never store or compute in floats.
7. **Trusting the client:** prices, totals, slot validity and stock are always recomputed on the server.
8. **Timezones:** compare in UTC; format in Lagos only at the edge (views, emails).
9. **Unbounded uploads:** validate size and mime, re-encode images, store badge documents on a private disk.
10. **Editing shipped migrations:** never; add new ones.

## 6.10 Progress tracking files the agent must maintain

- `docs/PROGRESS.md` — phase-by-phase log: done, in progress, next.
- `docs/OPEN_QUESTIONS.md` — ambiguities encountered and the default chosen.
- `docs/BACKLOG.md` — ideas outside scope.
- `docs/DECISIONS.md` — short ADR-style notes for any deviation from this plan, with reason.

## 6.11 Per-phase prompt template (for resuming work)

```text
Continue building MYPAGE.NG. Read AGENTS.md and docs/PROGRESS.md.
Current phase: <Pn — name>. Execute the unchecked tasks for this phase from the plan (section 6.5)
in order. After each task: run verification commands, commit, tick it off in docs/PROGRESS.md.
Stop at the phase exit criteria and summarise what was built, what tests were added, and any
open questions. Do not start the next phase.
```

---

# Part 7 — Theme Blade Code (starting skeletons)

All components are anonymous Blade components under `resources/views/components/themes/...`. They read brand colours from the CSS variables set in the shared layout (`bg-brand`, `text-brand-contrast`). Escape everything with `{{ }}`.

## 7.1 File manifest

```
resources/views/components/themes/
├── shared/
│   ├── layout.blade.php          (see Part 2, P3)
│   ├── image.blade.php
│   ├── price.blade.php
│   ├── rating-stars.blade.php
│   ├── verified-badge.blade.php
│   ├── whatsapp-button.blade.php
│   ├── header.blade.php
│   └── footer.blade.php
├── store/sections/  hero, featured, categories, products, reviews, about, contact
├── store/           product-card, cart-drawer
└── service/sections/ hero, services, team, booking, gallery, reviews, about, contact
```

Missing section views must be skipped, never cause a 500: in `page.blade.php` wrap with `@if (View::exists('components.themes.'.$theme.'.sections.'.$section->key))`.

## 7.2 Shared components

**`shared/image.blade.php`** (lazy, fixed ratio, no layout shift)

```blade
@props(['path' => null, 'alt' => '', 'ratio' => 'aspect-[4/5]', 'eager' => false])
<div {{ $attributes->class([$ratio, 'overflow-hidden bg-slate-100']) }}>
  @if ($path)
    <img src="{{ Storage::url($path) }}" alt="{{ $alt }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async"
         class="h-full w-full object-cover">
  @endif
</div>
```

**`shared/price.blade.php`**

```blade
@props(['kobo', 'compare' => null])
<span class="font-semibold">{{ \App\Shared\Support\Money::format($kobo) }}</span>
@if ($compare && $compare > $kobo)
  <span class="ml-1 text-sm text-slate-500 line-through">{{ \App\Shared\Support\Money::format($compare) }}</span>
@endif
```

**`shared/whatsapp-button.blade.php`**

```blade
@props(['page', 'text' => null])
@if ($page->whatsapp_number)
  <a href="https://wa.me/{{ ltrim($page->whatsapp_number, '+') }}?text={{ rawurlencode($text ?? 'Hello '.$page->name) }}"
     class="fixed bottom-4 right-4 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-green-500 text-white shadow-lg"
     style="bottom: calc(1rem + env(safe-area-inset-bottom, 0px));"
     aria-label="Chat on WhatsApp" target="_blank" rel="noopener">
    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2z"/></svg>
  </a>
@endif
```

**`shared/header.blade.php`** (shows the cart only for the store theme)

```blade
@props(['page'])
<header class="sticky top-0 z-30 border-b bg-white/95 backdrop-blur">
  <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
    <a href="/" class="flex items-center gap-2 font-[var(--font-heading)] text-lg font-bold">
      @if ($page->logo_path)<img src="{{ Storage::url($page->logo_path) }}" alt="" class="h-8 w-8 rounded-full object-cover">@endif
      {{ $page->name }}
      @if ($page->verified_at)<x-themes.shared.verified-badge />@endif
    </a>
    @if ($page->theme->value === 'store')
      <button type="button" @click="$dispatch('open-cart')" class="relative p-2" aria-label="Open cart">
        🛒<span x-data x-show="$store.cart.count > 0" x-text="$store.cart.count"
          class="absolute -right-1 -top-1 rounded-full bg-brand px-1.5 text-xs text-brand-contrast"></span>
      </button>
    @else
      <a href="#booking" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast">Book now</a>
    @endif
  </div>
</header>
```

## 7.3 Store theme

**`store/sections/hero.blade.php`**

```blade
@props(['page', 'section'])
<section class="relative">
  <x-themes.shared.image :path="$page->cover_path" ratio="aspect-[16/9] md:aspect-[21/9]" :eager="true" :alt="$page->name" />
  <div class="absolute inset-0 flex flex-col items-start justify-end gap-3 bg-gradient-to-t from-black/60 to-transparent p-6 text-white">
    <h1 class="font-[var(--font-heading)] text-3xl font-bold md:text-5xl">{{ $page->name }}</h1>
    @if ($page->tagline)<p class="max-w-xl text-base md:text-lg">{{ $page->tagline }}</p>@endif
    <div class="flex gap-3">
      <a href="#products" class="rounded-lg bg-brand px-5 py-3 font-semibold text-brand-contrast">Shop now</a>
      <a href="https://wa.me/{{ ltrim($page->whatsapp_number, '+') }}" class="rounded-lg border border-white px-5 py-3 font-semibold">WhatsApp</a>
    </div>
  </div>
</section>
```

**`store/product-card.blade.php`**

```blade
@props(['product'])
<article class="group">
  <a href="{{ route('storefront.product', $product->slug) }}">
    <x-themes.shared.image :path="$product->images->first()?->path" :alt="$product->name" class="rounded-xl" />
    <h3 class="mt-2 line-clamp-2 text-sm font-medium">{{ $product->name }}</h3>
  </a>
  <div class="mt-1 flex items-center justify-between">
    <x-themes.shared.price :kobo="$product->price_kobo" :compare="$product->compare_at_kobo" />
    <button type="button"
      x-data
      @click="$store.cart.add({ id: {{ $product->id }}, name: @js($product->name), price_kobo: {{ $product->price_kobo }}, variant: null })"
      class="rounded-lg bg-brand px-3 py-2 text-sm text-brand-contrast"
      @disabled($product->track_stock && $product->stock_qty < 1)>
      {{ $product->track_stock && $product->stock_qty < 1 ? 'Sold out' : 'Add' }}
    </button>
  </div>
</article>
```

**`store/sections/products.blade.php`** (server-side filter via query string)

```blade
@props(['page', 'section', 'products', 'categories' => collect()])
<section id="products" class="mx-auto max-w-6xl px-4 py-10">
  <h2 class="mb-4 font-[var(--font-heading)] text-2xl font-bold">Products</h2>
  <form method="get" class="mb-4 flex gap-2">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search" class="w-full rounded-lg border px-3 py-2">
    <select name="category" class="rounded-lg border px-3 py-2" onchange="this.form.submit()">
      <option value="">All</option>
      @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->name }}</option>@endforeach
    </select>
  </form>
  <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
    @forelse ($products as $product)
      <x-themes.store.product-card :product="$product" />
    @empty
      <p class="col-span-full text-slate-500">No products yet.</p>
    @endforelse
  </div>
  @if (method_exists($products, 'links'))<div class="mt-6">{{ $products->links() }}</div>@endif
</section>
```

**`store/cart-drawer.blade.php`** (included once in the store `page.blade.php`)

```blade
<div x-data="{ open: false, busy: false }" @open-cart.window="open = true">
  <div x-show="open" x-cloak class="fixed inset-0 z-50 bg-black/40" @click="open=false"></div>
  <aside x-show="open" x-cloak x-transition class="fixed inset-y-0 right-0 z-50 flex w-full max-w-md flex-col bg-white p-4">
    <div class="flex items-center justify-between"><h2 class="text-lg font-bold">Your cart</h2><button @click="open=false" aria-label="Close">✕</button></div>
    <ul class="mt-4 flex-1 divide-y overflow-y-auto">
      <template x-for="(item, i) in $store.cart.items" :key="i">
        <li class="flex items-center justify-between py-3">
          <div><p x-text="item.name" class="text-sm font-medium"></p>
               <p class="text-xs text-slate-500" x-text="'₦' + (item.price_kobo/100).toLocaleString()"></p></div>
          <div class="flex items-center gap-2">
            <button @click="item.qty = Math.max(1, item.qty-1); $store.cart.save()">−</button>
            <span x-text="item.qty"></span>
            <button @click="item.qty++; $store.cart.save()">+</button>
            <button @click="$store.cart.remove(i)" class="text-red-600" aria-label="Remove">🗑</button>
          </div>
        </li>
      </template>
    </ul>
    <div class="border-t pt-3">
      <p class="flex justify-between font-semibold"><span>Subtotal</span><span x-text="'₦' + ($store.cart.total/100).toLocaleString()"></span></p>
      <form method="post" action="{{ route('storefront.checkout.start') }}" class="mt-3 grid gap-2">
        @csrf
        <input type="hidden" name="cart" :value="JSON.stringify($store.cart.items.map(i => ({id: i.id, variant: i.variant, qty: i.qty})))">
        <button class="rounded-lg bg-brand py-3 font-semibold text-brand-contrast" :disabled="$store.cart.count === 0">Checkout</button>
        <button type="submit" name="channel" value="whatsapp" class="rounded-lg border py-3 font-semibold">Order via WhatsApp</button>
      </form>
    </div>
  </aside>
</div>
```

The server accepts only `id`, `variant`, `qty` from the cart payload, loads prices from the database, and rejects unknown IDs.

**Product detail (`store/product-show.blade.php`)** — gallery (Alpine `x-data="{ active: 0 }"` with thumbnails), title, price, variant `<select>` bound to Alpine state, quantity stepper, and a mobile sticky bar:

```blade
<div class="fixed inset-x-0 bottom-0 z-30 flex gap-2 border-t bg-white p-3 md:static md:border-0 md:p-0"
     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));">
  <button @click="$store.cart.add({ id: {{ $product->id }}, name: @js($product->name), price_kobo: price, variant: variant }, qty)"
          class="flex-1 rounded-lg bg-brand py-3 font-semibold text-brand-contrast">Add to cart</button>
  <a href="{{ $waLink }}" class="rounded-lg border px-4 py-3">WhatsApp</a>
</div>
```

## 7.4 Service theme

**`service/sections/hero.blade.php`** — same structure as the store hero with the primary button set to `<a href="#booking">Book now</a>` and a rating summary line (`<x-themes.shared.rating-stars :value="$page->rating_avg" /> ({{ $page->rating_count }})`).

**`service/sections/services.blade.php`**

```blade
@props(['page', 'section', 'services'])
<section id="services" class="mx-auto max-w-4xl px-4 py-10">
  <h2 class="mb-4 font-[var(--font-heading)] text-2xl font-bold">Services</h2>
  <ul class="grid gap-3">
    @foreach ($services as $s)
      <li class="flex items-center justify-between gap-3 rounded-xl border p-4">
        <div>
          <h3 class="font-semibold">{{ $s->name }}</h3>
          <p class="text-sm text-slate-600">{{ $s->duration_min }} min · <x-themes.shared.price :kobo="$s->price_kobo" /></p>
          @if ($s->description)<p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $s->description }}</p>@endif
        </div>
        <a href="{{ route('storefront.service', $s->slug) }}#booking" class="shrink-0 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-brand-contrast">Book</a>
      </li>
    @endforeach
  </ul>
</section>
```

**`service/sections/team.blade.php`** — grid of staff cards: round photo (`x-themes.shared.image` with `rounded-full aspect-square`), name, short bio.

**`service/sections/booking.blade.php`** (embeds the Alpine widget from Part 2, P6; extended here to a 4-step flow)

```blade
@props(['page', 'section', 'services', 'staff'])
<section id="booking" class="mx-auto max-w-2xl px-4 py-10"
  x-data="booking({ services: @js($services->map->only(['id','name','duration_min','price_kobo','deposit_kobo'])), staff: @js($staff->map->only(['id','name'])) })">
  <h2 class="mb-4 font-[var(--font-heading)] text-2xl font-bold">Book an appointment</h2>

  <ol class="mb-4 flex gap-2 text-xs">
    <template x-for="(label, i) in ['Service','Time','Details','Pay']" :key="i">
      <li :class="step >= i+1 ? 'bg-brand text-brand-contrast' : 'bg-slate-100'" class="rounded-full px-3 py-1" x-text="label"></li>
    </template>
  </ol>

  <div x-show="step === 1"><!-- radio list of services, optional staff select ("Any available") --></div>
  <div x-show="step === 2">
    <input type="date" x-model="date" :min="today" @change="loadSlots()" class="w-full rounded-lg border px-3 py-2">
    <div class="mt-3 grid grid-cols-3 gap-2">
      <template x-for="s in slots" :key="s">
        <button type="button" @click="slot = s" x-text="s"
          :class="slot === s ? 'bg-brand text-brand-contrast' : 'border'" class="rounded-lg py-2"></button>
      </template>
    </div>
    <p x-show="date && !slots.length && !loading" class="mt-3 text-sm text-slate-500">No times available on this day.</p>
  </div>
  <form x-show="step >= 3" method="post" action="{{ route('storefront.bookings.store') }}" class="grid gap-3">
    @csrf
    <input type="hidden" name="service_id" :value="serviceId">
    <input type="hidden" name="staff_id" :value="staffId">
    <input type="hidden" name="starts_at" :value="date + 'T' + slot">
    <input name="name" required placeholder="Your name" class="rounded-lg border px-3 py-2" autocomplete="name">
    <input name="phone" required inputmode="tel" placeholder="080..." class="rounded-lg border px-3 py-2" autocomplete="tel">
    <input name="email" type="email" required placeholder="Email (for your receipt)" class="rounded-lg border px-3 py-2" autocomplete="email">
    <textarea name="notes" placeholder="Notes (optional)" class="rounded-lg border px-3 py-2"></textarea>
    <button class="rounded-lg bg-brand py-3 font-semibold text-brand-contrast">Continue to payment</button>
  </form>

  <div class="mt-4 flex justify-between">
    <button type="button" x-show="step > 1" @click="step--">Back</button>
    <button type="button" x-show="step < 3" :disabled="!canNext" @click="step++" class="rounded-lg bg-brand px-4 py-2 text-brand-contrast">Next</button>
  </div>
</section>
```

Register `Alpine.data('booking', cfg => ({ step: 1, serviceId: null, staffId: '', date: '', slot: null, slots: [], loading: false, today: new Date().toISOString().slice(0,10), get canNext() { return this.step === 1 ? !!this.serviceId : !!this.slot; }, async loadSlots() { /* fetch /availability */ } }))` in `app.js`. The server re-validates the slot in `CreateBooking`; the widget is only a convenience.

**`service/sections/about.blade.php`** — bio plus an opening-hours table built from `working_hours` (group by weekday, show "Closed" where none), address, and a plain map link `https://www.google.com/maps/search/?api=1&query={{ urlencode($page->address) }}`.

**`service/booking-confirmation.blade.php`** — reference, service, date/time in Lagos time, status, deposit paid, "Add to calendar" (`.ics` link), "Chat on WhatsApp" with prefilled text, cancellation policy text.

**`.ics` generation** (no package needed):

```php
$ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//mypage.ng//EN\r\nBEGIN:VEVENT\r\n"
  ."UID:{$b->reference}@mypage.ng\r\nDTSTAMP:".now()->utc()->format('Ymd\THis\Z')."\r\n"
  ."DTSTART:".$b->starts_at->utc()->format('Ymd\THis\Z')."\r\nDTEND:".$b->ends_at->utc()->format('Ymd\THis\Z')."\r\n"
  ."SUMMARY:".addcslashes($b->service->name.' at '.$page->name, ',;\\')."\r\nEND:VEVENT\r\nEND:VCALENDAR";
return response($ics, 200, ['Content-Type' => 'text/calendar', 'Content-Disposition' => 'attachment; filename="booking.ics"']);
```

## 7.5 Dashboard inbox component

**`components/inbox/table.blade.php`**

```blade
@props(['rows', 'columns', 'statuses' => []])
<div x-data="{ drawer: null }">
  <div class="mb-3 flex gap-2 overflow-x-auto">
    @foreach ($statuses as $key => $label)
      <a href="?status={{ $key }}" class="whitespace-nowrap rounded-full border px-3 py-1 text-sm {{ request('status') === $key ? 'bg-brand text-brand-contrast' : '' }}">{{ $label }}</a>
    @endforeach
  </div>
  <div class="overflow-x-auto rounded-xl border">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-left"><tr>@foreach ($columns as $c)<th class="px-3 py-2">{{ $c['label'] }}</th>@endforeach</tr></thead>
      <tbody class="divide-y">
        @forelse ($rows as $row)
          <tr class="cursor-pointer hover:bg-slate-50" @click="drawer = {{ $row->id }}">
            @foreach ($columns as $c)<td class="px-3 py-2">{{ $c['value']($row) }}</td>@endforeach
          </tr>
        @empty
          <tr><td colspan="{{ count($columns) }}" class="px-3 py-8 text-center text-slate-500">Nothing here yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  {{ $slot }}   {{-- drawers: <x-ui.drawer x-show="drawer === {{ $row->id }}"> --}}
</div>
```

---

# Part 8 — Pest Test Skeletons (money, tenancy, booking)

## 8.1 Test setup

**`tests/Pest.php`**

```php
uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Feature', 'Unit');

function storeHost(string $slug = 'demo'): string { return "http://{$slug}.".config('mypage.domain'); }

function publishedPage(string $theme = 'store', array $attrs = []): StorePage {
    return StorePage::factory()->create(['theme' => $theme, 'status' => 'published', 'slug' => 'demo'] + $attrs);
}
```

In `phpunit.xml` set `DB_CONNECTION=mysql` with a dedicated test database (or SQLite only if every migration is SQLite-compatible), `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`. Booking concurrency and `lockForUpdate` behaviour must be tested on MySQL.

## 8.2 Tenancy

```php
it('hides other sellers products on the dashboard and storefront', function () {
    $pageA = publishedPage('store', ['slug' => 'a']);
    $pageB = publishedPage('store', ['slug' => 'b']);
    Product::factory()->for($pageB->seller)->create(['store_page_id' => $pageB->id, 'name' => 'Secret']);

    $this->actingAs($pageA->seller)->get('/dashboard/products')->assertDontSee('Secret');
    $this->get(storeHost('a').'/')->assertDontSee('Secret');
});

it('forbids editing another sellers page', function () {
    [$a, $b] = [publishedPage('store', ['slug' => 'a']), publishedPage('store', ['slug' => 'b'])];
    $this->actingAs($a->seller)->put("/dashboard/page/branding", ['store_page_id' => $b->id, 'name' => 'Hacked'])->assertForbidden();
});

it('requires BelongsToSeller on every model with a seller_id column', function () {
    foreach (modelsWithColumn('seller_id') as $class) {
        expect(class_uses_recursive($class))->toContain(BelongsToSeller::class);
    }
});
```

Implement `modelsWithColumn()` by scanning `app/Domains/*/Models`, instantiating each, and checking `Schema::hasColumn($model->getTable(), 'seller_id')`.

## 8.3 Money helpers and commission

```php
it('converts naira to kobo safely', function (string $in, int $kobo) {
    expect(Money::toKobo($in))->toBe($kobo);
})->with([['12,500', 1250000], ['0.1', 10], ['1999.99', 199999]]);

it('calculates commission with integer math', function () {
    config(['mypage.commission_bps' => 150]);
    expect(CommissionCalculator::for(1999))->toBe(29);      // intdiv(1999*150, 10000)
    expect(CommissionCalculator::for(1000000))->toBe(15000);
});
```

## 8.4 Webhook, confirmation and ledger

```php
beforeEach(function () {
    config(['services.paystack.secret' => 'sk_test_x']);
    $this->tx = Transaction::factory()->create(['reference' => 'MPTEST1', 'amount_kobo' => 500000, 'status' => 'pending', 'gateway' => 'paystack']);
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => ['status' => 'success', 'amount' => 500000, 'paid_at' => now()->toIso8601String()]])]);
});

function paystackPost($test, array $body, ?string $secret = 'sk_test_x') {
    $raw = json_encode($body);
    return $test->call('POST', '/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $raw, $secret),
    ], $raw);
}

it('rejects a bad signature', function () {
    paystackPost($this, ['event' => 'charge.success', 'data' => ['id' => 1, 'reference' => 'MPTEST1']], 'wrong')->assertStatus(401);
    expect(Transaction::first()->status)->toBe('pending');
});

it('confirms once even when the webhook is replayed', function () {
    $body = ['event' => 'charge.success', 'data' => ['id' => 99, 'reference' => 'MPTEST1']];
    paystackPost($this, $body)->assertNoContent();
    paystackPost($this, $body)->assertNoContent();

    expect(WebhookEvent::count())->toBe(1);
    expect(Transaction::first()->status)->toBe('paid');
    expect(WalletLedger::where('transaction_id', $this->tx->id)->count())->toBe(2);   // double entry, not 4
});

it('fails the transaction when the paid amount does not match', function () {
    Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['data' => ['status' => 'success', 'amount' => 100]])]);
    app(ConfirmTransaction::class)->handle('MPTEST1');
    expect(Transaction::first()->status)->toBe('failed');
});

it('keeps the ledger balanced', function () {
    app(ConfirmTransaction::class)->handle('MPTEST1');
    $credits = WalletLedger::where('direction', 'credit')->sum('amount_kobo');
    $debits  = WalletLedger::where('direction', 'debit')->sum('amount_kobo');
    expect($credits)->toBe($debits);
});

it('does not allow ledger rows to be updated or deleted', function () {
    app(ConfirmTransaction::class)->handle('MPTEST1');
    expect(fn () => WalletLedger::first()->update(['amount_kobo' => 1]))->toThrow(LogicException::class);
});
```

## 8.5 Checkout re-pricing

```php
it('ignores client supplied prices', function () {
    $page = publishedPage('store');
    $p = Product::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'price_kobo' => 500000, 'status' => 'active']);

    $this->post(storeHost().'/checkout', [
        'cart' => json_encode([['id' => $p->id, 'qty' => 2, 'price_kobo' => 1]]),
        'name' => 'Ada', 'phone' => '08031234567', 'email' => 'ada@example.com',
    ])->assertRedirect();

    expect(Order::first()->total_kobo)->toBe(1000000);
});

it('rejects out-of-stock items', function () { /* track_stock true, stock_qty 0 => validation error, no order created */ });
```

## 8.6 Booking availability and double-booking

```php
it('offers no slot that overlaps a confirmed booking', function () {
    $page = publishedPage('service');
    $service = Service::factory()->create(['store_page_id' => $page->id, 'seller_id' => $page->seller_id, 'duration_min' => 60, 'buffer_min' => 0]);
    WorkingHour::factory()->create(['store_page_id' => $page->id, 'weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);
    $monday = CarbonImmutable::parse('next monday', 'Africa/Lagos');

    Booking::factory()->create(['service_id' => $service->id, 'store_page_id' => $page->id, 'seller_id' => $page->seller_id,
        'starts_at' => $monday->setTime(10, 0)->utc(), 'ends_at' => $monday->setTime(11, 0)->utc(), 'status' => 'confirmed']);

    $slots = app(AvailabilityService::class)->slotsFor($service, $monday)->map->format('H:i');
    expect($slots)->not->toContain('10:00')->not->toContain('10:30')->toContain('09:00')->toContain('11:00');
});

it('rejects a second booking for the same slot', function () {
    // arrange service + hours as above
    $start = CarbonImmutable::parse('next monday 10:00', 'Africa/Lagos');
    $buyer = ['name' => 'Ada', 'phone' => '08031234567', 'email' => 'a@b.co'];
    app(CreateBooking::class)->handle($service, $start, null, $buyer);
    expect(fn () => app(CreateBooking::class)->handle($service, $start, null, $buyer))->toThrow(SlotUnavailable::class);
});

it('frees a slot when a pending hold expires', function () {
    // create pending_payment booking with expires_at in the past => slot offered again
});
```

For a real parallel test, add a script (outside Pest) that spawns two `php artisan tinker`/HTTP processes hitting the same slot and asserts exactly one `201`; run it once per release candidate on staging.

## 8.7 Status transitions

```php
it('only allows legal order transitions', function (string $from, string $to, bool $ok) {
    $order = Order::factory()->create(['status' => $from]);
    $run = fn () => app(UpdateOrderStatus::class)->handle($order, OrderStatus::from($to));
    $ok ? expect($run)->not->toThrow(InvalidTransition::class) : expect($run)->toThrow(InvalidTransition::class);
})->with([
    ['pending', 'paid', true], ['paid', 'processing', true], ['processing', 'shipped', true],
    ['shipped', 'delivered', true], ['delivered', 'pending', false], ['cancelled', 'paid', false],
]);

it('writes a history row for every change', function () {
    $order = Order::factory()->create(['status' => 'pending']);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Paid);
    expect($order->history()->count())->toBe(1);
});
```

## 8.8 Escrow, payouts, subscriptions

- Escrow: `ReleaseEscrow` credits the seller wallet once; a `disputed` escrow is skipped by `ReleaseAutoEscrow`; a refund path calls the gateway `refund()` (fake it) and marks the transaction `refunded`.
- Payout: success debits wallet and marks `paid`; gateway failure restores balance with a compensating ledger pair; amounts above balance throw.
- Subscription: expired seller gets a redirect on dashboard writes but `GET storeHost()` still returns 200.

## 8.9 Theme smoke tests

```php
it('renders both themes with every section toggled', function (string $theme) {
    $page = publishedPage($theme);
    foreach (config("themes.{$theme}.sections") as $i => $key) {
        $page->sections()->create(['key' => $key, 'position' => $i, 'is_enabled' => true]);
    }
    $this->get(storeHost())->assertOk()->assertSee($page->name);

    $page->sections()->update(['is_enabled' => false]);
    $this->get(storeHost())->assertOk();     // must not 500 with zero sections
})->with(['store', 'service']);
```

## 8.10 What "all green" means for the agent

Before tagging a phase, `php artisan test` must pass with: all tests above implemented for that phase, no skipped money-path tests, and no test depending on the order of other tests. Money-path coverage target is 100% of branches in `Payments`, `Booking\Actions` and `Orders\Actions` (run `php artisan test --coverage --min=90` overall and review the money directories manually).

---

# Part 9 — REVISION: Two Fixed Full-Website Themes (colour is the only design setting)

## 9.1 What changed and what it overrides

**New rule:** MYPAGE.NG has exactly two themes, **Store** (`store`) and **Business** (`service`). Each is a *complete website* with many pages, a fixed layout, fixed typography and fixed components. A seller can change **only the brand colour** of the design. Everything else a seller edits is *content* (text, images, products/services, contact details, FAQs, policies).

| Earlier idea (Parts 1–8) | Now |
| --- | --- |
| One page of toggleable, reorderable sections | A multi-page website per theme (page maps in 9.4) |
| `store_sections` table, Sections tab in the page editor | **Not built.** Blocks inside a page auto-hide when they have no content |
| Font pairs and layout options | **Not built.** One fixed font per theme |
| `font_pair` column, `settings` theme options | **Not built.** Only `brand_color` |
| Section registry in `config/themes.php` | Page registry (9.4) |
| Tasks T1.3 (section seeding), T3.1–T3.3, T4.3, T4.4 (required sections), T5.5, T6.6 | Replaced by the tasks in 9.12 |
| Part 7 section components | Reused as partials inside the new pages |
| Part 8.9 theme smoke test | Replaced by the page tests in 9.13 |

**Add these rules to `AGENTS.md`:**

```markdown
## Theme rules (override anything else)
- Exactly two themes: `store` and `service` (labelled "Store" and "Business").
- Each theme is a fixed multi-page website. Do NOT build a page builder, section toggles,
  section reordering, font choices, layout choices, or a third theme.
- The ONLY design setting is `store_pages.brand_color`. All other differences between two
  sellers on the same theme come from their content.
- Blocks inside a page hide automatically when they have no content (no empty headings).
- Do not create the `store_sections` table or a `font_pair` column.
```

## 9.2 Theme identities (what makes the two feel different)

|  | Store theme | Business theme |
| --- | --- | --- |
| Mood | Image-led, dense, shopping-focused | Spacious, trust-led, appointment-focused |
| Font (self-hosted, fixed) | Inter | Plus Jakarta Sans |
| Radius | 12px cards, 8px buttons | 16px cards, full-round buttons |
| Primary action | Add to cart / Checkout | Book now |
| Header | Logo, nav, search, cart (sticky) | Logo, nav, **Book now** button (sticky) |
| Home hero | Cover image with headline and Shop now | Large photo split layout with Book now and rating |
| Footer | 4 columns: shop, help, policies, contact | 3 columns: services, info, contact + hours |
| Mobile nav | Slide-over menu + bottom cart bar on product pages | Slide-over menu + sticky Book now bar |

Both themes share the same shared partials (SEO head, WhatsApp button, FAQ accordion, breadcrumbs, contact form), so behaviour is consistent while the look is distinct.

## 9.3 Colour system (the only customisation)

**Input:** one hex colour, `store_pages.brand_color`, chosen from 12 presets or a custom hex.

**Presets:** `#0f766e` teal, `#059669` emerald, `#2563eb` blue, `#4f46e5` indigo, `#7c3aed` violet, `#c026d3` fuchsia, `#e11d48` rose, `#dc2626` red, `#ea580c` orange, `#d97706` amber, `#0f172a` slate, `#16a34a` green.

**Derived tokens (computed once per request, never stored):**

| Token | Purpose | How |
| --- | --- | --- |
| `--brand` | Buttons, accents, badges | The chosen colour |
| `--brand-contrast` | Text on `--brand` | White or near-black, whichever has the higher contrast ratio |
| `--brand-ink` | Brand-coloured text and links on white | `--brand` darkened until contrast with white is at least 4.5:1 |
| `--brand-50/100/200` | Tinted backgrounds and borders | `color-mix` of brand with white |
| `--brand-700` | Hover state | `color-mix` of brand with black |

**Validation:** accept `#rgb` or `#rrggbb`, normalise to lowercase `#rrggbb`. Reject near-white colours (relative luminance above 0.85) because buttons would vanish on white; the picker explains why. Very dark or very light accepted colours still work because `--brand-contrast` and `--brand-ink` are computed.

**`app/Shared/Support/Color.php`**

```php
final class Color
{
    public static function normalize(string $hex): ?string
    {
        $hex = strtolower(ltrim(trim($hex), '#'));
        if (preg_match('/^[0-9a-f]{3}$/', $hex)) $hex = preg_replace('/(.)/', '$1$1', $hex);
        return preg_match('/^[0-9a-f]{6}$/', $hex) ? "#{$hex}" : null;
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($c) {
            $c /= 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrast(string $a, string $b): float
    {
        [$l1, $l2] = [self::luminance($a), self::luminance($b)];
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    public static function textOn(string $bg): string
    {
        return self::contrast($bg, '#ffffff') >= self::contrast($bg, '#111827') ? '#ffffff' : '#111827';
    }

    public static function mix(string $hex, string $with, float $ratio): string   // ratio = share of $with
    {
        $a = self::rgb($hex); $b = self::rgb($with);
        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($a[$i] * (1 - $ratio) + $b[$i] * $ratio), [0, 1, 2]));
    }

    public static function ink(string $hex): string   // brand-coloured text on white, AA contrast
    {
        for ($i = 0; $i <= 10 && self::contrast($hex, '#ffffff') < 4.5; $i++) {
            $hex = self::mix($hex, '#000000', 0.12);
        }
        return $hex;
    }

    public static function isTooLight(string $hex): bool { return self::luminance($hex) > 0.85; }

    public static function palette(string $hex): array
    {
        $hex = self::normalize($hex) ?? '#0f766e';
        return ['brand' => $hex, 'contrast' => self::textOn($hex), 'ink' => self::ink($hex)];
    }
}
```

**`resources/css/app.css`** (replaces the earlier `@theme` block; verify `@theme inline` against the Tailwind 4 docs)

```css
@import "tailwindcss";

@theme inline {
  --color-brand: var(--brand);
  --color-brand-contrast: var(--brand-contrast);
  --color-brand-ink: var(--brand-ink);
  --color-brand-50: color-mix(in oklab, var(--brand) 8%, white);
  --color-brand-100: color-mix(in oklab, var(--brand) 14%, white);
  --color-brand-200: color-mix(in oklab, var(--brand) 28%, white);
  --color-brand-700: color-mix(in oklab, var(--brand) 82%, black);
}

@font-face { font-family: 'Inter'; src: url('/fonts/inter-var.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }
@font-face { font-family: 'Plus Jakarta Sans'; src: url('/fonts/plus-jakarta-sans-var.woff2') format('woff2'); font-weight: 200 800; font-display: swap; }

.theme-store   { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; --radius-card: 12px; --radius-btn: 8px; }
.theme-service { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; --radius-card: 16px; --radius-btn: 9999px; }
```

The theme class goes on `<body>`. Components use `bg-brand text-brand-contrast`, `text-brand-ink`, `bg-brand-50`, `border-brand-200`, `hover:bg-brand-700`, and `rounded-[var(--radius-card)]` / `rounded-[var(--radius-btn)]`. Never hardcode a hex colour in a theme view except neutral slate/white/green (WhatsApp).

## 9.4 Page maps

Every page uses the theme's layout (header, footer, WhatsApp button, SEO head). Shared URLs are served by one controller that picks the view by theme; theme-specific URLs sit behind an `EnsureTheme` middleware.

### Store theme pages

| Page | URL | Content (fixed layout) | Indexed |
| --- | --- | --- | --- |
| Home | `/` | Hero, category grid, featured products, new arrivals, trust strip, testimonials, about teaser, WhatsApp CTA band | Yes |
| Shop | `/shop` | Filters (category, price range, in stock), sort, product grid, pagination | Yes |
| Category | `/shop/category/{slug}` | Category title and description, same grid | Yes |
| Product | `/products/{slug}` | Gallery, title, price, variants, quantity, add to cart, WhatsApp order, description tabs, related products | Yes |
| Search | `/search?q=` | Results grid | No |
| Cart | `/cart` | Line items, steppers, subtotal, delivery note, checkout and WhatsApp buttons | No |
| Checkout | `/checkout` | Contact, delivery address, delivery option, order summary, pay button | No |
| Order status | `/orders/{reference}` (signed) | Status timeline, items, payment state, WhatsApp the seller | No |
| Track order | `/track` | Enter reference + phone to open order status | No |
| About | `/about` | Story, image, values, contact CTA | Yes |
| Contact | `/contact` | Form, phone, WhatsApp, email, address, hours, social links | Yes |
| FAQ | `/faq` | Accordion of seller Q&A | Yes |
| Policies | `/policies/{shipping-returns\|privacy\|terms}` | Seller text with starter templates | Yes |
| Not found | any | Friendly 404 with shop link | No |

### Business theme pages

| Page | URL | Content (fixed layout) | Indexed |
| --- | --- | --- | --- |
| Home | `/` | Hero + Book now, trust strip, services preview, how it works (3 steps), team preview, gallery preview, testimonials, FAQ preview, location and hours, CTA band | Yes |
| Services | `/services` | Services listed by category with duration, price, Book buttons | Yes |
| Service | `/services/{slug}` | Image, description, duration, price, deposit info, who performs it, Book button, related services | Yes |
| Book | `/book` (`?service=`) | 4-step booking flow (service, time, details, pay) | No |
| Booking confirmation | `/bookings/{reference}` (signed) | Reference, details, add-to-calendar, WhatsApp, policy | No |
| Manage booking | `/bookings/{reference}/manage` (signed) | Cancel or reschedule within the allowed window | No |
| Team | `/team` | Staff cards with photo, role, bio | Yes |
| Gallery | `/gallery` | Image grid with lightbox | Yes |
| Reviews | `/reviews` | Rating summary and approved reviews | Yes |
| About | `/about` | Story, image, values | Yes |
| Contact | `/contact` | Form, phone, WhatsApp, address, map link, opening hours | Yes |
| FAQ | `/faq` | Accordion | Yes |
| Policies | `/policies/{cancellation\|privacy\|terms}` | Seller text with starter templates | Yes |
| Not found | any | Friendly 404 with Book now | No |

Also for both: `/sitemap.xml`, `/robots.txt`, `/preview` (signed, drafts only).

## 9.5 Home page wireframes (fixed layouts)

**Store home**

```
[ announcement bar (optional text) ]
[ Logo   Home  Shop  Categories▾  About  Contact      🔍  🛒(2) ]
+----------------------------------------------------------+
|  HERO  cover image full width                            |
|  Headline                                                |
|  Subheadline          [ Shop now ] [ WhatsApp ]          |
+----------------------------------------------------------+
|  Shop by category:  [img Cat1] [img Cat2] [img Cat3] ... |
|  Featured products  (4 cols desktop / 2 mobile)          |
|  [card][card][card][card]            View all →          |
|  New arrivals       [card][card][card][card]             |
|  Trust strip: 🚚 Delivery  🔒 Secure pay  💬 WhatsApp help |
|  What customers say: [review][review][review]            |
|  About teaser: image | short story  [Read more]          |
|  CTA band: "Questions? Chat with us"  [ WhatsApp ]       |
+----------------------------------------------------------+
[ footer: Shop | Help | Policies | Contact + "Powered by" ]
```

**Business home**

```
[ Logo   Home  Services  Team  Gallery  About  Contact   [ Book now ] ]
+----------------------------------------------------------+
|  HERO split: text left / photo right                     |
|  Headline, subheadline, ★ 4.9 (128)  [ Book now ] [ WA ]  |
+----------------------------------------------------------+
|  Trust strip: Easy online booking | Secure deposit | Reminders |
|  Our services  [card][card][card]  [card][card][card]    |
|  How it works:  1 Choose  →  2 Pick a time  →  3 Confirm  |
|  Meet the team  [photo][photo][photo][photo]             |
|  Gallery        [img][img][img][img][img][img]           |
|  Reviews        [review][review][review]                 |
|  FAQ preview    (4 items accordion)                      |
|  Find us: address, map link | opening hours table        |
|  CTA band: "Ready to book?"   [ Book now ]               |
+----------------------------------------------------------+
[ footer: Services | Info | Contact + hours + "Powered by" ]
```

**Auto-hide rule:** every block above is wrapped in a check (`@if ($featured->isNotEmpty())`, `@if ($page->content('about.story'))`). Required content for publishing (9.9) guarantees the core blocks exist; optional blocks simply disappear. Never render an empty heading.

## 9.6 Content model (replaces `store_sections`)

Page text lives in one small table keyed by page, edited through plain forms. No layout data is stored.

```php
Schema::create('page_contents', function (Blueprint $t) {
    $t->id();
    $t->foreignId('store_page_id')->constrained()->cascadeOnDelete();
    $t->string('key', 40);            // home | about | contact | faq | policy_shipping_returns | policy_cancellation | policy_privacy | policy_terms
    $t->json('content');
    $t->timestamps();
    $t->unique(['store_page_id', 'key']);
});

Schema::create('gallery_images', function (Blueprint $t) {      // Business theme
    $t->id();
    $t->foreignId('seller_id')->constrained();
    $t->foreignId('store_page_id')->constrained()->cascadeOnDelete();
    $t->string('path'); $t->string('caption')->nullable(); $t->unsignedInteger('position')->default(0);
    $t->timestamps();
});

Schema::create('contact_messages', function (Blueprint $t) {
    $t->id();
    $t->foreignId('seller_id')->constrained();
    $t->foreignId('store_page_id')->constrained()->cascadeOnDelete();
    $t->string('name'); $t->string('phone', 20)->nullable(); $t->string('email')->nullable();
    $t->text('message'); $t->timestamp('read_at')->nullable(); $t->timestamps();
});
```

`store_pages` keeps `slug, name, theme, status, tagline, bio, logo_path, cover_path, whatsapp_number, address, city, state, brand_color, verified_at, published_at` and a small `settings` JSON for **business rules only** (delivery fee rules, booking window, cancellation hours, timezone). `theme` is locked once a page is published.

**Content shapes**

```
home     { headline, subheadline, announcement, hero_cta_label? }      // optional announcement bar
about    { story, image_path, values: [ {title, text} x0..4 ] }
contact  { phone, email, hours: {1..7: [open, close] | null}, instagram, facebook, tiktok, x }
faq      { items: [ {q, a} ] }
policy_* { body }                                                         // plain text / light markdown
```

**`StorePage::content()`**

```php
public function content(string $path, mixed $default = null): mixed
{
    $this->contentMap ??= $this->contents()->get()->keyBy('key');   // one query per request
    [$key, $field] = array_pad(explode('.', $path, 2), 2, null);
    $data = $this->contentMap[$key]->content ?? null;
    $value = $field === null ? $data : data_get($data, $field);
    return filled($value) ? $value : $default;
}
```

**Default content** — `DefaultContent::for(StorePage $page): array` returns starter text for every key (headline from `$page->name`, a generic About paragraph, 4 starter FAQs, and policy templates such as shipping/returns or cancellation). `CreateStorePage` writes these rows so a brand-new site is never empty. Sellers edit the text; they cannot remove pages.

**Business hours** come from `working_hours` (single source of truth for booking and for the hours table on About, Contact and the Home footer block).

## 9.7 Routing and theme resolution

**Helper (`app/Shared/Support/helpers.php`, autoloaded via `composer.json` `files`)**

```php
function theme_view(string $name, array $data = [])
{
    $page = app(\App\Domains\Seller\Models\StorePage::class);
    return view("themes.{$page->theme->value}.pages.{$name}", $data + ['page' => $page]);
}
```

**`EnsureTheme` middleware (alias `theme`)**

```php
public function handle(Request $request, Closure $next, string $theme)
{
    abort_unless(app(StorePage::class)->theme->value === $theme, 404);
    return $next($request);
}
```

**`routes/storefront.php`**

```php
// Shared URLs: one controller, view chosen by theme
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [InfoPageController::class, 'about'])->name('about');
Route::get('/faq', [InfoPageController::class, 'faq'])->name('faq');
Route::get('/policies/{key}', [PolicyController::class, 'show'])->name('policy');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.send');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/csrf', fn () => response()->json(['token' => csrf_token()]))->middleware('throttle:60,1');

Route::middleware('theme:store')->group(function () {
    Route::get('/shop', [ShopController::class, 'index'])->name('store.shop');
    Route::get('/shop/category/{category:slug}', [ShopController::class, 'category'])->name('store.category');
    Route::get('/products/{product:slug}', [ProductPageController::class, 'show'])->name('store.product');
    Route::get('/search', [ShopController::class, 'search'])->name('store.search');
    Route::get('/cart', [CartPageController::class, 'show'])->name('store.cart');
    Route::post('/checkout/start', [CheckoutController::class, 'start'])->name('store.checkout.start');
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('store.checkout');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('store.checkout.place');
    Route::get('/orders/{reference}', [OrderStatusController::class, 'show'])->middleware('signed')->name('store.order');
    Route::get('/track', [OrderStatusController::class, 'track'])->name('store.track');
    Route::post('/track', [OrderStatusController::class, 'find'])->middleware('throttle:10,1');
});

Route::middleware('theme:service')->group(function () {
    Route::get('/services', [ServicePageController::class, 'index'])->name('service.index');
    Route::get('/services/{service:slug}', [ServicePageController::class, 'show'])->name('service.show');
    Route::get('/book', [BookingPageController::class, 'show'])->name('service.book');
    Route::get('/availability', AvailabilityController::class)->middleware('throttle:60,1')->name('service.availability');
    Route::post('/bookings', [BookingPageController::class, 'store'])->middleware('throttle:10,1')->name('service.bookings.store');
    Route::get('/bookings/{reference}', [BookingPageController::class, 'confirmation'])->middleware('signed')->name('service.booking');
    Route::get('/bookings/{reference}/manage', [BookingPageController::class, 'manage'])->middleware('signed')->name('service.booking.manage');
    Route::post('/bookings/{reference}/cancel', [BookingPageController::class, 'cancel'])->middleware('signed');
    Route::get('/team', [TeamPageController::class, 'index'])->name('service.team');
    Route::get('/gallery', [GalleryPageController::class, 'index'])->name('service.gallery');
    Route::get('/reviews', [ReviewPageController::class, 'index'])->name('service.reviews');
});
```

Route-model binding (`{product:slug}`) must go through the `BelongsToSeller` scope so another seller's slug never resolves. Unknown or inactive slugs return the themed 404.

**Caching warning:** cached page HTML must not contain CSRF tokens or anything per-visitor. Forms that POST (cart drawer, contact, checkout, booking) fetch a fresh token from `/csrf` on first interaction (Alpine `x-init`) and put it in a hidden `_token` input. Do not full-page-cache `/contact`, `/checkout`, `/book`, `/cart`, `/track`, or signed pages.

**`HomeController`**

```php
public function index(StorePage $page)
{
    $data = match ($page->theme) {
        Theme::Store => [
            'categories'  => Category::orderBy('position')->limit(8)->get(),
            'featured'    => Product::active()->where('is_featured', true)->with('images')->limit(8)->get(),
            'newArrivals' => Product::active()->with('images')->latest()->limit(8)->get(),
        ],
        Theme::Service => [
            'services' => Service::active()->orderBy('position')->limit(6)->get(),
            'staff'    => StaffMember::active()->limit(4)->get(),
            'gallery'  => GalleryImage::orderBy('position')->limit(6)->get(),
            'hours'    => WorkingHour::summaryFor($page),
            'faqs'     => collect($page->content('faq.items', []))->take(4),
        ],
    };
    $data['reviews'] = Review::approved()->latest()->limit(3)->get();

    return theme_view('home', $data + ['schema' => JsonLd::home($page)]);
}
```

## 9.8 Layout and page code (starting skeletons)

**`components/themes/shared/head.blade.php`**

```blade
@props(['page', 'palette', 'title' => null, 'description' => null, 'noindex' => false, 'schema' => null])
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>{{ $title ? $title.' | '.$page->name : $page->name }}</title>
<meta name="description" content="{{ Str::limit($description ?? $page->tagline ?? '', 155) }}">
<link rel="canonical" href="{{ url()->current() }}">
@if ($noindex)<meta name="robots" content="noindex,nofollow">@endif
<meta property="og:site_name" content="{{ $page->name }}">
<meta property="og:title" content="{{ $title ?? $page->name }}">
<meta property="og:description" content="{{ Str::limit($description ?? $page->tagline ?? '', 155) }}">
@if ($page->cover_path)<meta property="og:image" content="{{ Storage::url($page->cover_path) }}">@endif
<meta name="theme-color" content="{{ $palette['brand'] }}">
<style>:root{--brand:{{ $palette['brand'] }};--brand-contrast:{{ $palette['contrast'] }};--brand-ink:{{ $palette['ink'] }}}</style>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if ($schema)<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>@endif
```

The `{{ }}` echoes of palette values are safe because `Color::palette()` only returns validated hex strings.

**`components/themes/store/layout.blade.php`** (the Business layout is identical with `theme-service`, the business header/footer, and a sticky mobile Book bar)

```blade
@props(['title' => null, 'description' => null, 'noindex' => false, 'schema' => null])
@php
  $page = app(\App\Domains\Seller\Models\StorePage::class);
  $palette = \App\Shared\Support\Color::palette($page->brand_color);
@endphp
<!doctype html>
<html lang="en" class="scroll-smooth">
<head><x-themes.shared.head :page="$page" :palette="$palette" :title="$title" :description="$description" :noindex="$noindex" :schema="$schema" /></head>
<body class="theme-store bg-white text-slate-900 antialiased" x-data>
  @if ($page->content('home.announcement'))
    <div class="bg-brand px-4 py-2 text-center text-sm text-brand-contrast">{{ $page->content('home.announcement') }}</div>
  @endif
  <x-themes.store.header :page="$page" />
  <main id="main">{{ $slot }}</main>
  <x-themes.store.footer :page="$page" />
  <x-themes.store.cart-drawer />
  <x-themes.shared.whatsapp-button :page="$page" />
</body>
</html>
```

**`themes/store/pages/home.blade.php`**

```blade
<x-themes.store.layout :title="null" :description="$page->content('home.subheadline', $page->tagline)" :schema="$schema">
  <section class="relative">
    <x-themes.shared.image :path="$page->cover_path" ratio="aspect-[4/3] md:aspect-[21/9]" :eager="true" :alt="$page->name" />
    <div class="absolute inset-0 flex flex-col justify-end gap-3 bg-gradient-to-t from-black/65 to-transparent p-6 text-white md:p-12">
      <h1 class="max-w-2xl text-3xl font-bold md:text-5xl">{{ $page->content('home.headline', $page->name) }}</h1>
      @if ($page->content('home.subheadline'))<p class="max-w-xl md:text-lg">{{ $page->content('home.subheadline') }}</p>@endif
      <div class="flex gap-3">
        <a href="{{ route('store.shop') }}" class="rounded-[var(--radius-btn)] bg-brand px-6 py-3 font-semibold text-brand-contrast hover:bg-brand-700">Shop now</a>
        @if ($page->whatsapp_number)<a href="https://wa.me/{{ ltrim($page->whatsapp_number, '+') }}" class="rounded-[var(--radius-btn)] border border-white px-6 py-3 font-semibold">WhatsApp</a>@endif
      </div>
    </div>
  </section>

  @if ($categories->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 py-10">
      <h2 class="mb-4 text-2xl font-bold">Shop by category</h2>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach ($categories as $c)
          <a href="{{ route('store.category', $c) }}" class="group rounded-[var(--radius-card)] bg-brand-50 p-5 text-center font-medium hover:bg-brand-100">{{ $c->name }}</a>
        @endforeach
      </div>
    </section>
  @endif

  @if ($featured->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 py-10">
      <div class="mb-4 flex items-end justify-between"><h2 class="text-2xl font-bold">Featured</h2><a href="{{ route('store.shop') }}" class="text-sm text-brand-ink">View all →</a></div>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach ($featured as $p)<x-themes.store.product-card :product="$p" />@endforeach</div>
    </section>
  @endif

  @if ($newArrivals->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 py-10">
      <h2 class="mb-4 text-2xl font-bold">New arrivals</h2>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach ($newArrivals as $p)<x-themes.store.product-card :product="$p" />@endforeach</div>
    </section>
  @endif

  <x-themes.store.trust-strip />
  @if ($reviews->isNotEmpty())<x-themes.shared.testimonials :reviews="$reviews" />@endif
  @if ($page->content('about.story'))<x-themes.shared.about-teaser :page="$page" />@endif
  <x-themes.shared.cta-band :page="$page" text="Questions? Chat with us" />
</x-themes.store.layout>
```

**`themes/service/pages/home.blade.php`** follows the Business wireframe in 9.5 in the same style: split hero with `Book now` (`route('service.book')`), `x-themes.service.trust-strip`, services preview (`@if ($services->isNotEmpty())`), the fixed 3-step "How it works", team preview, gallery preview, testimonials, FAQ preview using `x-themes.shared.faq-accordion :items="$faqs"`, hours table via `x-themes.service.hours-table :hours="$hours"`, and `cta-band` with `Book now`.

**`components/themes/shared/faq-accordion.blade.php`**

```blade
@props(['items'])
@if (collect($items)->isNotEmpty())
<div x-data="{ open: null }" class="divide-y rounded-[var(--radius-card)] border">
  @foreach ($items as $i => $item)
    <div>
      <button type="button" class="flex w-full items-center justify-between px-4 py-3 text-left font-medium" @click="open = open === {{ $i }} ? null : {{ $i }}" :aria-expanded="open === {{ $i }}">
        {{ $item['q'] }}<span x-text="open === {{ $i }} ? '−' : '+'"></span>
      </button>
      <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-4 text-slate-600">{{ $item['a'] }}</div>
    </div>
  @endforeach
</div>
@endif
```

**`ContactController@store`** (honeypot field `website` must be empty)

```php
public function store(Request $r, StorePage $page)
{
    abort_if(filled($r->input('website')), 422);                      // bots fill hidden field
    $d = $r->validate(['name' => 'required|max:80', 'phone' => ['nullable', new NigerianPhone],
                       'email' => 'nullable|email', 'message' => 'required|max:2000']);
    abort_unless(filled($d['phone'] ?? null) || filled($d['email'] ?? null), 422);

    $msg = ContactMessage::create($d + ['seller_id' => $page->seller_id, 'store_page_id' => $page->id]);
    $page->seller->notify(new NewContactMessage($msg));               // queued
    return back()->with('status', 'Thanks, we will get back to you soon.');
}
```

**`PolicyController@show`**

```php
$allowed = $page->theme === Theme::Store ? ['shipping-returns', 'privacy', 'terms'] : ['cancellation', 'privacy', 'terms'];
abort_unless(in_array($key, $allowed, true), 404);
$body = $page->content('policy_'.str_replace('-', '_', $key).'.body');
return theme_view('policy', ['key' => $key, 'body' => $body ? Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : null]);
```

The policy view prints `{!! $body !!}` **only** because the markdown renderer strips raw HTML and unsafe links; keep that option set and add a test (9.13).

## 9.9 Publish requirements (per theme)

| Requirement | Store | Business |
| --- | --- | --- |
| Business name, slug, brand colour | Yes | Yes |
| WhatsApp number or phone | Yes | Yes |
| At least one active item | 1 active product with an image | 1 active service |
| Extra | About story filled (starter text counts only if edited) | Working hours set for at least one day |
| Payment ready for checkout | Optional (WhatsApp ordering works without) | Deposit requires gateway; otherwise "request booking" mode |

`PublishStorePage` returns a checklist of failures with links to the right editor screen.

## 9.10 Dashboard changes (forms, not a builder)

Sidebar group **Website** (both themes): Brand and colour, Home text, About, Contact info, FAQ, Policies, SEO, Preview, Publish. Store-only: Products, Categories, Delivery. Business-only: Services, Team, Gallery, Availability. Both: Customer messages (contact form inbox), Reviews (approve/hide).

- **Brand and colour:** name, logo, favicon-less (use logo), cover image, 12 preset swatches plus a hex field. An Alpine live preview card shows a button, a link and a badge using the chosen colour and shows the contrast warning if the colour is rejected. Saving flushes the tag cache.
- **Home text:** headline, subheadline, announcement text. Nothing about layout.
- **FAQ:** Alpine repeater of question/answer rows (max 20) with up/down buttons.
- **Policies:** one textarea per policy with a "reset to starter text" button.
- **SEO:** site title suffix and default description only; per-page titles and descriptions are generated from content.
- **Preview:** opens `/preview` (signed, 1 hour) in a new tab so the seller sees the real multi-page site while drafting.

## 9.11 SEO and structured data per page

| Page | Title pattern | JSON-LD |
| --- | --- | --- |
| Home | `{Name} — {tagline}` | `Store` / `LocalBusiness` + `WebSite` |
| Shop, Services | `Shop \| {Name}` / `Services \| {Name}` | `ItemList` |
| Product | `{Product} \| {Name}` | `Product` with `Offer` (price in NGN, availability) |
| Service | `{Service} \| {Name}` | `Service` with `Offer` |
| About, Contact, FAQ | `{Page} \| {Name}` | `FAQPage` on FAQ; `ContactPage` optional |
| Cart, Checkout, Order, Booking, Search, Track, Book | same pattern | none, `noindex` |

One canonical URL per page, `robots.txt` per storefront host pointing to its own `sitemap.xml`, and the sitemap lists only indexable pages of that store (products, services, categories, static pages).

## 9.12 Replacement task list for the agent (supersedes T1.3, T3.x, T4.3, T4.4, T5.5, T6.6)

Order matters. Each task ends with the global verification commands from 6.4.

- [ ] **TW1 — Schema:** migrations for `page_contents`, `gallery_images`, `contact_messages`; do **not** create `store_sections` or `font_pair`. *Done when:* `migrate:fresh` passes.
- [ ] **TW2 — Colour system:** `Color` helper, presets config, validation rule `BrandColor`. *Done when:* unit tests cover normalise (`#0F6`, `0f766e`), luminance, `textOn`, `ink` reaching 4.5:1, and rejecting `#ffffff`/`#fafafa`.
- [ ] **TW3 — Tokens and fonts:** `app.css` tokens, self-hosted Inter and Plus Jakarta Sans, `theme-store`/`theme-service` classes. *Done when:* `npm run build` passes and a test page recolours when `--brand` changes.
- [ ] **TW4 — Routing core:** `EnsureTheme`, `theme_view`, storefront route file as in 9.7, `StorePage::content()`, `DefaultContent`, `CreateStorePage` seeds content. *Done when:* a new page has all content rows; store URLs 404 on a service site and vice versa.
- [ ] **TW5 — Shared components:** `head`, `image`, `price`, `rating-stars`, `whatsapp-button`, `breadcrumbs`, `faq-accordion`, `testimonials`, `about-teaser`, `cta-band`, `contact-form`. *Done when:* each renders in a `/dev/ui` gallery route (local only).
- [ ] **TW6 — Store shell:** layout, header (mobile menu, search, cart count), footer, cart drawer, `/csrf` endpoint, trust strip. *Done when:* header and footer render on every Store URL with correct links.
- [ ] **TW7 — Store commerce pages:** Home, Shop (filters, sort, pagination), Category, Product, Search, Cart, Checkout, Order status, Track order, 404. *Done when:* a buyer completes browse → cart → checkout on the demo store and each URL returns the right status.
- [ ] **TW8 — Info pages:** About, Contact (stores messages, honeypot, throttle), FAQ, Policies for both themes. *Done when:* page tests pass for both themes; unknown policy keys 404.
- [ ] **TW9 — Business shell:** layout, header with Book now, footer with hours, sticky mobile Book bar, trust strip, hours table. *Done when:* renders on every Business URL.
- [ ] **TW10 — Business pages:** Home, Services, Service, Book (4-step flow from Part 7), Confirmation, Manage, Team, Gallery, Reviews, 404. *Done when:* a visitor books a slot end to end on the demo business and receives the confirmation page.
- [ ] **TW11 — Website editors in the dashboard:** Brand and colour, Home text, About, Contact info, FAQ, Policies, SEO, Gallery (Business), Messages, Reviews moderation. *Done when:* each save changes the public page and busts the cache.
- [ ] **TW12 — Publish checklist:** rules in 9.9. *Done when:* publishing an incomplete site returns a checklist with correct links.
- [ ] **TW13 — SEO:** titles, canonical, noindex, JSON-LD, sitemap, robots. *Done when:* sitemap excludes cart, checkout, orders and bookings; JSON-LD parses as JSON.
- [ ] **TW14 — Demo sites:** update `DemoSellerSeeder` with two complete sites (Store in teal, Business in indigo) including images, content, reviews. *Done when:* `migrate:fresh --seed` yields both browsable sites.
- [ ] **TW15 — Visual QA:** open every page of both demos at 375, 768 and 1280 px, with brand colours teal, amber and near-black; record issues in `docs/QA.md` and fix. *Done when:* no horizontal scroll, readable button text in all three colours, Lighthouse targets from 4.7 met on Home and one detail page per theme.

## 9.13 Tests that replace the old theme smoke test

```php
it('serves every public page of each theme', function (string $theme, array $urls) {
    $page = publishedPage($theme);
    seedMinimalContent($page);                              // helper: one item, hours, reviews
    foreach ($urls as $url => $noindex) {
        $res = $this->get(storeHost().$url)->assertOk()->assertSee($page->name);
        $noindex ? $res->assertSee('noindex', false) : $res->assertDontSee('noindex', false);
    }
})->with([
    ['store',   ['/' => false, '/shop' => false, '/about' => false, '/contact' => false, '/faq' => false, '/policies/privacy' => false, '/cart' => true, '/track' => true]],
    ['service', ['/' => false, '/services' => false, '/team' => false, '/gallery' => false, '/reviews' => false, '/about' => false, '/contact' => false, '/book' => true]],
]);

it('does not serve another themes pages', function () {
    publishedPage('service');
    $this->get(storeHost().'/shop')->assertNotFound();
});

it('keeps button text readable for any brand colour', function (string $hex) {
    $p = Color::palette($hex);
    expect(Color::contrast($p['brand'], $p['contrast']))->toBeGreaterThanOrEqual(4.5);
    expect(Color::contrast($p['ink'], '#ffffff'))->toBeGreaterThanOrEqual(4.5);
})->with(['#0f766e', '#d97706', '#facc15', '#0f172a', '#7c3aed']);

it('hides empty blocks instead of printing empty headings', function () {
    $page = publishedPage('store');
    $this->get(storeHost().'/')->assertDontSee('Featured')->assertDontSee('New arrivals');
});

it('escapes seller supplied text', function () {
    $page = publishedPage('store');
    $page->contents()->updateOrCreate(['key' => 'home'], ['content' => ['headline' => '<script>alert(1)</script>']]);
    $this->get(storeHost().'/')->assertDontSee('<script>alert(1)</script>', false);
});

it('strips raw html from policy text', function () {
    $page = publishedPage('store');
    $page->contents()->updateOrCreate(['key' => 'policy_privacy'], ['content' => ['body' => "Hello <script>x</script> [a](javascript:alert(1))"]]);
    $this->get(storeHost().'/policies/privacy')->assertDontSee('<script>', false)->assertDontSee('javascript:', false);
});

it('rejects honeypot and throttles the contact form', function () { /* website filled => 422; 6th post in a minute => 429 */ });
it('never lists another sellers products in the shop or sitemap', function () { /* extend tenancy tests to /shop and /sitemap.xml */ });
it('keeps cached pages free of csrf tokens', function () { /* GET / twice, assert response body has no name="_token" value */ });
```

## 9.14 How the earlier parts still apply

- **Part 5 and Part 7** components (product card, cart drawer, booking widget, WhatsApp link, `.ics`) are reused inside the new pages; ignore their section-registry wiring.
- **Parts 3, 6 (non-theme tasks), 8 (money, tenancy, booking tests)** are unchanged.
- **Part 4.3/4.4 wireframes** are superseded by 9.4 and 9.5 for page lists; the product page, cart drawer, checkout and booking-flow specs there still hold.
- **Part 4.2 font pairs and brand presets** are replaced by 9.2 and 9.3.

---

# Part 10 — Page-by-Page Blade Code (full websites)

This part gives starting code for every remaining page of both themes, following Part 9. Same rules: colours only through `bg-brand`, `text-brand-ink`, `bg-brand-50` etc.; escape seller content with `{{ }}`; blocks hide when empty. Treat the code as skeletons to adapt and test, and check APIs against current docs.

## 10.1 Shared plumbing

**`theme_view` with a shared fallback** (replaces the helper in 9.7, so info pages are written once)

```php
function theme_view(string $name, array $data = [])
{
    $page = app(\App\Domains\Seller\Models\StorePage::class);
    return view()->first([
        "themes.{$page->theme->value}.pages.{$name}",   // theme specific wins
        "themes.shared.pages.{$name}",                    // fallback: about, contact, faq, policy, 404
    ], $data + ['page' => $page]);
}
```

Shared pages wrap themselves in the right layout with `<x-dynamic-component :component="'themes.'.$page->theme->value.'.layout'" ...>`.

**Themed 404 (`bootstrap/app.php`)**

```php
->withExceptions(function (Exceptions $e) {
    $e->render(function (NotFoundHttpException $ex, Request $request) {
        if (app()->bound(\App\Domains\Seller\Models\StorePage::class)) {
            return response(theme_view('404')->render(), 404);
        }
    });
})
```

**Route binding that hides inactive items** — add to `Product` and `Service`:

```php
public function resolveRouteBinding($value, $field = null)
{
    return $this->where($field ?? 'slug', $value)->active()->firstOrFail();   // scope also applies BelongsToSeller
}
```

**Opening hours presenter (both themes)**

```php
final class HoursPresenter
{
    public static function for(StorePage $page): array      // [['day' => 'Monday', 'hours' => '09:00–17:00' | 'Closed'], ...]
    {
        $names = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $rows = $page->theme === Theme::Service
            ? WorkingHour::where('store_page_id', $page->id)->whereNull('staff_member_id')->get()->groupBy('weekday')
                ->map(fn ($g) => $g->map(fn ($h) => substr($h->start_time, 0, 5).'–'.substr($h->end_time, 0, 5))->implode(', '))
            : collect($page->content('contact.hours', []))->map(fn ($v) => $v ? implode('–', $v) : null);
        return collect($names)->map(fn ($n, $d) => ['day' => $n, 'hours' => $rows[$d] ?? 'Closed'])->values()->all();
    }
}
```

**`components/themes/shared/hours.blade.php`**

```blade
@props(['page'])
<dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
  @foreach (\App\Shared\Support\HoursPresenter::for($page) as $row)
    <dt class="font-medium">{{ $row['day'] }}</dt><dd class="text-slate-600">{{ $row['hours'] }}</dd>
  @endforeach
</dl>
```

**`components/themes/shared/breadcrumbs.blade.php`**

```blade
@props(['items'])   {{-- [['Home', route('home')], ['Shop', route('store.shop')], ['Current']] --}}
<nav aria-label="Breadcrumb" class="mx-auto max-w-7xl px-4 pt-4 text-sm text-slate-500">
  @foreach ($items as $i => $item)
    @if (isset($item[1]))<a href="{{ $item[1] }}" class="hover:text-brand-ink">{{ $item[0] }}</a> / @else<span aria-current="page">{{ $item[0] }}</span>@endif
  @endforeach
</nav>
```

**`components/themes/shared/page-title.blade.php`**

```blade
@props(['title', 'lead' => null])
<header class="mx-auto max-w-3xl px-4 py-10 text-center">
  <h1 class="text-3xl font-bold md:text-4xl">{{ $title }}</h1>
  @if ($lead)<p class="mt-3 text-slate-600">{{ $lead }}</p>@endif
</header>
```

## 10.2 Shared info pages (written once, used by both themes)

**`themes/shared/pages/about.blade.php`**

```blade
<x-dynamic-component :component="'themes.'.$page->theme->value.'.layout'" title="About" :description="\Illuminate\Support\Str::limit($page->content('about.story', ''), 150)">
  <x-themes.shared.breadcrumbs :items="[['Home', route('home')], ['About']]" />
  <x-themes.shared.page-title :title="'About '.$page->name" />
  <section class="mx-auto grid max-w-5xl gap-8 px-4 pb-12 md:grid-cols-2 md:items-center">
    @if ($page->content('about.image_path'))
      <x-themes.shared.image :path="$page->content('about.image_path')" ratio="aspect-[4/3]" class="rounded-[var(--radius-card)]" :alt="$page->name" />
    @endif
    <div class="space-y-4 leading-relaxed text-slate-700 {{ $page->content('about.image_path') ? '' : 'md:col-span-2 max-w-3xl mx-auto' }}">
      {!! nl2br(e($page->content('about.story', $page->bio))) !!}
    </div>
  </section>
  @if (count($page->content('about.values', [])))
    <section class="bg-brand-50 py-12"><div class="mx-auto grid max-w-5xl gap-6 px-4 md:grid-cols-3">
      @foreach ($page->content('about.values') as $v)
        <div class="rounded-[var(--radius-card)] bg-white p-5"><h2 class="font-semibold">{{ $v['title'] }}</h2><p class="mt-1 text-sm text-slate-600">{{ $v['text'] }}</p></div>
      @endforeach
    </div></section>
  @endif
  <x-themes.shared.cta-band :page="$page" :text="$page->theme->value === 'store' ? 'Ready to shop?' : 'Ready to book?'" />
</x-dynamic-component>
```

`nl2br(e(...))` escapes first, then adds line breaks, so the `{!! !!}` is safe.

**`themes/shared/pages/contact.blade.php`**

```blade
<x-dynamic-component :component="'themes.'.$page->theme->value.'.layout'" title="Contact">
  <x-themes.shared.breadcrumbs :items="[['Home', route('home')], ['Contact']]" />
  <x-themes.shared.page-title title="Contact us" lead="We usually reply within a day. For quick answers, message us on WhatsApp." />
  <div class="mx-auto grid max-w-5xl gap-10 px-4 pb-16 md:grid-cols-2">
    <form method="post" action="{{ route('contact.send') }}" class="grid gap-3">
      @csrf
      <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">   {{-- honeypot --}}
      <input name="name" required value="{{ old('name') }}" placeholder="Your name" autocomplete="name" class="rounded-lg border px-3 py-3">
      <input name="phone" inputmode="tel" value="{{ old('phone') }}" placeholder="Phone (080...)" autocomplete="tel" class="rounded-lg border px-3 py-3">
      <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" autocomplete="email" class="rounded-lg border px-3 py-3">
      <textarea name="message" required rows="5" placeholder="How can we help?" class="rounded-lg border px-3 py-3">{{ old('message') }}</textarea>
      @if ($errors->any())<p class="text-sm text-red-600">{{ $errors->first() }}</p>@endif
      @if (session('status'))<p class="rounded-lg bg-brand-50 px-3 py-2 text-sm">{{ session('status') }}</p>@endif
      <button class="rounded-[var(--radius-btn)] bg-brand px-6 py-3 font-semibold text-brand-contrast hover:bg-brand-700">Send message</button>
    </form>
    <div class="space-y-6 text-sm">
      <div class="space-y-1">
        @if ($page->content('contact.phone'))<p>📞 <a href="tel:{{ $page->content('contact.phone') }}" class="text-brand-ink">{{ $page->content('contact.phone') }}</a></p>@endif
        @if ($page->whatsapp_number)<p>💬 <a href="https://wa.me/{{ ltrim($page->whatsapp_number, '+') }}" class="text-brand-ink">WhatsApp</a></p>@endif
        @if ($page->content('contact.email'))<p>✉️ <a href="mailto:{{ $page->content('contact.email') }}" class="text-brand-ink">{{ $page->content('contact.email') }}</a></p>@endif
        @if ($page->address)<p>📍 {{ $page->address }}, {{ $page->city }} · <a class="text-brand-ink" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($page->address.' '.$page->city) }}" rel="noopener" target="_blank">Open map</a></p>@endif
      </div>
      <div><h2 class="mb-2 font-semibold">Opening hours</h2><x-themes.shared.hours :page="$page" /></div>
    </div>
  </div>
</x-dynamic-component>
```

The form carries `@csrf` directly because `/contact` is never page-cached (9.7).

**`themes/shared/pages/faq.blade.php`**

```blade
<x-dynamic-component :component="'themes.'.$page->theme->value.'.layout'" title="FAQ" :schema="\App\Shared\Support\JsonLd::faq($page)">
  <x-themes.shared.breadcrumbs :items="[['Home', route('home')], ['FAQ']]" />
  <x-themes.shared.page-title title="Frequently asked questions" />
  <div class="mx-auto max-w-3xl px-4 pb-16">
    @php $items = $page->content('faq.items', []); @endphp
    @if (count($items))<x-themes.shared.faq-accordion :items="$items" />@else<p class="text-center text-slate-500">No questions yet. Reach us on the <a class="text-brand-ink" href="{{ route('contact') }}">contact page</a>.</p>@endif
  </div>
</x-dynamic-component>
```

**`themes/shared/pages/policy.blade.php`** (`$key`, `$body` come from `PolicyController`)

```blade
@php $titles = ['shipping-returns' => 'Shipping and returns', 'cancellation' => 'Cancellation policy', 'privacy' => 'Privacy policy', 'terms' => 'Terms of service']; @endphp
<x-dynamic-component :component="'themes.'.$page->theme->value.'.layout'" :title="$titles[$key]">
  <x-themes.shared.breadcrumbs :items="[['Home', route('home')], [$titles[$key]]]" />
  <x-themes.shared.page-title :title="$titles[$key]" />
  <article class="prose mx-auto max-w-3xl px-4 pb-16">
    @if ($body){!! $body !!}@else<p>This policy has not been added yet.</p>@endif
  </article>
</x-dynamic-component>
```

If using `prose` classes, add the Tailwind typography plugin; otherwise style `h2`, `p`, `ul` with a small `.policy` CSS block.

**`themes/shared/pages/404.blade.php`**

```blade
<x-dynamic-component :component="'themes.'.$page->theme->value.'.layout'" title="Page not found" :noindex="true">
  <section class="mx-auto max-w-xl px-4 py-24 text-center">
    <p class="text-6xl font-bold text-brand-ink">404</p>
    <h1 class="mt-2 text-2xl font-bold">We could not find that page</h1>
    <a href="{{ $page->theme->value === 'store' ? route('store.shop') : route('service.book') }}" class="mt-6 inline-block rounded-[var(--radius-btn)] bg-brand px-6 py-3 font-semibold text-brand-contrast">
      {{ $page->theme->value === 'store' ? 'Continue shopping' : 'Book now' }}
    </a>
  </section>
</x-dynamic-component>
```

## 10.3 Store theme: header and footer

**`components/themes/store/header.blade.php`**

```blade
@props(['page'])
@php $cats = \App\Domains\Catalog\Models\Category::orderBy('position')->limit(8)->get(); @endphp
<header x-data="{ menu: false }" class="sticky top-0 z-30 border-b bg-white/95 backdrop-blur">
  <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
    <button type="button" class="p-2 md:hidden" @click="menu = true" aria-label="Open menu">☰</button>
    <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2 text-lg font-bold">
      @if ($page->logo_path)<img src="{{ Storage::url($page->logo_path) }}" alt="" class="h-8 w-8 rounded-full object-cover">@endif
      <span class="truncate">{{ $page->name }}</span>
      @if ($page->verified_at)<x-themes.shared.verified-badge />@endif
    </a>
    <nav class="ml-6 hidden items-center gap-6 text-sm font-medium md:flex" aria-label="Main">
      <a href="{{ route('home') }}" class="hover:text-brand-ink">Home</a>
      <a href="{{ route('store.shop') }}" class="hover:text-brand-ink">Shop</a>
      @if ($cats->isNotEmpty())
        <div class="relative" x-data="{ open: false }" @mouseleave="open = false" @keydown.escape="open = false">
          <button type="button" @click="open = !open" @mouseenter="open = true" :aria-expanded="open" class="hover:text-brand-ink">Categories ▾</button>
          <div x-show="open" x-cloak class="absolute left-0 top-full w-56 rounded-xl border bg-white p-2 shadow-lg">
            @foreach ($cats as $c)<a href="{{ route('store.category', $c) }}" class="block rounded-lg px-3 py-2 hover:bg-brand-50">{{ $c->name }}</a>@endforeach
          </div>
        </div>
      @endif
      <a href="{{ route('about') }}" class="hover:text-brand-ink">About</a>
      <a href="{{ route('contact') }}" class="hover:text-brand-ink">Contact</a>
    </nav>
    <form action="{{ route('store.search') }}" class="ml-auto hidden md:block" role="search">
      <input type="search" name="q" placeholder="Search products" class="w-56 rounded-full border px-4 py-2 text-sm">
    </form>
    <button type="button" @click="$dispatch('open-cart')" class="relative ml-auto p-2 md:ml-0" aria-label="Open cart">
      🛒<span x-show="$store.cart.count > 0" x-text="$store.cart.count" class="absolute -right-1 -top-1 rounded-full bg-brand px-1.5 text-xs text-brand-contrast"></span>
    </button>
  </div>

  <div x-show="menu" x-cloak class="fixed inset-0 z-50 md:hidden" @keydown.escape.window="menu = false">
    <div class="absolute inset-0 bg-black/40" @click="menu = false"></div>
    <nav class="absolute inset-y-0 left-0 w-72 overflow-y-auto bg-white p-5" aria-label="Mobile">
      <form action="{{ route('store.search') }}" class="mb-4"><input type="search" name="q" placeholder="Search products" class="w-full rounded-full border px-4 py-2"></form>
      <ul class="space-y-1 text-base font-medium">
        <li><a class="block py-2" href="{{ route('home') }}">Home</a></li>
        <li><a class="block py-2" href="{{ route('store.shop') }}">Shop</a></li>
        @foreach ($cats as $c)<li><a class="block py-2 pl-4 text-slate-600" href="{{ route('store.category', $c) }}">{{ $c->name }}</a></li>@endforeach
        <li><a class="block py-2" href="{{ route('about') }}">About</a></li>
        <li><a class="block py-2" href="{{ route('contact') }}">Contact</a></li>
        <li><a class="block py-2" href="{{ route('store.track') }}">Track order</a></li>
      </ul>
    </nav>
  </div>
</header>
```

**`components/themes/store/footer.blade.php`**

```blade
@props(['page'])
<footer class="mt-16 border-t bg-slate-50">
  <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 text-sm md:grid-cols-4">
    <div>
      <p class="text-lg font-bold">{{ $page->name }}</p>
      @if ($page->tagline)<p class="mt-2 text-slate-600">{{ $page->tagline }}</p>@endif
      <div class="mt-3 flex gap-3">
        @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'x' => 'X'] as $k => $label)
          @if ($page->content("contact.$k"))<a href="{{ $page->content("contact.$k") }}" rel="noopener nofollow" target="_blank" class="text-brand-ink">{{ $label }}</a>@endif
        @endforeach
      </div>
    </div>
    <div><h2 class="mb-2 font-semibold">Shop</h2><ul class="space-y-1"><li><a href="{{ route('store.shop') }}">All products</a></li><li><a href="{{ route('store.track') }}">Track your order</a></li></ul></div>
    <div><h2 class="mb-2 font-semibold">Help</h2><ul class="space-y-1"><li><a href="{{ route('faq') }}">FAQ</a></li><li><a href="{{ route('contact') }}">Contact</a></li><li><a href="{{ route('policy', 'shipping-returns') }}">Shipping and returns</a></li><li><a href="{{ route('policy', 'privacy') }}">Privacy</a></li><li><a href="{{ route('policy', 'terms') }}">Terms</a></li></ul></div>
    <div><h2 class="mb-2 font-semibold">Contact</h2><ul class="space-y-1 text-slate-600">
      @if ($page->content('contact.phone'))<li>{{ $page->content('contact.phone') }}</li>@endif
      @if ($page->content('contact.email'))<li>{{ $page->content('contact.email') }}</li>@endif
      @if ($page->address)<li>{{ $page->address }}, {{ $page->city }}</li>@endif
    </ul></div>
  </div>
  <div class="border-t py-4 text-center text-xs text-slate-500">© {{ date('Y') }} {{ $page->name }} · Powered by <a class="text-brand-ink" href="https://mypage.ng/?ref={{ $page->slug }}">mypage.ng</a></div>
</footer>
```

Social URLs from content must be validated as `https://` URLs when saved (dashboard FormRequest `url` + `starts_with:https://`).

## 10.4 Store theme: Shop, Category, Search

**`ShopController`**

```php
class ShopController extends Controller
{
    public function index(Request $r)                       { return $this->listing($r, null, 'Shop'); }
    public function category(Request $r, Category $category){ return $this->listing($r, $category, $category->name); }
    public function search(Request $r)                      { return $this->listing($r, null, 'Search', noindex: true); }

    private function listing(Request $r, ?Category $category, string $title, bool $noindex = false)
    {
        $r->validate(['sort' => 'in:new,price_asc,price_desc', 'min' => 'nullable|numeric|min:0', 'max' => 'nullable|numeric|min:0']);

        $q = Product::active()->with('images');
        $q->when($category, fn ($q) => $q->where('category_id', $category->id));
        $q->when($r->filled('category') && ! $category, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $r->string('category'))));
        $q->when($r->filled('q'), fn ($q) => $q->where('name', 'like', '%'.addcslashes($r->string('q'), '%_\\').'%'));
        $q->when($r->filled('min'), fn ($q) => $q->where('price_kobo', '>=', Money::toKobo($r->input('min'))));
        $q->when($r->filled('max'), fn ($q) => $q->where('price_kobo', '<=', Money::toKobo($r->input('max'))));
        $q->when($r->boolean('in_stock'), fn ($q) => $q->where(fn ($q) => $q->where('track_stock', false)->orWhere('stock_qty', '>', 0)));
        match ($r->input('sort', 'new')) {
            'price_asc' => $q->orderBy('price_kobo'), 'price_desc' => $q->orderByDesc('price_kobo'), default => $q->latest(),
        };

        return theme_view('shop', [
            'title' => $title, 'category' => $category, 'noindex' => $noindex,
            'products' => $q->paginate(24)->withQueryString(),
            'categories' => Category::orderBy('position')->get(),
        ]);
    }
}
```

**`themes/store/pages/shop.blade.php`** (also renders Category and Search)

```blade
<x-themes.store.layout :title="$title" :description="$category?->description" :noindex="$noindex || request()->hasAny(['sort','min','max','in_stock','page'])">
  <x-themes.shared.breadcrumbs :items="array_filter([['Home', route('home')], $category ? ['Shop', route('store.shop')] : null, [$title]])" />
  <div class="mx-auto max-w-7xl px-4 py-6" x-data="{ filters: false }">
    <div class="mb-4 flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ $title }} @if (request('q'))<span class="text-base font-normal text-slate-500">for "{{ request('q') }}"</span>@endif</h1>
      <button type="button" class="rounded-lg border px-3 py-2 text-sm md:hidden" @click="filters = !filters">Filters</button>
    </div>

    <div class="grid gap-8 md:grid-cols-[220px_1fr]">
      <form method="get" class="space-y-5 text-sm md:block" :class="filters ? 'block' : 'hidden md:block'">
        @if (request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
        <div><label class="mb-1 block font-semibold">Sort by</label>
          <select name="sort" class="w-full rounded-lg border px-3 py-2" onchange="this.form.submit()">
            <option value="new" @selected(request('sort', 'new') === 'new')>Newest</option>
            <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
            <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
          </select></div>
        @unless ($category)
        <div><label class="mb-1 block font-semibold">Category</label>
          <select name="category" class="w-full rounded-lg border px-3 py-2" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach ($categories as $c)<option value="{{ $c->slug }}" @selected(request('category') === $c->slug)>{{ $c->name }}</option>@endforeach
          </select></div>
        @endunless
        <div><label class="mb-1 block font-semibold">Price (₦)</label>
          <div class="flex gap-2"><input name="min" value="{{ request('min') }}" inputmode="numeric" placeholder="Min" class="w-full rounded-lg border px-2 py-2"><input name="max" value="{{ request('max') }}" inputmode="numeric" placeholder="Max" class="w-full rounded-lg border px-2 py-2"></div></div>
        <label class="flex items-center gap-2"><input type="checkbox" name="in_stock" value="1" @checked(request('in_stock')) onchange="this.form.submit()"> In stock only</label>
        <button class="w-full rounded-[var(--radius-btn)] bg-brand py-2 font-semibold text-brand-contrast hover:bg-brand-700">Apply</button>
        <a href="{{ url()->current() }}" class="block text-center text-slate-500">Clear</a>
      </form>

      <div>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
          @forelse ($products as $product)<x-themes.store.product-card :product="$product" />
          @empty<p class="col-span-full py-16 text-center text-slate-500">No products match your filters.</p>@endforelse
        </div>
        <div class="mt-8">{{ $products->links() }}</div>
      </div>
    </div>
  </div>
</x-themes.store.layout>
```

Filtered and paginated URLs are `noindex` while the clean `/shop` and `/shop/category/{slug}` stay indexable (the canonical is `url()->current()`, which has no query string).

## 10.5 Store theme: Product page

**`ProductPageController@show`**

```php
public function show(Product $product)    // binding already hides inactive items
{
    $product->load(['images', 'variants', 'category']);
    return theme_view('product', [
        'product' => $product,
        'related' => Product::active()->with('images')->where('id', '!=', $product->id)
                        ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))->limit(4)->get(),
        'schema' => JsonLd::product(app(StorePage::class), $product),
    ]);
}
```

**`themes/store/pages/product.blade.php`**

```blade
@php
  $variants = $product->variants->map->only(['id', 'name', 'price_kobo', 'stock_qty'])->values();
  $wa = app(\App\Domains\Messaging\Actions\GenerateWaMeLink::class)->forProduct($page, $product);
@endphp
<x-themes.store.layout :title="$product->name" :description="\Illuminate\Support\Str::limit(strip_tags($product->description), 150)" :schema="$schema">
  <x-themes.shared.breadcrumbs :items="array_filter([['Home', route('home')], ['Shop', route('store.shop')], $product->category ? [$product->category->name, route('store.category', $product->category)] : null, [$product->name]])" />

  <div class="mx-auto grid max-w-7xl gap-8 px-4 py-6 md:grid-cols-2"
       x-data="{ active: 0, qty: 1, variant: null, variants: @js($variants), base: {{ $product->price_kobo }}, stock: {{ $product->track_stock ? $product->stock_qty : 'null' }},
                 get price() { const v = this.variants.find(v => v.id === this.variant); return v?.price_kobo ?? this.base; },
                 get available() { const v = this.variants.find(v => v.id === this.variant); const s = v ? v.stock_qty : this.stock; return s === null || s > 0; } }">

    {{-- Gallery --}}
    <div>
      <div class="overflow-hidden rounded-[var(--radius-card)] bg-slate-100">
        @foreach ($product->images as $i => $img)
          <img x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif src="{{ Storage::url($img->path) }}" alt="{{ $img->alt ?: $product->name }}" class="aspect-square w-full object-cover" @if ($i === 0) fetchpriority="high" @endif>
        @endforeach
      </div>
      @if ($product->images->count() > 1)
        <div class="mt-3 flex gap-2 overflow-x-auto">
          @foreach ($product->images as $i => $img)
            <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'ring-2 ring-brand' : ''" class="h-16 w-16 shrink-0 overflow-hidden rounded-lg"><img src="{{ Storage::url($img->path) }}" alt="" loading="lazy" class="h-full w-full object-cover"></button>
          @endforeach
        </div>
      @endif
    </div>

    {{-- Details --}}
    <div class="space-y-4">
      <h1 class="text-2xl font-bold md:text-3xl">{{ $product->name }}</h1>
      <p class="text-2xl font-semibold" x-text="'₦' + (price / 100).toLocaleString()"></p>
      @if ($variants->isNotEmpty())
        <div><label class="mb-1 block text-sm font-medium">Option</label>
          <div class="flex flex-wrap gap-2">
            <template x-for="v in variants" :key="v.id">
              <button type="button" @click="variant = v.id" :disabled="v.stock_qty === 0" :class="variant === v.id ? 'bg-brand text-brand-contrast' : 'border'" class="rounded-lg px-4 py-2 text-sm disabled:opacity-40" x-text="v.name"></button>
            </template>
          </div></div>
      @endif
      <div class="flex items-center gap-3"><span class="text-sm font-medium">Quantity</span>
        <div class="flex items-center rounded-lg border"><button type="button" class="px-3 py-2" @click="qty = Math.max(1, qty - 1)">−</button><span class="w-8 text-center" x-text="qty"></span><button type="button" class="px-3 py-2" @click="qty++">+</button></div></div>

      <div class="fixed inset-x-0 bottom-0 z-30 flex gap-2 border-t bg-white p-3 md:static md:border-0 md:p-0" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));">
        <button type="button" :disabled="!available || ({{ $variants->isNotEmpty() ? 'true' : 'false' }} && variant === null)"
          @click="$store.cart.add({ id: {{ $product->id }}, name: @js($product->name), price_kobo: price, variant: variant }, qty); $dispatch('open-cart')"
          class="flex-1 rounded-[var(--radius-btn)] bg-brand py-3 font-semibold text-brand-contrast hover:bg-brand-700 disabled:opacity-50" x-text="available ? 'Add to cart' : 'Sold out'"></button>
        @if ($wa)<a href="{{ $wa }}" target="_blank" rel="noopener" class="rounded-[var(--radius-btn)] border px-4 py-3 font-semibold">WhatsApp</a>@endif
      </div>

      @if ($product->description)<div class="border-t pt-4 text-sm leading-relaxed text-slate-700">{!! nl2br(e($product->description)) !!}</div>@endif
      <ul class="space-y-1 border-t pt-4 text-sm text-slate-600"><li>🚚 Delivery across Nigeria</li><li>🔒 Secure payment</li><li><a class="text-brand-ink" href="{{ route('policy', 'shipping-returns') }}">Shipping and returns</a></li></ul>
    </div>
  </div>

  @if ($related->isNotEmpty())
    <section class="mx-auto max-w-7xl px-4 py-10"><h2 class="mb-4 text-xl font-bold">You may also like</h2>
      <div class="grid grid-cols-2 gap-4 md:grid-cols-4">@foreach ($related as $p)<x-themes.store.product-card :product="$p" />@endforeach</div></section>
  @endif
  <div class="h-20 md:hidden"></div>   {{-- space for the sticky bar --}}
</x-themes.store.layout>
```

The server still re-prices every line at checkout, so the `price_kobo` sent from the page is only for display. Show the "Delivery across Nigeria" line only if the seller's delivery settings say so; otherwise drop it (auto-hide rule).

## 10.6 Store theme: Cart, Checkout, Order status, Track

**Cart pricer (server truth)**

```php
final class CartPricer
{
    /** @param array<int, array{id:int, variant:?int, qty:int}> $cart */
    public function price(array $cart): array
    {
        $cart = collect($cart)->filter(fn ($l) => isset($l['id'], $l['qty']) && (int) $l['qty'] > 0)->take(50);
        $products = Product::active()->with('variants')->whereIn('id', $cart->pluck('id'))->get()->keyBy('id');
        $lines = []; $subtotal = 0;

        foreach ($cart as $l) {
            $p = $products[(int) $l['id']] ?? throw new InvalidCart('Item unavailable');
            $v = ($l['variant'] ?? null) ? $p->variants->firstWhere('id', (int) $l['variant']) : null;
            $unit = $v?->price_kobo ?? $p->price_kobo;
            $qty  = min((int) $l['qty'], 99);
            $stock = $v ? $v->stock_qty : ($p->track_stock ? $p->stock_qty : null);
            if ($stock !== null && $qty > $stock) throw new InvalidCart("Only {$stock} left of {$p->name}");
            $lines[] = ['product_id' => $p->id, 'variant_id' => $v?->id, 'name' => $p->name.($v ? " ({$v->name})" : ''), 'unit_kobo' => $unit, 'qty' => $qty, 'total_kobo' => $unit * $qty];
            $subtotal += $unit * $qty;
        }
        return ['lines' => $lines, 'subtotal_kobo' => $subtotal];
    }
}
```

**Delivery fee** (`settings.delivery`: `flat_kobo`, `free_over_kobo`, `pickup` bool)

```php
final class DeliveryFee
{
    public static function for(StorePage $page, int $subtotal, string $method): int
    {
        if ($method === 'pickup') return 0;
        $d = $page->settings['delivery'] ?? [];
        $free = $d['free_over_kobo'] ?? null;
        return ($free && $subtotal >= $free) ? 0 : (int) ($d['flat_kobo'] ?? 0);
    }
}
```

**`CheckoutController`**

```php
public function start(Request $r, StorePage $page, CartPricer $pricer)
{
    $cart = json_decode($r->input('cart', '[]'), true) ?: [];
    try { $priced = $pricer->price($cart); } catch (InvalidCart $e) { return redirect()->route('store.cart')->withErrors($e->getMessage()); }
    abort_if(empty($priced['lines']), 422);
    session(['checkout' => $priced]);

    if ($r->input('channel') === 'whatsapp') {
        $order = app(CreateOrderFromWhatsApp::class)->handle($page, $priced);          // pending order with reference
        return redirect()->away(app(GenerateWaMeLink::class)->forOrder($page, $priced['lines'], $priced['subtotal_kobo'], $order->reference));
    }
    return redirect()->route('store.checkout');
}

public function show(StorePage $page)
{
    $priced = session('checkout') ?? abort(redirect()->route('store.cart'));
    return theme_view('checkout', ['priced' => $priced, 'states' => config('nigeria.states'), 'noindex' => true]);
}

public function store(PlaceOrderRequest $r, StorePage $page, CartPricer $pricer)
{
    $lines = session('checkout.lines') ?? abort(422);
    // re-price from the database so a price or stock change between steps is caught
    $priced = $pricer->price(collect($lines)->map(fn ($l) => ['id' => $l['product_id'], 'variant' => $l['variant_id'], 'qty' => $l['qty']])->all());
    $order = app(CreateOrderFromCheckout::class)->handle($page, $priced, $r->validated());   // customer upsert, DeliveryFee::for(), status pending
    $url = app(InitializePayment::class)->handle($order, $r->validated('email'));
    session()->forget('checkout');
    return redirect()->away($url);
}
```

`PlaceOrderRequest` validates name, `NigerianPhone`, email, `method` (`delivery|pickup`, pickup only if enabled), address and `state` (required when delivery), note. `config/nigeria.php` holds the list of 36 states plus FCT.

**`themes/store/pages/cart.blade.php`**

```blade
<x-themes.store.layout title="Cart" :noindex="true">
  <div class="mx-auto max-w-3xl px-4 py-8">
    <h1 class="mb-4 text-2xl font-bold">Your cart</h1>
    @if ($errors->any())<p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</p>@endif
    <div x-show="$store.cart.count === 0" class="py-16 text-center text-slate-500">Your cart is empty. <a class="text-brand-ink" href="{{ route('store.shop') }}">Start shopping</a></div>
    <div x-show="$store.cart.count > 0" x-cloak x-data="{ token: '' }" x-init="fetch('/csrf').then(r => r.json()).then(d => token = d.token)">
      <ul class="divide-y">
        <template x-for="(item, i) in $store.cart.items" :key="i">
          <li class="flex items-center justify-between gap-3 py-4">
            <div><p class="font-medium" x-text="item.name"></p><p class="text-sm text-slate-500" x-text="'₦' + (item.price_kobo / 100).toLocaleString()"></p></div>
            <div class="flex items-center gap-2">
              <button type="button" @click="item.qty = Math.max(1, item.qty - 1); $store.cart.save()" class="rounded border px-2">−</button>
              <span x-text="item.qty"></span>
              <button type="button" @click="item.qty++; $store.cart.save()" class="rounded border px-2">+</button>
              <button type="button" @click="$store.cart.remove(i)" class="ml-2 text-sm text-red-600">Remove</button>
            </div>
          </li>
        </template>
      </ul>
      <p class="mt-4 flex justify-between text-lg font-semibold"><span>Subtotal</span><span x-text="'₦' + ($store.cart.total / 100).toLocaleString()"></span></p>
      <p class="mt-1 text-sm text-slate-500">Delivery fees are shown at checkout.</p>
      <form method="post" action="{{ route('store.checkout.start') }}" class="mt-4 grid gap-2 sm:grid-cols-2">
        <input type="hidden" name="_token" :value="token">
        <input type="hidden" name="cart" :value="JSON.stringify($store.cart.items.map(i => ({ id: i.id, variant: i.variant, qty: i.qty })))">
        <button class="rounded-[var(--radius-btn)] bg-brand py-3 font-semibold text-brand-contrast hover:bg-brand-700" :disabled="!token">Checkout</button>
        <button name="channel" value="whatsapp" class="rounded-[var(--radius-btn)] border py-3 font-semibold" :disabled="!token">Order via WhatsApp</button>
      </form>
    </div>
  </div>
</x-themes.store.layout>
```

**`themes/store/pages/checkout.blade.php`**

```blade
@php $delivery = $page->settings['delivery'] ?? []; @endphp
<x-themes.store.layout title="Checkout" :noindex="true">
  <div class="mx-auto grid max-w-5xl gap-8 px-4 py-8 md:grid-cols-[1fr_360px]" x-data="{ method: 'delivery' }">
    <form method="post" action="{{ route('store.checkout.place') }}" class="space-y-4">
      @csrf
      <h1 class="text-2xl font-bold">Checkout</h1>
      @if ($errors->any())<div class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $errors->first() }}</div>@endif
      <fieldset class="grid gap-3"><legend class="mb-1 font-semibold">Your details</legend>
        <input name="name" required value="{{ old('name') }}" placeholder="Full name" autocomplete="name" class="rounded-lg border px-3 py-3">
        <input name="phone" required inputmode="tel" value="{{ old('phone') }}" placeholder="Phone (080...)" autocomplete="tel" class="rounded-lg border px-3 py-3">
        <input name="email" type="email" required value="{{ old('email') }}" placeholder="Email (for your receipt)" autocomplete="email" class="rounded-lg border px-3 py-3">
      </fieldset>
      <fieldset class="grid gap-3"><legend class="mb-1 font-semibold">Delivery</legend>
        <div class="flex gap-2">
          <label class="flex-1 rounded-lg border p-3" :class="method === 'delivery' ? 'border-brand bg-brand-50' : ''"><input type="radio" name="method" value="delivery" x-model="method" class="mr-2">Delivery</label>
          @if ($delivery['pickup'] ?? false)<label class="flex-1 rounded-lg border p-3" :class="method === 'pickup' ? 'border-brand bg-brand-50' : ''"><input type="radio" name="method" value="pickup" x-model="method" class="mr-2">Pickup</label>@endif
        </div>
        <div x-show="method === 'delivery'" class="grid gap-3">
          <input name="address" value="{{ old('address') }}" placeholder="Delivery address" autocomplete="street-address" class="rounded-lg border px-3 py-3">
          <select name="state" class="rounded-lg border px-3 py-3"><option value="">State</option>@foreach ($states as $s)<option @selected(old('state') === $s)>{{ $s }}</option>@endforeach</select>
        </div>
        <textarea name="note" rows="2" placeholder="Note for the seller (optional)" class="rounded-lg border px-3 py-3">{{ old('note') }}</textarea>
      </fieldset>
      <button class="w-full rounded-[var(--radius-btn)] bg-brand py-3 text-lg font-semibold text-brand-contrast hover:bg-brand-700">Pay ₦{{ number_format(($priced['subtotal_kobo'] + ($delivery['flat_kobo'] ?? 0)) / 100) }}</button>
      <p class="text-center text-xs text-slate-500">You will be redirected to a secure payment page.</p>
    </form>

    <aside class="h-fit rounded-[var(--radius-card)] bg-slate-50 p-5">
      <h2 class="mb-3 font-semibold">Order summary</h2>
      <ul class="divide-y text-sm">@foreach ($priced['lines'] as $l)<li class="flex justify-between py-2"><span>{{ $l['qty'] }} × {{ $l['name'] }}</span><span>{{ \App\Shared\Support\Money::format($l['total_kobo']) }}</span></li>@endforeach</ul>
      <p class="mt-3 flex justify-between"><span>Subtotal</span><span>{{ \App\Shared\Support\Money::format($priced['subtotal_kobo']) }}</span></p>
      <p class="flex justify-between text-sm text-slate-500"><span>Delivery</span><span x-text="method === 'pickup' ? 'Free' : '{{ \App\Shared\Support\Money::format((int) ($delivery['flat_kobo'] ?? 0)) }}'"></span></p>
    </aside>
  </div>
</x-themes.store.layout>
```

The total on the button is a display estimate; the real total is computed server-side in `CreateOrderFromCheckout` using `DeliveryFee::for()` (it handles pickup and the free-over threshold).

**`themes/store/pages/order.blade.php`** (signed URL, `$order` loaded by reference)

```blade
@php
  $steps = ['pending' => 'Order placed', 'paid' => 'Paid', 'processing' => 'Preparing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
  $current = array_search($order->status->value, array_keys($steps));
@endphp
<x-themes.store.layout :title="'Order '.$order->reference" :noindex="true">
  <div class="mx-auto max-w-2xl px-4 py-8">
    <h1 class="text-2xl font-bold">Order {{ $order->reference }}</h1>
    @if (in_array($order->status->value, ['cancelled', 'refunded']))
      <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-red-700">This order was {{ $order->status->value }}.</p>
    @else
      <ol class="my-6 grid grid-cols-5 gap-1 text-center text-xs">
        @foreach ($steps as $k => $label)
          <li><span class="mx-auto mb-1 flex h-7 w-7 items-center justify-center rounded-full {{ $loop->index <= $current ? 'bg-brand text-brand-contrast' : 'bg-slate-200' }}">{{ $loop->iteration }}</span>{{ $label }}</li>
        @endforeach
      </ol>
    @endif
    <ul class="divide-y rounded-[var(--radius-card)] border text-sm">@foreach ($order->items as $i)<li class="flex justify-between p-3"><span>{{ $i->qty }} × {{ $i->name }}</span><span>{{ \App\Shared\Support\Money::format($i->total_kobo) }}</span></li>@endforeach
      <li class="flex justify-between p-3 font-semibold"><span>Total</span><span>{{ \App\Shared\Support\Money::format($order->total_kobo) }}</span></li></ul>
    @if ($page->whatsapp_number)<a class="mt-6 inline-block rounded-[var(--radius-btn)] border px-5 py-3 font-semibold" href="https://wa.me/{{ ltrim($page->whatsapp_number, '+') }}?text={{ rawurlencode('Hello, about my order '.$order->reference) }}">Message the seller</a>@endif
  </div>
</x-themes.store.layout>
```

**Payment return** — `checkout.callback` re-verifies the transaction (Part 2, P7) and then redirects to `URL::temporarySignedRoute('store.order', now()->addDays(7), $order->reference)`, so the buyer always lands on a safe, signed status page.

**`OrderStatusController@find`** (Track order) — look up by `reference` **and** matching normalised phone; on success redirect to a 7-day signed `store.order` URL; on failure show one generic "We could not find that order" message (never reveal which field was wrong). Throttled `10,1`.

**`themes/store/pages/track.blade.php`** — a centred card with two inputs (`reference`, `phone`), `@csrf`, a submit button and the generic error. `noindex`.

---

# Part 7 — Revision: multi-page themes with merchant-editable content

> Supersedes the "fixed single home page with fixed sections" understanding in Parts 1–4.

1. **Each theme is a small multi-page website.** Public pages per theme:
   - **Store:** `home`, `products` (catalog), `product-show`, `about`, `contact`, `cart`, `checkout`, `order-tracking`.
   - **Service:** `home`, `services`, `service-show`, `about`, `contact`, `booking`, `booking-confirmation`.
2. **Every page renders its own ordered sections** from `store_sections`. `store_sections` gains a `page` column (`home|products|product-show|about|contact|cart|checkout|order-tracking` / `home|services|service-show|about|contact|booking|booking-confirmation`) in addition to `key`, `position`, `is_enabled`, `content JSON`.
3. **All section content is merchant-editable** in the dashboard page editor: rich text (headings, body copy) and image uploads (hero/cover/gallery) per section, plus section enable/disable and ordering. Content is stored in `store_sections.content` JSON and rendered into the theme's fixed section shells — the layout/styling stays fixed, only content is editable.
4. Rich-text HTML is sanitised on save (allowlist: `h2,h3,p,br,strong,em,ul,ol,li,a`) before being stored; output still escapes by default.
5. Public routes map per page, resolved through the middleware-bound `StorePage` and its theme:
   `/` → `themes.{theme}.page` (home), `/about`, `/contact`, `/products` or `/services`, `/products/{slug}` / `/services/{slug}`, `/cart`, `/checkout`, `/book`, `/track-order`.
6. Themes remain independent packs under `resources/views/themes/{store,service}/` sharing only `themes/_shared/*` (layout, whatsapp button, SEO head). Section components stay dynamic (`x-dynamic-component`), now grouped by page: `themes.{theme}.sections.{page}.{key}`.
7. The publish gate still applies per theme, but required sections are validated across pages (`products` section on `products` page must have ≥1 product, etc.).
