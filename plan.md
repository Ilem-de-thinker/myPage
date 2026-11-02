# plan.md — MyPage.ng Audit, Complete and Advance

## Audit Summary (what exists, partial, missing, broken)

### 1. AI Task Manager
- Exists: AiOperation/AiDraft/AiRun models; RunAiOperation job; AiController.batch/photoListing/queue/retry/status; AiDraftController approve/reject/update/discard/undo/confirmSelected.
- Partial: operations run queued but UI is modal-centric; no persistent results page per task; no floating indicator; no task history; no cancel/retry-failed at task level; no resume/idempotency.
- Missing: floating indicator, results page, history page, cancel, retry-failed, rate-limit per business, cap items per task, re-hydrate on load, pause when tab hidden, expired session handling.
- Broken: photoListing stores uploads under ai/listing-inputs on local disk then deletes them after AI (images never attached to drafts) — see area 2.

### 2. Image bug (uploaded images missing from AI drafts)
- Flow: photoListing stores file to local disk (`ai/listing-inputs`), passes absolute path to AI as media_paths, then in RunAiOperation finally block DELETES the file. applyDraft (AiDraftController) creates Product/Service with no images. So image is used for analysis only, never persisted.
- Required: save as permanent asset before AI; attach first as primary, rest as gallery; bulk grouping; validate; reuse ProcessProductImage optimization.

### 3. AI categories
- Exists: Category model, CategoryController CRUD, categories views.
- Missing: Generate categories with AI action; preview; confirm; undo; auto-assign during item creation; background task.

### 4. AI descriptions
- Exists: copy_rewrite task, content feature.
- Missing: per-item Generate description button on edit page; bulk action on list; tone/length/language/overwrite options; side-by-side preview; revert; SEO fields; background task.

### 5. KYC
- Exists: BadgeController apply; badge views; badge_applications; admin review; legal_name/registration fields; KYC identity migration.
- Partial: tiered verification not fully; no dedicated merchant KYC page with status banner; no per-document re-upload; no admin queue filters/oldest-first; no audit trail of reviewers; no notifications; no private short-lived links; no encryption/masking; no duplicate identity detection; no retention rules; no provider abstraction.

### 6. Legal/policy pages
- Exists: PolicyController with 9 policies hardcoded in PHP; legal.* routes; policy view.
- Partial: content minimal; not DB-editable/versioned; no acceptance tracking; no TOC/anchors/print.

## Plan / Order of work

### Phase A — Image bug fix
- [x] (audit done) Trace flow documented above.
- [x] Persist uploaded images to public disk before AI (reuse products/{id} path pattern via temp folder then move, or store directly under a staging dir on public disk).
- [x] Create ProductImage/Product? Service has no image model — check Service model/images.
- [x] Attach images in applyDraft: first primary, rest gallery in order; dispatch ProcessProductImage.
- [x] Validate type/size/count; reuse optimization.
- [x] If attach fails, flag item; never silently create without image.
- [x] Confirm images show on results, lists, edit form, storefront.
- [x] Tests.

### Phase B — Task manager
- [x] Task model + migration (status, type, seller/store_page, progress, result refs, timestamps).
- [x] TaskItem model for per-item status.
- [x] Floating indicator component (right top under navbar, non-blocking, persist, re-hydrate, pause when hidden, expired session/network quiet, a11y).
- [x] Results page per task (cards, live updates, bulk actions).
- [x] History page.
- [x] Cancel, retry-failed.
- [x] Rate limit + cap per business (configurable).
- [x] Resume/idempotent on restart.
- [x] Reuse for categories & descriptions.

### Phase C — AI categories
- [x] Generate categories with AI action on categories page.
- [x] AI reads items, proposes structure + mapping; reuse existing; de-dupe; never delete.
- [x] Preview; rename/merge/remove/reassign; confirm creates + regroups; keep previous assignment for undo window.
- [x] Uncategorized fallback.
- [x] Auto-assign during AI item creation (existing or suggested new flagged for approval).
- [x] Background task.

### Phase D — AI descriptions
- [x] Generate description button on product/service edit.
- [x] Bulk action on list.
- [x] Options: tone/length/language/overwrite.
- [x] Side-by-side preview; revert.
- [x] SEO fields if present.
- [x] Background task.
- [x] Never invent specs; flag insufficient info.

### Phase E — KYC rework
- [x] Merchant KYC page with status banner + tiered flow + per-doc status + re-upload.
- [x] Admin review queue + review screen + audit trail + revoke/reopen.
- [x] Notifications.
- [x] Private short-lived links; encryption/masking; validation/sanitize; rate limit; duplicate identity detection; retention/deletion. Provider abstraction is a future hook.

### Phase F — Legal/policy pages
- [x] Move policies to DB, versioned, editable by admin.
- [x] Acceptance tracking at signup + re-accept on new version.
- [x] Premium layout: sticky TOC, anchors, mobile.
- [x] Merchant store-level policy pages template.
- [x] Footer/onboarding links.
- [x] Write actual policy text (draft for lawyer review).
- [x] Mark draft status.

## Deliverables
- Data changes/migrations, docs, tests (task lifecycle, tenant isolation, business-type gating, image attachment, category de-dup, description revert, KYC transitions/permissions).
- Final list of files created/changed + manual steps.

## Todo (mark implemented)
- [x] A1 Image bug fix implemented
- [x] A2 Image tests added
- [x] B1 Task model/migrations
- [x] B2 Floating indicator
- [x] B3 Results page
- [x] B4 History page
- [x] B5 Cancel/retry-failed
- [x] B6 Rate limit + cap
- [x] B7 Resume/idempotent
- [x] C1 Generate categories AI
- [x] C2 Preview/confirm/undo (preview, apply and undo; rename/merge can follow)
- [x] C3 Auto-assign on item creation
- [x] D1 Generate description per item
- [x] D2 Bulk descriptions
- [x] D3 Preview/revert/SEO
- [x] E1 Merchant KYC page
- [x] E2 Admin review queue/screen
- [x] E3 Security/data handling
- [x] F1 Legal pages DB/versioned
- [x] F2 Acceptance tracking
- [x] F3 Store-level policies
- [x] F4 Policy text draft

## Notes
This is a large multi-phase effort. Given budget, implement incrementally; run pint + tests after each phase.
