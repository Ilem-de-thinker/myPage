# MYPAGE.NG — Laravel 13 Project Structure & Build Plan

> **Revision 2026-10-23 — payment architecture.** This document still shows
> `Payments/EscrowAccount`, `Wallet`, `WalletLedger`, `Payout` and
> `EscrowProviderInterface`. Those are **being removed**: buyer payments go to each
> seller's own Paystack account, not to the platform, so there is no platform
> balance to hold, move or pay out. Replace them with:
>
> - `SellerGateway` (`seller_id`, `gateway`, `environment`, `public_key`,
>   `secret_key` encrypted, `secret_key_last4`, `webhook_secret` encrypted,
>   `verified_at`, `last_failed_at`, unique on `seller_id + gateway`)
> - `StoreReport` (`store_page_id`, `category`, `body`, reporter contact withheld
>   from the seller, `status`, `resolved_by_seller_id`, `resolved_at`, `admin_note`)
>
> Also new: storefronts move to enforced subdomains (`{store}.mypage.ng`), and the
> `verified` badge becomes a buyer-facing marker plus a checkout switch rather than
> a dashboard gate. The decision and deletion list are at the top of
> `before lunch.md`; build tracking is Phase 17 of `todo.md`.

> **Stack:** PHP 8.4 · Laravel 13 · MySQL 8 · Redis · Tailwind CSS 4 · Alpine.js · Livewire 4 (dashboard) · Laravel Sanctum (public API) · Queues (Redis) · Paystack + Flutterwave SDKs · WhatsApp Cloud API
> **Repo:** `mypage-ng/` monolith-first (modular monolith, domain-organized)

---

## 1. High-Level Architecture

Modular monolith with strict domain boundaries. Each domain owns its models, actions, services, jobs, events, and tests. Cross-domain talk happens via **events + service interfaces only** — never direct model reach-across.

```
┌─────────────────────────────────────────────────────────┐
│                        Web (Blade/Livewire)              │
│           Public Storefront · Checkout · Directory       │
├─────────────────────────────────────────────────────────┤
│                        Web (Blade/Livewire)              │
│              Seller Dashboard · Admin Panel              │
├─────────────────────────────────────────────────────────┤
│                     REST API (Sanctum)                   │
│        Mobile app later · Third-party integrations       │
├─────────────────────────────────────────────────────────┤
│  ┌────────┬────────┬────────┬────────┬────────────────┐  │
│  │ Auth & │ Seller │ Orders │ Pay-   │ Messaging      │  │
│  │ Billing│ (Pages)│        │ ments  │ (WhatsApp)     │  │
│  ├────────┼────────┼────────┼────────┼────────────────┤  │
│  │Catalog │Customers│Escrow │ Directory│ Admin        │  │
│  └────────┴────────┴────────┴────────┴────────────────┘  │
├─────────────────────────────────────────────────────────┤
│   Events / Jobs (Redis queues)  ·  Notifications         │
└─────────────────────────────────────────────────────────┘
```

---

## 2. Full Directory Structure

