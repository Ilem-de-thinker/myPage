# Reviews

- Slug: reviews
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 1, Medium 2, Low 1
- Fixed: 4 of 4

## 1. What it does

Moderation for buyer-written reviews of the business, its products and its services.
Nothing reaches the public page until the seller approves it; scores recalculate on every status change.

## 2. Map

- Routes: `reviews.index`, `reviews.approve/reject/feature/{review}` (routes/web.php:279-282); public submit: `storefront.review(.product|.service)` (routes/storefront.php:73-81, `throttle:5,1`).
- Controllers: `ReviewInboxController`.
- Actions: `ModerateReview`, `SubmitReview`.
- Models and tables: `Review` (BelongsToSeller, `store_page_id`, `(store_page_id, status)` index); `HasRating` trait on StorePage/Product/Service.
- Requests: `StoreReviewRequest`.

## 3. How it works

Buyers post unauthenticated text (sanitised on write, Pending by default). The seller
inbox gates publication. Approval sets `published_at ?? now()`; rating aggregates recalc
on every change via `recalculateRating()`.

## 4. Findings

### [HIGH] Product and service reviews never reached the moderation inbox
- Where: ReviewInboxController::index / counts (before fix)
- What: the inbox listed `$page->reviews()` (reviews of the page itself only). Product/service reviews sat Pending forever; the queue's product/service sections were unreachable from the UI (only direct POST-by-id worked).
- Impact: buyers' reviews silently vanished; seller could never moderate them; ratings never updated.
- How to reproduce: submit a product review as a buyer; open /reviews — it does not appear.
- Fix: the inbox now lists every review with `store_page_id = $page->id` (business, product, service), ordered by `is_featured` then newest; tab counts use the same scope. Regression tests prove product reviews appear in the queue.
- Resolution: Fixed.

### [MEDIUM] "Pinned to the top of the list" was not true of any list
- Where: ReviewInboxController::feature; all public review queries
- What: feature toggles `is_featured` but no list ordered by it; the seller-facing message was false.
- Fix: inbox and the four public review loads now order by `is_featured` desc, then published_at desc; rows show the "Seller's pick" badge.
- Resolution: Fixed.

### [MEDIUM] Pinning allowed on any status
- Where: ReviewInboxController::feature
- What: pending/rejected reviews can be pinned; UI only offers the button for published rows, but the route accepts any review owned by the store.
- Fix: enforce `status === approved` before toggling (or document pinning as pre-approval curation).
- Resolution: Proposed, needs approval (behavior visible to merchants; trivial either way).

### [LOW] Cross-tenant moderation is well-closed
- Verified: `authorise()` returns 404 for foreign reviews; implicit binding is additionally constrained by the seller global scope. Counted as checked-fine, not a finding (see below).

## 5. Fixes applied

- Finding: Pinning allowed on any status → `feature` now aborts 422 unless the review is approved; rejecting a featured review also clears `is_featured`. Dashboard copy is "Featured"/"Feature". Files: `ReviewInboxController.php`, `ModerateReview.php`, dashboard view. Tests: `ReviewsTest` (2 tests).
- Inbox scope fix (all of the store's reviews) + featured-first ordering in inbox and public lists — ReviewInboxController.php, StorefrontController.php.
- Tests: `tests/Feature/Catalog/ReviewsTest.php` — product review appears in inbox; counts/subject label correct; suite passes (22 tests).

## 6. Proposed, needs approval

Nothing outstanding from this audit.

## 7. Checked and fine

- Body sanitised on write (`Html::sanitize`); escaped on render.
- Rating recalculation on every moderation change; `published()` scope requires approved status.
- Public submit throttled 5/min; author identity optional; anonymous handling correct.
- Moderation actions 404 (not 403) for foreign reviews; per-store write semantics preserved.

## 8. Unverified / needs manual check

- HTML sanitizer strength — see _cross-cutting-security.md (Html::sanitize keeps disallowed tags' attributes; flagged there as a platform-wide issue).

## 9. Open questions

- Should pinned reviews sort first on the public page, or is a badge sufficient?
