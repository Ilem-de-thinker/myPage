# MyPage.ng discovery directory

MyPage.ng is a discovery layer. Directory pages expose only approved, active, published stores and opted-in listings. Product and service cards end in the merchant storefront through `/go/{type}/{id}`; the platform does not provide buyer accounts, checkout, payment, order tracking, invoicing or delivery.

## Architecture

- `DirectoryCatalogQuery` is the shared database retrieval boundary for stores, products and services.
- `PublicDirectoryController` owns directory pages and the lightweight store profile.
- `DirectoryRedirectController` resolves a live merchant URL, records a privacy-respecting click, adds UTM markers, and falls back to the directory when an item or store is no longer public.
- `MarketplaceAssistantController` asks the configured `marketplace_intent` task for filters, retrieves real records with `DirectoryCatalogQuery`, and returns only database-backed results. AI failure falls back to keyword search.
- `SyncDirectorySearchIndex` is dispatched after store, product and service changes. Embeddings are optional; keyword retrieval remains the fallback.

## Listing and moderation

Merchants opt into discovery with `is_directory_opted_in`. Product and service visibility is independently controlled by `is_directory_listed`. Admins can approve, remove, feature and unfeature stores from the admin Stores screen. Suspended stores are excluded by the existing `discoverable` scope.

Featured placement is represented by the existing `is_featured` field and should be connected to subscription-plan rules when commercial placement rules are finalized.

## Redirect and analytics

Every store, product, service and WhatsApp action uses the redirect route. Events contain the merchant, item where applicable, source, query/category metadata, device and a truncated-purpose visitor hash. Repeated directory clicks from the same visitor within ten minutes are ignored. The merchant analytics screen includes the seven-day directory click total and top sources.

## Help me buy

The public landing page and directory pages mount one shared `directory.help-me-buy` component. It is a mobile bottom sheet and desktop side panel. It has a daily anonymous cap, a disclosure, rate limiting, and prompt-injection guardrails. Candidate names, prices, locations and URLs are always selected from the database response; the model cannot create a listing.

## Adding a new directory surface

Add the route/controller view to the public platform shell, use `DirectoryCatalogQuery` for retrieval, and send outbound actions through `directory.go`. If the surface needs AI, add a config task and prompt, then ground its response against the catalog before rendering it.
# Directory architecture

The public directory is discovery only. Product and service actions leave through the internal `/go/{type}/{id}` redirect, which records a privacy-respecting referral event before sending the visitor to the merchant's own storefront. The platform does not create carts, orders, invoices, payments or buyer accounts.

## Search and sync

`DirectoryCatalogQuery` provides database-backed keyword, category, price, state, city, verified and nearest-location filtering. `SyncDirectorySearchIndex` is dispatched by model observers and removes embeddings whenever a listing is no longer public. Run the database queue worker in `deploy/supervisor/mypage-queue.conf`; Redis is not required.

AI intent and ranking are optional layers over the same database retrieval. Prices, availability, store names and outbound URLs always come from retrieved records.

## Featured placement and moderation

Store directory opt-in remains a seller setting and admin moderation remains required. Featured placement is limited to active, trialing or in-grace subscriptions on the configured Growth/Pro plan slugs in `config/billing.php`; `directory:sync-featured` removes stale placement daily. Store reports and item-level product/service reports are stored in `store_reports` for the admin queue.

## Redirects and tracking

Store, product, service and WhatsApp actions use `DirectoryRedirectController`. Events include merchant, item, source, query/category and device metadata, with short-window visitor de-duplication. Stale items fall back to the merchant store; stale stores fall back to the directory.

## Production operations

Use database cache (`CACHE_STORE=database`) and database queues (`QUEUE_CONNECTION=database`). Install the cron entry and supervise the queue worker. Run `php artisan storage:link` during deployment. Configure `AI_ENABLED=true` and the provider keys used by the task map in `config/ai.php`; the application records failed AI tasks, queue failures, redirect failures and webhook dispatch failures to the normal application log.