```
mypage-ng/
├── app/
│   ├── Domains/                        # ⭐ Core of the app (domain-first)
│   │   ├── Auth/
│   │   │   ├── Actions/
│   │   │   │   ├── RegisterSeller.php
│   │   │   │   ├── LoginSeller.php
│   │   │   │   └── VerifyEmail.php
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── RegisterController.php
│   │   │   │   ├── LoginController.php
│   │   │   │   ├── PasswordResetController.php
│   │   │   │   └── EmailVerificationController.php
│   │   │   ├── Requests/
│   │   │   │   ├── RegisterRequest.php
│   │   │   │   └── LoginRequest.php
│   │   │   ├── Events/
│   │   │   │   └── SellerRegistered.php
│   │   │   └── Policies/               # (auth mostly gates)
│   │   │
│   │   ├── Seller/                     # Seller accounts & store pages
│   │   │   ├── Models/
│   │   │   │   ├── Seller.php
│   │   │   │   ├── StorePage.php
│   │   │   │   ├── StoreTheme.php
│   │   │   │   ├── DomainBinding.php
│   │   │   │   └── VerificationBadge.php
│   │   │   ├── Actions/
│   │   │   │   ├── CreateStorePage.php
│   │   │   │   ├── UpdateStorePage.php
│   │   │   │   ├── PublishStorePage.php
│   │   │   │   ├── UnpublishStorePage.php
│   │   │   │   ├── UpdateStoreTheme.php
│   │   │   │   └── BindCustomDomain.php
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── OnboardingController.php
│   │   │   │   │   ├── StorePageController.php
│   │   │   │   │   ├── ThemeController.php
│   │   │   │   │   └── DomainController.php
│   │   │   │   └── Livewire/          # Page builder UI
│   │   │   │       ├── PageBuilder.php
│   │   │   │       ├── ThemePicker.php
│   │   │   │       └── PagePreview.php
│   │   │   ├── Services/
│   │   │   │   └── SlugResolver.php    # username.mypage.ng or custom domain
│   │   │   ├── Events/
│   │   │   │   ├── StorePagePublished.php
│   │   │   │   └── StorePageSuspended.php
│   │   │   └── Policies/
│   │   │       └── StorePagePolicy.php
│   │   │
│   │   ├── Catalog/                    # Products, inventory, categories
│   │   │   ├── Models/
│   │   │   │   ├── Product.php
│   │   │   │   ├── ProductImage.php
│   │   │   │   ├── ProductVariant.php
│   │   │   │   ├── Category.php
│   │   │   │   └── InventoryLog.php
│   │   │   ├── Actions/
│   │   │   │   ├── CreateProduct.php
│   │   │   │   ├── UpdateProduct.php
│   │   │   │   ├── BulkImportProducts.php
│   │   │   │   ├── AdjustStock.php
│   │   │   │   └── SyncWhatsAppCatalog.php
│   │   │   ├── Http/Controllers/
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── CategoryController.php
│   │   │   │   └── InventoryController.php
│   │   │   ├── Http/Livewire/
│   │   │   │   ├── ProductForm.php
│   │   │   │   ├── ProductTable.php
│   │   │   │   └── StockAdjuster.php
│   │   │   ├── Jobs/
│   │   │   │   └── SyncProductToWhatsApp.php
│   │   │   └── Policies/
│   │   │       └── ProductPolicy.php
│   │   │
│   │   ├── Orders/                     # Order inbox (core value!)
│   │   │   ├── Models/
│   │   │   │   ├── Order.php
│   │   │   │   ├── OrderItem.php
│   │   │   │   ├── OrderStatusHistory.php
│   │   │   │   └── Cart.php             # abandoned carts
│   │   │   ├── Enums/
│   │   │   │   ├── OrderStatus.php       # pending→paid→processing→shipped→delivered→cancelled/refunded
│   │   │   │   └── PaymentStatus.php
│   │   │   ├── Actions/
│   │   │   │   ├── CreateOrderFromWhatsApp.php  # parses wa.me prefilled messages
│   │   │   │   ├── CreateOrderFromCheckout.php
│   │   │   │   ├── UpdateOrderStatus.php
│   │   │   │   └── RecordAbandonedCart.php
│   │   │   ├── Http/Controllers/
│   │   │   │   ├── OrderController.php
│   │   │   │   └── CheckoutController.php      # public
│   │   │   ├── Http/Livewire/
│   │   │   │   ├── OrderInbox.php
│   │   │   │   └── OrderDetailPanel.php
│   │   │   ├── Jobs/
│   │   │   │   ├── ParseWhatsAppOrderMessage.php
│   │   │   │   └── RemindAbandonedCart.php
│   │   │   ├── Events/
│   │   │   │   ├── OrderCreated.php
│   │   │   │   ├── OrderPaid.php
│   │   │   │   └── OrderDelivered.php
│   │   │   └── Policies/
│   │   │       └── OrderPolicy.php
│   │   │
│   │   ├── Payments/                   # Paystack + Flutterwave + escrow
│   │   │   ├── Models/
│   │   │   │   ├── Transaction.php
│   │   │   │   ├── Payout.php
│   │   │   │   ├── EscrowAccount.php
│   │   │   │   ├── Wallet.php
│   │   │   │   └── WalletLedger.php     # double-entry ledger
│   │   │   ├── Services/
│   │   │   │   ├── Contracts/
│   │   │   │   │   ├── PaymentGatewayInterface.php
│   │   │   │   │   └── EscrowProviderInterface.php
│   │   │   │   ├── PaystackGateway.php
│   │   │   │   ├── FlutterwaveGateway.php
│   │   │   │   ├── EscrowService.php
│   │   │   │   └── CommissionCalculator.php   # 1–1.5% platform take
│   │   │   ├── Actions/
│   │   │   │   ├── InitializePayment.php
│   │   │   │   ├── VerifyPaymentWebhook.php
│   │   │   │   ├── ReleaseEscrow.php
│   │   │   │   └── RequestPayout.php
│   │   │   ├── Http/Controllers/
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── WebhookController.php       # paystack/flutterwave
│   │   │   │   └── PayoutController.php
│   │   │   ├── Jobs/
│   │   │   │   ├── ProcessPayout.php
│   │   │   │   └── ReleaseAutoEscrow.php        # T+N auto-release
│   │   │   └── Events/
│   │   │       ├── PaymentConfirmed.php
│   │   │       └── PayoutProcessed.php
│   │   │
│   │   ├── Customers/                  # Auto-built CRM ("customer book")
│   │   │   ├── Models/
│   │   │   │   ├── Customer.php
│   │   │   │   ├── CustomerNote.php
│   │   │   │   └── CustomerSegment.php
│   │   │   ├── Actions/
│   │   │   │   ├── UpsertCustomerFromOrder.php
│   │   │   │   ├── MergeCustomers.php
│   │   │   │   └── SegmentCustomers.php
│   │   │   ├── Http/Controllers/
│   │   │   │   └── CustomerController.php
│   │   │   └── Http/Livewire/
│   │   │       └── CustomerTable.php
│   │   │
│   │   ├── Messaging/                  # WhatsApp Cloud API, broadcasts
│   │   │   ├── Models/
│   │   │   │   ├── WhatsAppConnection.php
│   │   │   │   ├── MessageTemplate.php
│   │   │   │   ├── BroadcastCampaign.php
│   │   │   │   └── BroadcastRecipient.php
│   │   │   ├── Services/
│   │   │   │   ├── WhatsAppCloudApiClient.php
│   │   │   │   ├── MessageCostCalculator.php   # ⭐ post-Oct-2026 pricing aware
│   │   │   │   ├── TemplateClassifier.php       # utility vs marketing tagging
│   │   │   │   └── OptOutManager.php
│   │   │   ├── Actions/
│   │   │   │   ├── SendOrderUpdateMessage.php
│   │   │   │   ├── QueueBroadcast.php
│   │   │   │   └── GenerateWaMeLink.php         # free deep links
│   │   │   ├── Jobs/
│   │   │   │   ├── SendBroadcastChunk.php        # throttled batches
│   │   │   │   └── SyncWhatsAppCatalogItem.php
│   │   │   ├── Http/Controllers/
│   │   │   │   ├── BroadcastController.php
│   │   │   │   └── WebhookController.php         # WhatsApp inbound messages
│   │   │   └── Http/Livewire/
│   │   │       └── BroadcastComposer.php          # live cost estimate in ₦
│   │   │
│   │   ├── Billing/                    # Subscriptions & verified badges
│   │   │   ├── Models/
│   │   │   │   ├── Subscription.php
│   │   │   │   ├── Plan.php
│   │   │   │   └── BadgeApplication.php
│   │   │   ├── Services/
│   │   │   │   └── SubscriptionManager.php       # paystack plans / manual transfer
│   │   │   ├── Http/Controllers/
│   │   │   │   ├── SubscriptionController.php
│   │   │   │   └── BadgeController.php
│   │   │   └── Jobs/
│   │   │       └── ExpireSubscriptions.php
│   │   │
│   │   ├── Directory/                  # Discovery layer (Phase 3)
│   │   │   ├── Models/
│   │   │   │   └── DirectoryListing.php
│   │   │   ├── Actions/
│   │   │   │   ├── IndexStoreForSearch.php
│   │   │   │   └── RefreshListing.php
│   │   │   ├── Http/Controllers/
│   │   │   │   └── DirectoryController.php
│   │   │   └── Jobs/
│   │   │       └── ReindexDirectory.php
│   │   │
│   │   └── Admin/                      # Internal ops panel
│   │       ├── Http/Controllers/
│   │       │   ├── AdminAuthController.php
│   │       │   ├── SellerAdminController.php
│   │       │   ├── TransactionAdminController.php
│   │       │   ├── DisputeAdminController.php
│   │       │   └── BadgeReviewController.php
│   │       ├── Http/Livewire/
│   │       │   ├── SellerModerationTable.php
│   │       │   ├── DisputeResolver.php
│   │       │   └── PlatformMetrics.php
│   │       └── Actions/
│   │           ├── SuspendStorePage.php
│   │           ├── ApproveBadge.php
│   │           └── ResolveDispute.php
│   │
│   ├── Shared/                         # Cross-domain utilities
│   │   ├── Support/
│   │   │   ├── Money.php                # kobo-safe integer math
│   │   │   ├── NgnFormatter.php
│   │   │   └── PhoneNumber.php          # normalize Nigerian numbers
│   │   ├── Rules/
│   │   │   ├── NigerianPhone.php
│   │   │   └── SafeSlug.php
│   │   ├── Middleware/
│   │   │   ├── EnsureSellerIsActive.php
│   │   │   ├── EnsureSubscriptionValid.php
│   │   │   └── TrackAffiliateReferral.php
│   │   └── Traits/
│   │       ├── BelongsToSeller.php
│   │       └── HasPublicSlug.php
│   │
│   ├── Console/
│   │   └── Commands/
│   │       ├── SyncWhatsAppCatalogCommand.php
│   │       ├── ReleaseDueEscrowsCommand.php
│   │       └── GenerateSitemapCommand.php
│   │
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── DomainServiceProvider.php     # binds gateway interfaces
│   │
│   └── Http/
│       └── Middleware/                   # only truly global middleware
│           └── SecurityHeaders.php
│
├── bootstrap/
│   ├── app.php                           # Laravel 11/12/13 style: middleware, routes, schedules here
│   └── providers.php
│
├── config/
│   ├── app.php
│   ├── database.php
│   ├── queue.php
│   ├── services.php                      # paystack, flutterwave, whatsapp keys
│   ├── billing.php                       # plan prices, commission rates
│   └── mypage.php                        # platform-level config (fees, escrow T+N)
│
├── database/
│   ├── migrations/                       # organized by domain prefix:
│   │   ├── 0001_01_01_000001_create_sellers_and_store_pages.php
│   │   ├── 0001_01_01_000002_create_products_and_inventory.php
│   │   ├── 0001_01_01_000003_create_orders_and_items.php
│   │   ├── 0001_01_01_000004_create_transactions_seller_gateways_store_reports.php
│   │   ├── 0001_01_01_000005_create_customers_and_crm.php
│   │   ├── 0001_01_01_000006_create_messaging_tables.php
│   │   ├── 0001_01_01_000007_create_billing_tables.php
│   │   └── 0001_01_01_000008_create_directory_and_admin_tables.php
│   ├── factories/                        # mirror model names
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── DemoSellerSeeder.php
│       └── PlanSeeder.php
│
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   │   ├── app.blade.php              # seller dashboard shell
│   │   │   ├── admin.blade.php
│   │   │   ├── storefront.blade.php        # public page shell (theme-aware)
│   │   │   └── guest.blade.php            # landing/auth shell
│   │   ├── pages/                         # landing, pricing, directory, legal
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── store-page/
│   │   ├── products/
│   │   ├── orders/
│   │   ├── customers/
│   │   ├── payments/
│   │   ├── messaging/
│   │   ├── billing/
│   │   ├── settings/
│   │   ├── storefront/                    # public store page + checkout
│   │   └── admin/
│   ├── css/
│   │   └── app.css                        # Tailwind 4 source
│   └── js/
│       └── app.js                         # Alpine + minimal JS
│
├── routes/
│   ├── web.php                            # dashboard, admin, public pages
│   ├── storefront.php                     # {slug}.mypage.ng + custom domains
│   ├── checkout.php
│   ├── api.php                            # Sanctum API
│   └── webhooks.php                       # paystack/flutterwave/whatsapp
│
├── storage/  public/  tests/
├── tests/
│   ├── Unit/Domains/                      # mirrors app/Domains
│   ├── Feature/Domains/
│   ├── Integration/                       # gateway sandbox tests
│   └── Pest.php
│
├── .env.example
├── composer.json
├── package.json
├── tailwind.config.js  (or css-first config)
├── vite.config.js
└── README.md
```

