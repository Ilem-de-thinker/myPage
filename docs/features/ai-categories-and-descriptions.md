# AI categories and descriptions

- Slug: ai-categories-and-descriptions
- Status: Partial
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 1, Medium 4, Low 1
- Fixed: 2 of 6

Generate category suggestions and product/service descriptions with AI; apply or undo.

## 1. What it does

Bulk-describe products/services and auto-create a category structure from catalogue contents,
with undo. Jobs are reserved against credits; apply/undo is scoped to the store page.

## 2. Map

- Routes: `products.generate-descriptions`, `products.apply-descriptions/{task}`, `products.undo-descriptions/{task}`, `services.*` twins, `categories.generate-ai`, `categories.apply-ai/{task}`, `categories.undo-ai/{task}`
- Controllers: `ProductController`, `ServiceController`, `CategoryController`
- Jobs: `RunAiOperation` with `_no_draft` inputs
- Plan gating: `AiLimits::throttle/assertWithinTaskCap/assertCanQueue/availableSlots/maxBatchItems`, per-item credit reservations

## 3. How it works

Batch of up to `maxBatchItems` items each queued as an operation; output validated against schema
(`seo_title`, `seo_description`, `description`, `insufficient_info`, `flags`); apply writes to the
product/service and records previous values in task summary for undo.

## 4. Findings

### [HIGH] Category AI loads the entire catalogue into the prompt unpaginated
- Where: `CategoryController::generateWithAi` (≈ lines 168-175)
- What: every product name is interpolated into the prompt; large catalogues blow the token budget and leak full catalogue contents to the provider beyond what's needed.
- Fix: sample the latest/most-used N items (e.g. 100) or aggregate by existing categories; cap prompt to a bounded summary.
- Resolution: Proposed, needs approval (changes AI behavior).

### [MEDIUM] Unmatched `category_name` silently maps to `category_id = null` on apply
- Where: `CategoryController::applyGeneratedCategories`
- What: an AI-suggested category that finds no match leaves products uncategorised with no feedback.
- Fix: surface "N items matched, M did not" in the result; optionally auto-create the missing category (firstOrCreate like the import path does).
- Resolution: Proposed, needs approval.

### [MEDIUM] Generated category `position` set from `categories()->count()` under concurrency
- What: two concurrent creates can get the same position — cosmetic ordering glitch only.
- Resolution: Won't fix (cosmetic); or use a per-store transaction counter.

### [MEDIUM] Description generation interpolation is N+1 + unpaginated batch
- Where: `ProductController::generateDescriptions` (`$product->category?->name` per item, no `with('category')`), `applyDescriptions` (per-op `Product::find`)
- Fix: `->with('category')` and keyed lookups; bounded by `maxBatchItems` so it's real but small.
- Resolution: Proposed (S).

### [MEDIUM] Credit check for batch description generation is pre-flight only
- Consistent with the rest of the synchronous AI surface — see ai-credits-and-limits.md (TOCTOU, reservation covers queued path so only overshoot is the theoretical parallel-apply).
- Resolution: Accepted / documented.

### [LOW] Undo descriptors restore previous description but not `ai_metadata.seo` when absent
- If product had no `ai_metadata` before, undo writes `null` — acceptable, and `seo` block is rebuilt wholesale — consistent.

## 5. Fixes applied

- Finding: Unmatched AI category names applied as `category_id = null` → apply now `firstOrCreate`s the category for the store before attaching products (store-scoped). Files: `AiDraftController.php`.
- None in this pass (all fixes pending owner decisions above).

## 6. Proposed, needs approval

- Cap/summarise catalogue slice for category generation (S).
- Eager-load category in description generation (S).
- Pre-flight vs per-row credit check for batch description generation (S).

## 7. Checked and fine

- Apply/undo page-scoped; reservations per item; overwrite flag honored; undo deletes only created-and-empty categories; credit pre-flight 429s.

## 8. Unverified / needs manual check

- Description-quality regression after prompt changes in flight (uncommitted `resources/prompts`?).

## 9. Open questions

- Should category generation auto-create missing categories or leave products uncategorised?
