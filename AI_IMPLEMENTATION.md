# MyPage.ng AI implementation

AI is contextual. There is no merchant-facing AI Studio, AI Hub, provider
picker, standalone AI dashboard, or catch-all AI workspace. Reuse the existing
Prism manager, task configuration, credits, fallbacks, queued operations,
drafts, and version history. Feature code passes registered task names and
tenant-scoped input; provider and model names stay inside the AI service layer
and deployment configuration.

## Feature map

| Page | Where AI lives | Merchant input | AI result | Who sees it |
| --- | --- | --- | --- | --- |
| Store creation | “Create with AI” option | Store name and what it does | Business type, theme, brand/accent, copy, FAQ, SEO, categories, starter drafts | Everyone |
| Products | “Use AI” button and floating button | Many photos, optional hint, and/or a free-text list | Multiple product drafts | STORE, BOTH |
| Services | “Use AI” button and floating button | Several services in text/voice transcript and/or photos | Multiple service drafts | SERVICE, BOTH |
| Appearance/Theme | “Use AI” button | Brand mood or logo | Three theme/color presets or logo color suggestion | Everyone |
| Content | Inline “Improve with AI” | Existing text field | Rewrite or translation | Everyone |
| Analytics | “Use AI” button | Question or insights request | Tenant-scoped insights | Everyone |
| Order chat | Inside the thread | Open thread and opt-in action | Reply suggestion, summary, translation | Everyone |
| Public storefront | Separate buyer widget | Buyer question | Grounded answer or WhatsApp handoff | Buyers |

Products and services never mix. The UI hides unsupported features and the
server rejects unsupported feature requests. `StorePage::businessType()` reads
`settings.business_type` when present and otherwise falls back to the existing
theme value. `AiFeatureRegistry` is the source of truth for page, feature, task,
business type, and plan mapping.

## Part 1: Store creation

The creation screen offers “Create manually” and “Create with AI”. The AI path
requires only a store name and a free-text description, including voice
transcript text. AI proposes business type, registered theme, merchant brand
color, accent, tagline, about, FAQ, SEO title/description, categories, and
starter products or services.

Every generated value, including every starter product or service, is an
editable draft on the review screen. The review shows a summary, live preview,
quick edits, per-item remove/keep controls, and version history. Confirm applies
only the selected configuration and starter drafts; Regenerate creates a new
version; Edit keeps the merchant in the review flow. Nothing is saved to the
live store before confirmation, and undo restores the previous configuration.
The palette generator derives all shades from the AI-selected brand and accent;
feature code never hard-codes brand colors.

## Part 2: Contextual creation features

### B. Product creation: STORE or BOTH only, bulk by design

The Products page has a prominent “Use AI” button opening a bottom sheet on
mobile and a side panel on desktop. There is no standalone product AI page.
The merchant can create many products in one run by:

1. Uploading many photos at once, with each photo becoming one draft product.
2. Pasting or typing a free-text list such as “Ankara bag 15k, leather sandals
   12k, tote bag 8k”, which AI splits into separate drafts.
3. Grouping several photos as one product for angles or colors before
   generation, while allowing AI to suggest product or service groupings.

AI fills everything it can per draft: name, description, category, tags,
suggested price clearly marked as a suggestion, variants, and SEO fields.
Results are editable draft cards. Each card supports edit, regenerate, and
discard. The panel supports select-all, “Confirm selected”, and “Confirm all”.

Large batches are queued with per-item progress. One failed item never blocks
the others; failed items show retry. The panel shows estimated credit use before
running and enforces plan/credit limits per batch. If the limit is reached
mid-batch, completed drafts remain available and the remaining items stop with
an upgrade prompt. Confirmed items are saved as normal unpublished/draft
products.

### C. Service creation: SERVICE or BOTH only, bulk by design

The Services page uses the same “Use AI” button and panel pattern. The merchant
can create many services in one run by:

1. Describing several services in one message or voice transcript, such as
   “bridal makeup, gele tying, lashes and home service makeup”, for AI to split
   into separate drafts.
2. Uploading reference or portfolio photos assigned to a service or grouped by
   AI.

Each draft includes title, description, packages/tiers, duration, what to
expect, service FAQ, suggested pricing clearly marked as a suggestion, and
booking questions. Draft cards support edit, regenerate, discard, select-all,
“Confirm selected”, and “Confirm all”. Queued jobs show per-item progress,
credit estimates, retries, partial results, and upgrade prompts when limits are
reached. Nothing is published until confirmation.

## Part 3: Other contextual surfaces

- Appearance/Theme: generate three live theme, brand-color, and accent presets
  from a mood description, or suggest brand colors from a logo. The merchant
  picks and confirms one preset; logo extraction also creates a confirmable
  color draft.
- Content/About/FAQ: inline “Improve with AI” actions rewrite tone or translate
  the active field without leaving the editor. Translation supports English,
  Nigerian Pidgin, Yoruba, Igbo, and Hausa, and the merchant confirms the text.
- Dashboard and Analytics: the dashboard now shows real tenant-scoped KPIs and
  seven-day storefront traffic, and the same analytics feature is available on
  the dedicated Analytics page. Insights and ask-your-data use only
  application-computed, tenant-scoped metrics.
- Order chat: suggested replies, summaries, and translations stay inside the
  thread. AI is opt-in and the merchant always sends the final message.
- Public storefront: the buyer assistant remains separate from dashboard AI,
  is grounded in the current store's published data, and can hand off to
  WhatsApp. It must not collide with storefront WhatsApp or floating controls.
- Pages without a registered feature show no AI button.

## Part 4: Shared flow and launcher

Every merchant flow follows input → progress → review/preview → Confirm →
apply, with undo/version history. AI never auto-publishes. Panels show credits
and plan limits, provide friendly retry states, show queued/running/succeeded/
failed status per batch item, and keep provider fallback invisible. All
endpoints are tenant-scoped and route through the existing AI layer by task
name.

The reusable launcher is driven by `AiFeatureRegistry`. On registered dashboard
pages it sits directly above the support button in one fixed bottom-right stack,
with a consistent gap. It uses design tokens, a minimum 44px touch target,
visible focus state, an aria label, Escape-to-close, safe-area/bottom-nav
offsets, and a calm one-time attention cue. It is below modal z-index and is
hidden while a panel, drawer, or keyboard interaction requires the space.

The old `/ai` URL remains only as a compatibility redirect to the dashboard.
Its page and navigation entry are removed.

## Part 5: Required tests

Add focused feature tests proving:

- A SERVICE merchant cannot reach product AI endpoints.
- A STORE merchant cannot reach service AI endpoints.
- A BOTH merchant can use product AI on Products and service AI on Services.
- A batch of 10 items yields 10 independent drafts.
- One failing batch item does not block the remaining drafts.
- “Confirm selected” saves only selected drafts; unselected drafts remain
  pending.
- A credit limit reached mid-batch keeps completed drafts and presents an
  upgrade path for unfinished items.
- Nothing is saved before Confirm and undo restores the previous state.
- The floating button renders only on pages registered in the feature registry.
- No central AI page or navigation entry remains.

## Existing service boundary

Do not rebuild Prism, `config/ai.php`, tasks, credits, fallbacks, tenant
scoping, or provider routing. Extend the contextual registry, panels, queued
batch orchestration, draft payloads, and approval/application paths as needed.
All generated products, services, and starter listings remain ordinary drafts
until the merchant confirms them.