---

## 3. Database Schema (core tables)

```
sellers ──1:N── store_pages ──1:N── products ──1:N── product_variants / product_images
store_pages ──1:N── orders ──1:N── order_items
orders ──1:1── transactions            (buyer → seller, via the seller's gateway)
sellers ──1:N── seller_gateways        (one per gateway; secret_key encrypted)
store_pages ──1:N── store_reports      (buyer reports about a store)
customers (unique per seller+phone) ──1:N── orders
whatsapp_connections ──1:N── broadcast_campaigns ──1:N── broadcast_recipients
subscriptions (seller N:1 plans)        (seller → mypage.ng, platform gateway)
```

The withdrawn tables — `escrow_accounts`, `wallets`, `wallet_ledger`, `payouts` —
are gone. Buyer money never enters a platform account, so there is nothing to hold,
ledger or pay out.

**Key invariants (enforced in service layer + DB constraints):**
- All money stored as **integer kobo**. No floats. Ever.
- ~~Every wallet movement writes two ledger rows.~~ Withdrawn with the ledger.
- A buyer payment is only ever confirmed by a gateway transaction. **No manual
  "mark as paid"**, and bank transfer is not an accepted method.
- A seller's secret key is never fillable, never serialised, never queued, and is
  proven against the gateway before it is stored.
