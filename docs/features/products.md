# Products

- Slug: products
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 3, Medium 4, Low 2
- Fixed: 9 of 9

## 1. What it does

The seller's catalogue: product CRUD, variants, stock adjustment, image uploads and processing,
CSV import, bulk operations, and plan-limit (max products) enforcement.

## 2. Map

- Routes: `products.*` incl. `products.variants.*` (shallow resource, `show` excluded), `products.import`, `products.bulk-destroy`, `products.stock`, `products.generate-descriptions` (see ai-categories-and-descriptions.md), routes/web.php:345-352.
- Controllers: `ProductController`, `ProductVariantController`, `ProductImportController`, `CategoryController` (see categories.md).
- Jobs: `ProcessProductImage`, `ProcessProductImport`.
- Models and tables: `Product`, `ProductVariant`, `ProductImage`, `ProductImport`, `InventoryLog`; migrations `...031802-031806`, `2026_10_24_121000`.
- Plan gating: `SubscriptionEntitlements::assertCanCreateListing` (products + services count vs `plans.max_products`) on store/create, import rows, AI apply paths.

## 3. How it works

Create: validate → `Money::toKobo` → `Product::create` → per-image `store("products/{id}", 'public')` + `ProcessProductImage` dispatch. AdjustStock uses `lockForUpdate` inside a transaction and writes `inventory_logs`. Delete now removes image/variant files from the public disk with the rows. Imports: CSV streamed row-by-row, per-row entitlement check, rows with unparseable prices are quarantined to the error list instead of importing as ₦0.

## 4. Findings

### [HIGH] Cross-tenant category assignment (IDOR)
- Where: app/Domains/Catalog/Http/Controllers/ProductController.php:300 (now scoped)
- What: `category_id` validated only as `exists:categories,id` — a seller could attach products to another seller's category.
- Impact: leaks existence/counts, breaks tenant isolation.
- Fix: `Rule::exists(...)->where('store_page_id', $page->id)`; regression test added.
- Resolution: Fixed.

### [HIGH] Import job crashed on ragged CSVs and never recovered
- Where: ProcessProductImport.php:34-36 (before fix)
- What: `fopen()` unchecked; `array_combine` outside per-row try → `ValueError` on rows wider than the header (or empty header) → job died with no `failed()` handler → import stuck at "processing" forever, CSV leaked.
- Impact: broken imports block the feature and lose the seller's file.
- Fix: unreadable file → failed import with message; rows wider than the header are truncated; `failed()` marks the import failed, reports the exception, deletes the CSV; missing `status` key in a CSV without that column no longer throws.
- Resolution: Fixed.

### [HIGH] Import wrote ₦0 products for unparseable prices
- Where: ProcessProductImport.php:55, Money::toKobo (returns 0 for garbage)
- What: invalid price rows were imported as free products instead of failing.
- Fix: rows whose price parses to ≤ 0 kobo are rejected into the error list; regression test added.
- Resolution: Fixed.

### [MEDIUM] Variant image path was literally broken (`variants/\{id}`)
- Where: ProductVariantController.php:133
- What: `"variants/\{$variant->product_id}"` stores files in a directory named `variants/\{1}`; backslashes in public URLs.
- Fix: correct interpolation string.
- Resolution: Fixed.

### [MEDIUM] Shallow variants resource exposed a 500 on `variants.show`
- Where: routes/web.php (`products.variants` resource)
- What: `GET /variants/{variant}` (`variants.show`) routed to a controller with no `show()` method → unhandled call → 500.
- Fix: `->except(['show'])`.
- Resolution: Fixed.

