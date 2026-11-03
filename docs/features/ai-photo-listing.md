# AI photo listing

- Slug: ai-photo-listing
- Status: Working
- Last reviewed: 2026-11-03
- Findings: Critical 2, High 1, Medium 2, Low 1
- Fixed: 6 of 6 (shared with ai-product-creation-single-and-bulk.md)

Photo-to-listing: upload photos → stage → provider vision call → draft product/service with images attached on publish.
Also covers the known issue from the audit brief: **uploaded images reliably attach to the created product or service**.

## 1. What it does

Upload 1–N photos; Mercho groups them (optional), writes the listing, and when the merchant
approves, the images are copied into the product/service's permanent directory.

## 2. Map

- Routes: `dashboard.ai.photo-listing` (202), `dashboard.ai.photo-groups`
- Controllers: `AiController::photoListing`, `AiController::suggestPhotoGroups`
- Services: `AiImageService` (stage/delete/copy), `PrismAiManager`
- Jobs: `RunAiOperation` (listing_from_photo/service_listing)
- Models: `AiDraft`, `AiOperation`, `ProductImage`, `Service`

## 3. How it works

Photos staged to `ai/{page}/staging/randomname.ext`; queued operation carries
`input.media_relative_paths`; provider call; draft created with `_media_relative_paths`;
on apply (`AiDraftController::applyDraft`) files are copied to `products/{id}/...` and
`(variants/)` paths are written — see ai-product-creation-single-and-bulk.md.

## 4. Findings

### [CRITICAL] `media_relative_paths` accepted arbitrary paths (path traversal + cross-tenant staging read)
- Where: PrismAiManager.php `request()` — same as ai-product-creation-single-and-bulk.md
- Fix: `safeStagingPath()` prefix + segment check.
- Resolution: Fixed.

### [CRITICAL] Absolute local files forwarded to the provider
- Where: same location; legacy `media_paths` fallback
- Fix: removed.
- Resolution: Fixed.

### [HIGH] Staged images are publicly reachable by URL
- Where: `AiImageService::stage()` — public disk `ai/{page}/staging/<random>`; deleting only happens on `grouping` finally-block or explicit draft failure
- What: anyone with the random filename can view a merchant's unpublished photos.
- Fix: default the AI staging disk to private and stream through an authorized route, or delete on every terminal state (apply/reject/discard/fail).
- Resolution: Proposed, needs approval (behavior + storage change).

### [MEDIUM] No dimension/size reduction before upload to the provider
- Up to 10 MB base64 per image inline in the prompt.
- Resolution: Proposed (S) — see ai-product-creation-single-and-bulk.md.

### [MEDIUM] Staged files are not always deleted (listing path, not grouping path)
- Where: `photoListing()` stages for every photo; deletion happens only on... nothing in this flow (draft reject/discard/approve calls `deleteDraftMedia`, which matches own-page prefix — verified). Crashed workers and expired drafts leak files.
- Fix: `ReleaseStaleAiReservations`-style sweeper for `ai/*/staging` older than N days.
- Resolution: Proposed (S).

### [LOW] Image count enforced server-side — good
- `imageLimit($page)` enforced in validation (`max`) and `assertImageCount`. Plan caps verified.

## 5. Fixes applied

**Batch 2 — approved decisions implemented (2026-11-03)**

- Finding: Staged images were publicly reachable → staging now lives on the private `local` disk under `ai/{page_id}/staging/`; the dashboard reads them through the authorized `dashboard.ai.media` route (tenant-prefix check, `..`/`.` rejection, `image/*` only, `nosniff`, `private, no-store`). Files: `AiImageService.php`, `AiDraftController.php`, `routes/web.php`, draft/task views. Tests: `AiListingImagesTest` (4 tests, `Storage::fake('local')`).
- Finding: No downscale before provider upload → provider payloads are resized to 1600px webp q80; the stored original is untouched. Files: `AiImageService.php`.
- Same as ai-product-creation-single-and-bulk.md (media path allowlist, legacy fallback removal, input caps).
- Files: `PrismAiManager.php`. Tests: `--filter='Ai|AI'` 286 passed (includes AiListingImagesTest).

## 6. Proposed, needs approval

- Staging orphan sweeper (S) — staging originals are deleted on draft apply/delete; only abandoned drafts rely on manual cleanup.

## 7. Checked and fine

- Plan image caps enforced on every entry point; upload MIME validated by real file (svg excluded); staging filenames unguessable; staging copies deleted on grouping failure paths.

## 8. Unverified / needs manual check

- Whether unpublished photos are ever linked from a a draft preview page that is publicly routable.

## 9. Open questions

- Should AI listing photos be private until the product goes live?
