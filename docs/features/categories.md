# Categories

- Slug: categories
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 0, High 0, Medium 2, Low 1
- Fixed: 2 of 3

## 1. What it does

Store-scoped category CRUD with inline creation from the product form, bulk delete,
and AI-generated category suggestions with server-side limit enforcement and undo.

## 2. Map

- Routes: `categories.index/create/store/edit/update/destroy`, `categories.bulk-destroy`, `categories.generate-ai`, `categories.apply-ai/{task}`, `categories.undo-ai/{task}` (routes/web.php:353-357).
- Controllers: `CategoryController`.
- Models and tables: `Category`; unique `(store_page_id, slug)`; `categories.position` for ordering.

## 3. How it works

Inline JSON creation from the product form; full-page CRUD otherwise. AI generation
pre-flights credits, creates `_no_draft` operations, and apply/undo are page-scoped.
Restore deletes only created-and-empty categories.

## 4. Findings

### [MEDIUM] Cannot edit a category without changing its name
- Where: CategoryController::validated (name uniqueness without `->ignore()`)
- What: renaming a category to its existing name failed validation; the "keep slug when name unchanged" branch was unreachable, and the existing test masked it with `assertRedirect()`.
- Fix: `->ignore($category?->id)`; regression test asserts `assertSessionHasNoErrors()` and slug stability.
- Resolution: Fixed.

### [MEDIUM] AI category generation loads all products unpaginated
- Where: CategoryController::generateWithAi (lines ~168-175)
- What: every product in the catalogue is interpolated into the prompt; large catalogues mean oversized prompts and provider cost.
- Fix: cap the sample (e.g. latest 100 names) or summarise by category; needs product decision on prompt content.
- Resolution: Proposed, needs approval (changes AI behavior).

### [LOW] Cross-tenant `category_id` accepted on products (fixed in products.md)
- See products.md — the fix scopes the exists rule to the store page.

## 5. Fixes applied

- Uniqueness rule now ignores the row being updated; regression test added. Files: CategoryController.php, tests/Feature/Seller/CategoriesTest.php. Tests: CategoriesTest passes.

## 6. Proposed, needs approval

- Cap/summarise the product list fed to the category AI (S).

## 7. Checked and fine

- Apply/undo of AI-generated categories are scoped to the store page; restore removes only created-and-empty rows.
- Bulk delete unassigns products (`category_id => null`) rather than deleting them; scoped to page.
- Duplicate names within one store rejected; same name across stores allowed.

## 8. Unverified / needs manual check

- AI category suggestions quality on very large catalogues.

## 9. Open questions

- None.