### [MEDIUM] Orphaned files on product deletion
- Where: ProductController::destroy/bulkDestroy
- What: FK-cascade removed `product_images` *rows* but never the files on the public disk (products, variants' images too).
- Fix: collect image + variant image paths before delete; delete rows, then delete files from the `public` disk. Test added.
- Resolution: Fixed.

### [MEDIUM] Money columns had no upper bound
- Where: ProductController.php validated() price/compare_at
- What: `price`/`compare_at` unbounded; `price_kobo` is unsigned int (≈ ₦42.9m max) → overflow 500s.
- Fix: `max:100000000` matching the existing `delivery_fee` convention.
- Resolution: Fixed.

### [LOW] No DB unique index on variant SKU; check-then-create race
- Where: ProductVariant migration 031805; ProductVariant::isSkuTaken
- What: concurrent SKU assignment can mint duplicates; app-only check.
- Fix: unique index (needs migration — see proposed) or transaction + retry.
- Resolution: Proposed, needs approval (schema change).

### [LOW] Plan limit check is check-then-insert
- Where: SubscriptionEntitlements::assertCanCreateListing
- What: two concurrent creates at `max-1` both pass.
- Fix: lock the subscription row or re-check inside the insert transaction.
- Resolution: Proposed, needs approval (billing-adjacent).

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Deletion was irreversible for accidentally removed products → `Product` uses `SoftDeletes`; destroy/bulk destroy soft-delete and keep image files; `PurgeDeletedProducts` (scheduled 03:30 daily) hard-deletes rows and files after 30 days; privacy requests use `PurgeSellerCatalogue` to force-delete immediately. Files: migration `2026_11_03_164659_add_soft_deletes_to_products.php`, `Product.php`, `ProductController.php`, `PurgeDeletedProducts.php`, `ProductImageFiles.php`, `routes/console.php`. Tests: `ProductsTest` (soft delete keeps files, purge job, hard delete on request).
- Finding: SKU uniqueness was global, so two stores could not use the same real-world SKU → variants carry `store_page_id`, a composite unique index enforces per-store uniqueness, and the availability check is store-scoped. Backfill included. Files: migration `2026_11_03_164701_scope_product_variant_skus_per_store.php`, `ProductVariant.php`, `ProductVariantController.php`. Tests: `ProductVariantsTest` (per-store SKU reuse).
- Finding: No image-count cap → `MAX_IMAGES = 8` enforced on create and against the existing count on update. Files: `ProductController.php`. Tests: `ProductsTest` (cap).
- Finding: Plan-limit check was check-then-insert → assertion and insert now share one transaction holding the subscription `lockForUpdate`, with a fail-closed entry-plan fallback. Files: `SubscriptionEntitlements.php`, `ProductController.php`, `ServiceController.php`, `ProcessProductImport.php`, `AiDraftController.php`. Tests: `PlanLimitFailClosedTest`, `TrialPlanTest`, `SubscriptionLimitsTest`.
- Variant image path interpolation; variants `show` route excluded; `category_id` tenant-scoped rule; `price`/`compare_at` upper bounds — ProductController.php, ProductVariantController.php, routes/web.php; tests: ProductsTest (cross-tenant category), variants tests pass.
- Import hardening (missing file, ragged rows, `failed()` sweeper, zero-price quarantine, missing status column) — ProcessProductImport.php; tests: ProductImportTest (3 tests).
- Orphan file cleanup with proof-by-test — ProductController.php + ProductsTest (image file deleted on destroy).
- Row-wide `status` CSV key bug — same job; covered by ragged-row test.

## 6. Proposed, needs approval

Nothing outstanding from this audit.

## 7. Checked and fine

- Tenant checks on edit/update/destroy/adjustStock/variants/bulkDestroy (404/403 as appropriate); bulk destroy scoped to page.
- Implicit binding + seller global scope on all product routes.
- Money stored in integer kobo at the DB edge.
- `AdjustStock` locks the row inside a transaction and logs to `inventory_logs`.
- Image MIME validation by real file (svg excluded), filenames random-hashed, images dispatched to a processing job.

## 8. Unverified / needs manual check

- `ProcessProductImage` leaves the original in place when GD cannot decode (HEIC) — confirm deployed GD/Imagick support, or accept as documented limitation.
- Public `storage` symlink on the deploy host (shared hosting vs VPS).

## 9. Open questions

- Should deleting a product really destroy its images, or soft-delete for recoverability?
- Is a global (all-stores) unique SKU the desired rule, or per store?
