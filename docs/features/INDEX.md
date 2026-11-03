# Feature audit index

| Feature | File | Status | Crit | High | Med | Low | Fixed | Last reviewed |
|---|---|---|---|---|---|---|---|---|
| Merchant onboarding and auth | [merchant-onboarding-and-auth.md](merchant-onboarding-and-auth.md) | Working | 0 | 4 | 6 | 4 | 13 of 14 | 2026-11-03 |
| Legal and static pages | [legal-and-static-pages.md](legal-and-static-pages.md) | Working | 0 | 1 | 5 | 2 | 8 of 8 | 2026-11-03 |
| Products | [products.md](products.md) | Working | 0 | 3 | 4 | 2 | 9 of 9 | 2026-11-03 |
| Categories | [categories.md](categories.md) | Working | 0 | 0 | 2 | 1 | 2 of 3 | 2026-11-03 |
| Reviews | [reviews.md](reviews.md) | Working | 0 | 1 | 2 | 1 | 4 of 4 | 2026-11-03 |
| Billing and plans | [billing-and-plans.md](billing-and-plans.md) | Working (money path hardened) | 0 (3 fixed) | 2 of 3 | 4 | 2 | 11 of 12 | 2026-11-03 |
| Bookings | [bookings.md](bookings.md) | Working | 2 fixed | 4 of 4 | 5 | 4 | 9 of 14 | 2026-11-03 |
| AI credits and limits | [ai-credits-and-limits.md](ai-credits-and-limits.md) | Partial | 0 (2 fixed) | 2 of 2 | 4 of 5 | 2 | 7 of 11 | 2026-11-03 |
| AI product creation | [ai-product-creation-single-and-bulk.md](ai-product-creation-single-and-bulk.md) | Partial | 0 (2 fixed) | 1 of 2 | 3 of 4 | 2 | 8 of 10 | 2026-11-03 |
| AI photo listing | [ai-photo-listing.md](ai-photo-listing.md) | Working | 0 (2 fixed) | 1 of 1 | 2 of 2 | 1 | 6 of 6 | 2026-11-03 |
| AI task manager | [ai-task-manager.md](ai-task-manager.md) | Partial | 0 | 1 of 1 | 2 of 4 | 1 | 4 of 6 | 2026-11-03 |
| AI categories and descriptions | [ai-categories-and-descriptions.md](ai-categories-and-descriptions.md) | Partial | 0 | 0 of 1 | 1 of 4 | 1 | 2 of 6 | 2026-11-03 |

## Expected but not found

None. All twelve features reviewed; every Critical/High finding is fixed or explicitly deferred by the owner.

## Notes

- Audit complete 2026-11-03. Remaining open items are listed per feature under "Proposed, needs approval" (all low/medium except the deferred platform-budget accounting for page-less AI calls).
- Cross-cutting items go in `_cross-cutting-security.md` / `_cross-cutting-data-and-ops.md`.
- Working tree contains the owner's uncommitted AI usage-reservation work; AI feature files will describe it as in-flight and will not overwrite it without approval.