- Order status changes always write an `order_status_histories` row.
- WhatsApp broadcasts require opt-in flag on customer; opt-outs honored before send.

---

## 4. Build Phases (mapped to the product plan)

| Phase | Scope | Exit criteria |
|---|---|---|
| **P0 — Foundation (wk 1–2)** | Auth, seller onboarding, store page CRUD, slug routing (`{slug}.mypage.ng`), Tailwind/Livewire shell | Seller can create + publish a page |
| **P1 — Catalog (wk 3–4)** | Products, images, variants, categories, stock counts, public storefront render + wa.me order button | Buyer can browse page and send order via WhatsApp |
| **P2 — Payments (wk 5–7)** | Paystack + Flutterwave init/verify, per-tenant webhooks (idempotent), checkout on the seller's subdomain against the seller's own credentials, encrypted seller gateway settings | First real ₦ collected end-to-end, into the seller's account |
| **P3 — Order Inbox + CRM (wk 8–10)** | Parse WhatsApp prefilled messages into orders, order status pipeline, auto customer-book, inventory decrement | The ₦3,500/mo product exists |
| **P4 — Escrow + Billing (wk 11–13)** | Escrow hold/release/dispute, subscriptions, verified badge application | Two revenue streams live |
| **P5 — Messaging (wk 14–16)** | WhatsApp Cloud API connect, templates, cost-aware broadcast composer, wa.me fallback | Broadcasts with live ₦ cost estimate |
| **P6 — Directory + SEO (wk 17–20)** | Public directory, sitemaps, OG tags, custom domains, service-booking pages w/ deposits | Discovery layer live |

---

## 5. Non-Negotiable Engineering Rules

1. **Gateways behind an interface.** `PaymentGatewayInterface` — Paystack/Flutterwave are swappable; never call them from controllers.
2. **Idempotent webhooks.** Every webhook stores a unique event id; retries are safe. Signature verification mandatory.
3. **Queues for everything slow.** Messaging, catalog sync, payouts, sitemap → Redis queues + Horizon.
4. **Feature flags from day one.** `mypage.php` config + DB flags for broadcasts, directory, custom domains — ship dark, enable per cohort. Escrow is no longer a flag; it is removed.
5. **Multi-tenancy discipline.** Every catalog/order/customer query scoped through `BelongsToSeller` global scope; store page lookups resolved by slug/domain middleware.
6. **Audit everything money touches.** Ledger rows immutable; admin actions logged.
7. **Pest tests for money paths.** Payment init/verify, cross-tenant webhook isolation, seller credential handling, the unverified-seller warning at checkout — 100% feature coverage, no exceptions. Escrow, commission and payout tests go with the code they tested.

---

## 6. Environments & Deploy

```
local → staging (mypage-stg.ng) → production (mypage.ng)
```

- Laravel Forge + Hetzner/DO (Lagos region if available, else EU) · Cloudflare (DNS + WAF + subdomain wildcard `*.mypage.ng`)
- MySQL managed · Redis managed · S3-compatible storage (images) · Backups nightly
- Horizon for queues · Telescope (staging only) · Sentry for errors
- GitHub Actions: lint → tests → deploy staging → manual promote → production

### `.env.example` key vars
```
APP_URL=https://mypage.ng
SESSION_DOMAIN=.mypage.ng
SANCTUM_STATEFUL_DOMAINS=mypage.ng
PAYSTACK_SECRET=  PAYSTACK_PUBLIC=
FLUTTERWAVE_SECRET=  FLUTTERWAVE_PUBLIC=
WHATSAPP_CLOUD_TOKEN=  WHATSAPP_BUSINESS_ID=  WHATSAPP_WEBHOOK_VERIFY_TOKEN=
PLATFORM_COMMISSION_BPS=150
ESCROW_AUTO_RELEASE_DAYS=7
```
