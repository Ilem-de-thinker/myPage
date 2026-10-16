# mypage.ng — UI/UX Specification

> **Audience:** design team.
> **Purpose:** a complete inventory of what is built today, what the design
> language actually is, and where it contradicts itself. Every class name,
> token and value below is read from the source, not from intent.
> **Status:** the platform is usable end-to-end. The design layer is *not*
> consistent — section 7 lists every place it isn't. That list is the brief.

---

## 1. What the platform is

mypage.ng is a multi-tenant storefront platform. A seller registers, picks one of
two themes, publishes a page at `/{business_name}`, and gets:

| Theme | Route | Sells | Checkout |
|---|---|---|---|
| **Store** | `/{slug}` | Products | Cart → card payment, or a WhatsApp order |
| **Service** | `/{slug}` | Bookable services | Availability calendar → deposit or full payment |

Buyers **never have an account**. Browsing, buying and booking require no
sign-in. The only sign-in door on a storefront is in the footer. Every design
decision below follows from that: nothing may sit behind a login, and the WhatsApp
action is treated as a primary conversion channel rather than a fallback.

There are three distinct audiences of screens:

1. **Platform** — landing, directory, discovery. One visual language, platform-owned.
2. **Storefront themes** — what a buyer sees at `/{slug}`. Token-driven, seller-themeable.
3. **Seller dashboard** — out of scope for this document.

---

## 2. Design system

### 2.1 There are two design systems, and they do not meet

This is the single most important thing for the design team to know.

**System A — "tenant" tokens.** Storefront themes. Every colour, radius and
typeface is chosen *by the seller* at runtime and printed into the document as
CSS custom properties by `App\Shared\Support\ThemeTokens` (via
`<x-theme-vars>`). Templates reference `var(--brand)`, never `text-green-600`.
Because the seller picks, contrast is derived automatically and a theme can never
produce unreadable text.

**System B — "platform" palette.** Landing, directory, discovery. Static Tailwind
slate + brand from `@theme` in `app.css`. No runtime theming.

The two systems currently produce visibly different pages. A product card on a
storefront and a business card on the discovery hub are the same kind of object
drawn in two different languages.

### 2.2 Colour

**Platform palette** (`resources/css/app.css`, `@theme`):

| Token | Value | Used for |
|---|---|---|
| `--color-brand` | `#16A34A` | primary actions |
| `--color-brand-dark` | `#15803D` | primary hover |
| `--color-brand-soft` | `#DCFCE7` | badge / wash fills |
| `--color-brand-900` | `#14532D` | dark-mode brand surfaces |
| `--color-body` | `#475569` | body copy (legacy) |
| `--color-surface` | `#F8FAFC` | page background |
| `--color-danger` | `#DC2626` | errors |
| `--color-warning` | `#D97706` | warnings |
| `--color-accent` | `#F97316` | secondary accent |
| `--font-sans` | Inter | body |
| `--font-display` | Plus Jakarta Sans | headings |

Neutral ramp in active use: `slate-50 … slate-950`.
Semantic mappings in `@theme inline`: `--color-ink`, `--color-ink-2`,
`--color-ink-3`, `--color-page`, `--color-card`, `--color-hairline`, `--color-subtle`.

**Tenant tokens** (printed per storefront by `ThemeTokens`, ~60 properties).
The ones templates actually use:

| Token | Meaning |
|---|---|
| `--brand` | primary fill |
| `--brand-ink` | text **on** `--brand` |
| `--brand-text` | brand-coloured text on paper (contrast-checked) |
| `--brand-wash`, `--brand-wash-2` | 2 tints of brand for card beds |
| `--brand-edge` | hairline border in brand family |
| `--brand-veil` | translucent brand for the scrolled navbar |
| `--paper` | page background |
| `--bg-surface`, `--bg-card`, `--bg-card-glass` | page / panel / glass panel |
| `--ink`, `--ink-2`, `--ink-3` | three text weights, all contrast-checked |
| `--rule`, `--border-subtle` | hairlines |
| `--radius-brand`, `-sm`, `-lg`, `--radius-pill` | radius dial |
| `--shadow-rest`, `--shadow-lift`, `--glow-primary`, `--glow-accent` | depth |
| `--font-heading-stack`, `--font-body-stack` | seller-chosen typefaces |
| `--input-border`, `--input-bg`, `--input-ring`, `--input-radius` | form controls |

**Discovery-hub alias.** The hub introduces `--primary` as an alias of
`rgb(var(--brand-primary))` so platform markup can say `var(--primary)` while a
storefront says `var(--brand)`, and both resolve to the same channel. **If the
design team renames or re-tokens this, the alias must move with it.**

**Non-negotiable colour rule, already applied:** colour is never the only signal.
Out-of-stock badges say "Out of stock" *and* dim; the Verified badge is shape +
colour; Store vs Booking is emoji + tint + wording simultaneously.

### 2.3 Typography

Two scales coexist.

**A. The tenant scale** (`storefront-theme.css`). Fluid, CSS-variable driven,
seller-facing. Used throughout both themes.

| Class | Size | Weight / leading / tracking |
|---|---|---|
| `.t-display-1` | `clamp(2.5rem, 1.6rem + 4.2vw, 4.75rem)` | 800 / 0.96 / −0.035em, balanced |
| `.t-display-2` | `clamp(1.75rem, 1.3rem + 2.2vw, 2.75rem)` | 800 / 1.04 / −0.025em, balanced |
| `.t-title` | `clamp(1.0625rem, 1rem + 0.35vw, 1.25rem)` | 600 / 1.25 / −0.01em |
| `.t-body-lg` | `1.0625rem` | 1.7 leading |
| `.t-body` | `clamp(0.9375rem, 0.91rem + 0.15vw, 1rem)` | 1.65 leading |
| `.t-meta` | `0.8125rem` | 500 / `var(--ink-3)`. **Explicitly never uppercase** — "a seller writing 'what you sell' should not have to shout it" |
| `.t-price-sm` | `0.9375rem` | 700, `tabular-nums`, `var(--brand-text)` |
| `.t-price-lg` | `clamp(1.5rem, 1.3rem + 1vw, 2rem)` | 700, tabular, brand-text |
| `.t-measure` / `.t-measure-narrow` | `68ch` / `58ch` | max line length |

All of `.t-display-1/2`, `.t-title`, `.t-price-*` also set
`font-family: var(--font-heading-stack)`.

**B. Hand-written Tailwind sizes.** `text-4xl sm:text-5xl`, `text-xs`,
`text-[11px]`, `text-[13px]`… Used on **every** platform page and on several
theme pages that predate the scale (checkout, cart, order-tracking, escrow).
This is the largest inconsistency in the product.

**Font pairing rule, stated in the CSS:** one family, two extremes (800 display,
400 reading). No italic accent inside a headline.

### 2.4 Layout

| Constant | Value | Where |
|---|---|---|
| `--shell` | `80rem` (1280px) | `.shell` — all tenant pages |
| platform container | `max-w-6xl` (1152px) | landing, directory, discovery |
| `--gutter` | `clamp(1rem, 0.6rem + 1.6vw, 2rem)` | `.shell` padding |
| `--section-y` | `clamp(3.5rem, 2.4rem + 4.6vw, 7rem)` | `.section-y` |
| `.t-measure` | `68ch` | prose |

`--brand-veil` exists specifically so the scrolled navbar can be a translucent
brand wash rather than a grey — the navbar must never become "browser default".

### 2.5 Components in `storefront-theme.css`

- `.plate` / `.plate-media` — a clipped image frame. **The frame never moves; only
  the picture inside it zooms** (`scale(1.03)` over 500ms on `:hover` and
  `:focus-visible`). Used by product cards and the product detail hero.
- `.grain::after` — 3% SVG fractal-noise over a photograph. Print noise, not
  decoration: it stops a cheap phone photo reading as stock imagery.
- `.card`, `.card-glass`, `.card-lift`, `.media-veil` — declared; **barely used**.
- `.marquee*` (in `app.css`) — the discovery hub's three scrolling columns.

### 2.6 Motion

| Motion | Spec |
|---|---|
| Plate zoom | 500ms `cubic-bezier(.2,.8,.2,1)` to `scale(1.03)` |
| Navbar handover | 300ms on background/border; height 64px → 56px past 24px scroll |
| Mobile sheet | 300ms translate + fade; backdrop 200ms |
| Filter drawer | 300ms ease-out in, 200ms ease-in out, `translate-x-full` |
| Accordion | 200ms ease-out opacity + `-translate-y-1` |
| Add-to-cart label | 150ms opacity crossfade |
| Cart badge | `x-transition.scale` |
| Marquee columns | 48s (up) / 56s (down) — deliberately desynchronised |
| Card hover | 300ms on transform, box-shadow, background, border *together* |

`prefers-reduced-motion: reduce` is honoured by the marquee (animation off,
duplicate copy removed) and by `storefront-theme.css`. It is **not** honoured on
the discovery hub's card hover, which only moves 4px.

### 2.7 Accessibility, as currently built

Good, and worth protecting:

- Global visible focus: `:focus-visible` gets `outline: var(--focus-ring)` **plus
  a paper-coloured inner ring**, so it survives on top of a brand fill where a
  single ring would vanish.
- Every decorative SVG carries `aria-hidden="true"` (+ `focusable="false"`).
- Chips use visible `×` hidden from AT plus `sr-only` "Remove {x} filter".
- The marquee's duplicated card list is `inert` — not merely `aria-hidden`, which
  does not stop its links being focused.
- Touch targets are 44–48px throughout (`min-h-11`, `min-h-12`, `size-11`).
- Product-card cart changes are announced via `sr-only` + `aria-live="polite"`.
- Sold-out is stated in words, not signalled by dimming alone.
- `prefers-reduced-motion` respected on all auto-playing motion.

Bad, and in section 7.

---

## 3. Platform screens

### 3.1 Landing — `/`

Single column, `max-w-6xl`. Sections in order: sticky header → hero → two-theme
explainer (2 cards) → "How it works" (3 numbered steps in an `<ol>`) → pricing
(3 plan cards, price read live from `Plan::active()` with a config fallback) →
dark closing CTA → footer.

- Header: `sticky top-0 z-30 border-b border-slate-200 bg-white/85 backdrop-blur`,
  `h-[56px]`-ish, wordmark `mypage` + `.ng` in brand.
- Hero: eyebrow → `h1` (`text-4xl sm:text-5xl`) → lede → primary + secondary CTA
  → reassurance line that browsing needs no account.
- Pricing cards: featured plan gets `border-brand shadow-subtle` **and** an
  absolute "Most popular" badge — never colour alone.
- No JavaScript at all. No Alpine.

### 3.2 Directory — `/directory` (legacy)

Browse-by-state-and-category. Header → `h1` + count → GET filter bar (search,
theme, state, category) → active-filter chips → `sm:grid-cols-2 lg:grid-cols-3`
card grid → pagination.

Each card is **one large `<a>`** wrapping logo, name, verified tick, location,
theme chip, tagline and product count.

Retained behind `feature:directory`. It coexists with the discovery hub and is
not going away — see section 7.

### 3.3 Discovery hub — `/discover`  ← **the new surface**

Two states on one route. Unfiltered: the marquee. Filtered: a result grid.
The two swap live without a navigation.

**Shell** — `body.hub-surface`, sticky header (`bg-white/80 backdrop-blur-lg`),
`max-w-6xl` content.

**Hero** — eyebrow, `h1 text-4xl sm:text-5xl`, lede, then:

- **Type switcher** — three pills with live counts from the query:
  `✨ Everything` · `🛍️ Stores` · `📅 Services`. Links, not radios, so they can be
  shared and opened in a new tab. Active = `bg-ink text-white` + `aria-current`.
- **Search row** — keyword input (magnifier icon, `pr-11` clear button) + city
  `<select>` + Search button. Stacks to a row at `sm`.
- **Tier pills** — only the tiers actually in use: `₦ Budget` `₦₦ Standard`
  `₦₦₦ Premium` `₦₦₦₦ Luxury`.

Two radial washes behind the hero, offset from each other
(`radial-gradient(55rem 28rem at 25% -15%, brand/0.14)` +
`radial-gradient(45rem 24rem at 78% 0%, orange/0.10)`). The offset is deliberate:
one centred wash reads as a spotlight, two read as designed.

**Marquee** — three columns, `--up / --down / --up`, middle column offset
`-6rem`. Each column is `36rem` tall, `mask-image` fades both ends, and the card
list is **rendered twice** with the animation translating exactly `-50%` so the
loop has no seam. Below `lg` it is replaced by a static `sm:grid-cols-2` grid —
a moving list on a touch screen is a list you cannot tap.

**Business card** — the signature component. **Two states:**

```
rest    opaque card
hover   lift + translucency + blur + brand glow
```

```blade
hover:-translate-y-1
hover:border-[var(--primary)]/45
hover:bg-white/70
hover:backdrop-blur-md
hover:shadow-[0_18px_45px_-18px_rgb(var(--brand-primary)/0.45),
             0_0_0_1px_rgb(var(--brand-primary)/0.12)]
```

A radial wash fades in under the card on hover so `backdrop-blur` has something to
blur — without it the blur renders as a flat tint and the effect is invisible.

Contents: logo or initial in a rounded square · name · verified tick · city/state ·
**type badge** (🛍️ Store green / 📅 Booking indigo, with inset ring) · tagline
(2 lines) · rating with count · price tier glyph · Featured chip · footer with
"Browse the shop" / "View services" + a chevron button that fills with brand on
hover.

**Results grid** — count heading, removable chips, `sm:grid-cols-2 lg:grid-cols-3`
of the same card, pagination, dashed empty state.

---

## 4. Store theme — `/{slug}`

Shell: `components/themes/shared/layout.blade.php` — a scroll-aware fixed navbar
that starts transparent over the hero photograph and becomes a translucent brand
veil past 24px, a mobile sheet, and a footer in `md:grid-cols-4`.

**Nav differs by theme:** Service gets `Services` + `Book`; Store gets `Products`
+ a cart pill with a live Alpine count badge. Then `About`, `Contact`.

**The WhatsApp layer is always on.** Mobile: a full-width bar pinned to the bottom
with `env(safe-area-inset-bottom)` padding. Desktop: a vertical tab on the right
edge, `writing-mode: vertical-rl`, reading "WhatsApp". `<main>` gets matching
bottom padding so the bar never covers the last row.

### 4.1 Product catalogue — `/{slug}/products`

`lg:grid-cols-4`: a sticky `col-span-1` rail and a `col-span-3` grid.

- **Rail**: `sticky top-20 max-h-[calc(100dvh-6rem)] overflow-y-auto`.
- **Below `lg`**: the rail becomes a right-hand off-canvas drawer
  (`w-[min(22rem,88vw)]`), opened by a Filters button that carries a live count badge.
- **One panel, included twice** (`partials/catalog-filters.blade.php`, `$idPrefix`
  = `rail` | `drawer`) so the two cannot drift. Search → Sort → Categories
  accordion → Price min/max → Apply/Clear.
- Category accordion uses `x-show`, not `x-collapse` — the Collapse plugin is not
  in the bundle, and a directive that quietly does nothing leaves the list open forever.
- Price is **two number boxes, not a dual slider**: a two-handle slider needs a
  pointer, a keyboard model and a drag state, and on a phone it fights page scroll.
- Grid: `grid-cols-2 md:grid-cols-3`.
- Active filters appear as removable chips above the grid, plus a Clear-all link.

**Product card** — `.plate`, bordered by a hairline rather than a box. The add-to-cart
button sits *below* the rule so the grid reads as a list a person wrote, not tiles a
template produced.

```
plate-media (aspect-square, bg-brand-wash, hover zoom 1.03)
  └─ absolute top-3 left-3  −30% discount badge (brand fill)
category pill (outlined, brand-edge)
name  ···  price stack:  <s>compare-at</s> over t-price-sm
"Out of stock" (words) or "Sold 5 times" (popular sort only)
SKU
────────── hairline ──────────
[ Add to cart ⌀ ]   →  [ In cart ]   with a 1-frame spinner
```

Compare-at is struck through **only** when it is genuinely higher — a seller who
typed the two the wrong way round gets a plain price, not a negative discount.

### 4.2 Product detail — `/{slug}/products/{slug}`

`md:grid-cols-12`: image `col-span-7`, purchase column `col-span-5`.

Image carries `grain` (3% noise). Monogram fallback for no photo.
Right column: name, category, `t-price-lg` (Alpine-bound, reflects the chosen
variant), stock line in words, sanitised description at `t-measure-narrow`, option
buttons (`aria-pressed`, disabled when sold out, showing per-option price),
a 48px quantity stepper, Add to cart, `Go to cart`, and a WhatsApp outline button.
Then a 4-up "You may also like".

### 4.3 Cart · Checkout · Tracking · Escrow

- **Cart** — rendered entirely from the Alpine store, styled as a receipt.
  `max-w-3xl`, hairline-separated rows, steppers, remove links. Two actions:
  `Checkout` (outlined) and `Order on WhatsApp` (brand). On WhatsApp success it
  shows the generated reference (`MP…`) and opens the wa.me link.
- **Checkout** — `max-w-2xl`. Two cards: "Your details" (name, phone, email,
  note) and "Order summary". The summary is **explicitly untrusted** — the server
  re-prices every line. Phone/email blur fires a best-effort abandoned-cart
  capture that is never allowed to block checkout.
- **Order tracking** — guest lookup, `max-w-2xl`, requires reference **and**
  contact to match, and a bad guess returns the same "not found" as a missing order.
- **Escrow** — token-signed buyer page, `max-w-lg`. Release behind a native
  `confirm()`; dispute behind a native `<details>` accordion. Emerald for money,
  amber for frozen, red for dispute.

### 4.4 Home blocks (store)

`hero` · `featured` (newest 4) · `categories` · `products` · `reviews` ·
`about` · `contact`. Order comes from the theme, not from drag handles.

**Hero** — optional photo with a neutral scrim
(`linear-gradient(180deg, rgb(10 12 16 / .45), .62, .82)`) plus `grain`.
The scrim is deliberately neutral ink so it never recolours the photograph.
Copy is `t-display-1` in white over photo. Two CTAs: brand-filled primary and a
bordered secondary that inverts over a photo.

**Featured** — hairline gutters rather than grid gaps: odd items on mobile get
`border-l pl-4`, every item but the first gets `md:border-l md:pl-5`.

**Reviews block** — ⚠️ this is a *seller-typed testimonial repeater*, not customer
reviews. A real reviews system now exists in the database (section 6) and this
block has not been wired to it.

---

## 5. Service theme — `/{slug}`

Same shell. Nav gains `Book`. No cart.

### 5.1 Service directory — `/{slug}/services`

1. Seller's own intro block, if any.
2. **Full-bleed hero** on `var(--bg-surface)`, deliberately *outside* `.shell` so
   the search reads as the way in: `h1` = page name, "{n} services you can book",
   a `min-h-14` search field with clear + Search inside its right edge, and quick
   filter pills.
3. **Quick pills** — duration (`Under 30 min`, `Up to 1 hour`, `Up to 2 hours`,
   `Up to 3 hours`) then the billing units actually on offer
   (`Per project` / `Per hour` / `Per month`). Every pill is a link that toggles
   itself via `http_build_query`, so the search keyword survives.
4. **Summary bar** — count, a tier breakdown (`₦₦₦ 3 services`) and a sort select
   that carries every active filter forward as hidden inputs.
5. **Removable chips**, because the pills that set them are up in the hero.
6. **`grid-cols-1 md:grid-cols-3 gap-8`** of service cards.

**Service card** — the tier comparison object.

```
┌ (featured → brand border + shadow-lift + ribbon on the border)
│  [photo, 4:3, optional]
│  Knotless Braids
│  description, 3 lines
│  ✓ Consultation and length check
│  ✓ Braid and install
│  ✓ Deep treatment on the day
│  +1 more
│  ─────────────────────────────
│  How long       Turnaround
│  3 hours        2 weeks
│  ₦45,000 /project
│  ₦10,000 to reserve, the rest on the day
│  2 people can take this
│  [ View & book ]
└
```

Design decisions worth keeping:
- The Featured ribbon sits **on the border**, not over the photo — a service
  often has no photo, and an overlay would sit on empty colour.
- Price + unit always together. "A price with no unit is a number a buyer has to
  email about."
- The price block is `mt-auto` so it bottom-aligns across a row regardless of
  whether a card has a photo.
- Deliverables: 4 shown, remainder counted. A card listing nine lines has stopped
  being a card.

### 5.2 Service detail · Booking · Confirmation

Detail: intro block → details block (`dl` of how long / price / deposit, brand CTA
+ WhatsApp outline) → the **booking widget in-page**, so a visitor does not leave
to make a choice they already made. `#book` + `scroll-mt-20` so the CTA does not
land under the fixed navbar.

Booking widget is rendered from **live bookable services**, never from stored
content — only the introduction above it is a block. That was a deliberate fix:
a widget a seller could fill in, or leave blank, was a booking page that silently
had nothing on it.

Confirmation renders exactly one component. "A confirmation page must never
disappear because a seller left a field blank."

### 5.3 Home blocks (service)

`hero` · `services` · `team` · `booking` · `gallery` · `reviews` · `about` · `contact`.

---

## 6. Built but not yet surfaced — **reviews**

A real reviews subsystem now exists in the database and is **not yet reachable from
the UI**. This is the largest gap.

- `reviews` table: morph to business / product / service, `author_name`,
  `author_location`, `rating` 1–5, `body`, `is_anonymous`,
  `status` (`pending` → `approved` → `rejected`), `is_featured`, `published_at`.
- Cached `rating` + `review_count` on `store_pages`, `products` and `services`,
  recalculated on every status change.
- Everything lands **pending**. Buyer-authored text on a public page is a legal and
  reputational problem, and the rating is derived from it.
- In design: the products page's `reviews` block is still the seller-typed
  testimonial repeater. A star summary on product cards, a review form on product
  and service detail pages, and a seller moderation inbox are all outstanding.

Components partially built: `components/ui/stars.blade.php`,
`components/ui/review-form.blade.php`, `components/ui/review-list.blade.php`.

---

## 7. Where the design contradicts itself

This is the section to work from.

| # | Problem | Impact | Where |
|---|---|---|---|
| **1** | **Two type systems.** Fluid `t-*` scale on every tenant page; hand-written `text-4xl sm:text-5xl` / `text-xs` / `text-[11px]` on every platform page *and* on checkout, cart, order-tracking and escrow. | Same product renders at three different heading sizes depending on the URL. | Global |
| **2** | **Two card philosophies.** `.plate` = flat, hairline, editorial, image zoom. `.hub-card` = elevated, rounded, glass, border glow, lift. | A product card and a business card are the same object in two languages. | themes ↔ discovery |
| **3** | **Dark mode exists on exactly one page.** The discovery hub has full `dark:` coverage and an OS-driven surface flip. Landing, directory and both themes have none. The stock Laravel paginator carries `dark:` classes that can never activate outside the hub. | Tab-to-tab colour inversion. | Global |
| **4** | **Three different headers.** Landing `sticky bg-white/85 backdrop-blur`. Directory `border-b bg-white`, not sticky. Hub `sticky bg-white/80 backdrop-blur-lg`. | Nav changes height and stickiness between platform pages. | Platform |
| **5** | **Stock paginator.** Laravel's default `pagination::tailwind` — grey/blue palette, wrong ring colours, invisible `dark:` — pasted into every list. | The most-repeated component on the product is the one that isn't ours. | Global |
| **6** | **Tenant pages that escaped the token system.** checkout, cart, order-tracking, escrow use raw `slate-*`, `text-ink`, and hex literals (`#b42318` for errors). They ignore `--brand-wash`, `--rule`, `t-*`. | Four pages on a storefront do not re-theme when a seller changes their colours. | Store theme |
| **7** | **`.hub-card` is used in markup but defined in no stylesheet.** Dead hook. | Hover styling can only be maintained in the Blade class list. | Discovery |
| **8** | **Business card `tabindex="0"` with no Enter/Space handler**, and its entire hover signature is `group-hover:` only. | A focus stop that does nothing, and keyboard users get none of the card's motion. | Discovery |
| **9** | **Two links per business card** (text link + chevron button) to the same URL. | Duplicate links in the tab order with near-identical meaning. | Discovery |
| **10** | **Directory cards are one giant link.** Accessible name concatenates name + Verified + location + chip + tagline + count. | Screen reader announces a paragraph per card. | Directory |
| **11** | **Directory filter inputs have no accessible name** — placeholder and option text only. Same on order-tracking and escrow. | Unlabelled controls. | Directory, store |
| **12** | **Landing has zero ARIA, zero `sr-only`, zero focus styling.** | Only nav anchor links are hidden on mobile. | Landing |
| **13** | **Double debounce on the hub search.** `@input.debounce.350ms` *and* a manual 350ms `setTimeout`. Worst case ~700ms. | Feels laggy; the stated intent was 350ms. | Discovery |
| **14** | **Marquee has no pause control.** Hover / `focus-within` / OS reduced-motion are the only off switches. | WCAG 2.2.2 asks for a mechanism. | Discovery |
| **15** | **Service search's clear button does not cancel its pending timer**, unlike the store filter panel which was fixed for exactly this. | Clearing can undo itself. | Service theme |
| **16** | **`.card`, `.card-glass`, `.card-lift`, `.media-veil` are declared and unused.** | Dead CSS. | Storefront CSS |
| **17** | **`--primary` alias exists on the hub only.** Storefronts say `var(--brand)`. | Two names for one colour across the product. | Global |
| **18** | **`$store.cart` lives in JS only.** The cart is client-side and `localStorage`-backed; the nav badge cannot render server-side. | No cart count in HTML for a crawler or a no-JS visitor. | Store theme |
| **19** | **The `reviews` home block is seller-authored testimonial text** while a real review system exists unused underneath it. | A seller can present invented praise as though it were customer reviews. | Both themes |
| **20** | **Mobile marquee flattens the 3 columns into one sequence,** losing the round-robin variety the desktop split exists to create. | Column 1 sees a worse list on mobile than on desktop. | Discovery |

---

## 8. Recommendations, in dependency order

1. **Pick one card language.** Either `.plate` grows elevation, or `.hub-card`
   flattens. Both cannot survive. (Fixes #2, and half of #1.)
2. **Adopt `t-*` on the platform pages**, or accept two scales deliberately and
   document where each applies. Currently it is accidental. (Fixes #1, #6.)
3. **Style the paginator.** Publish `pagination::tailwind` as a project view in
   the current palette, with working `dark:`. Smallest visual-quality-per-hour win
   in this list. (Fixes #5.)
4. **Decide the dark-mode story.** Either the hub stops, or the platform and both
   themes get it. Shipping one dark page among five light ones is the worst of the
   three options. (Fixes #3.)
5. **One header.** Extract the platform header, drive sticky/translucent from a
   prop. (Fixes #4.)
6. **Move the hub's `group-hover:` card treatment onto `:focus-visible`** and drop
   the `tabindex="0"`, or make it a real button. (Fixes #8.)
7. **Merge the duplicate card links** into one footer link with the chevron as
   decoration. (Fixes #9.)
8. **Fix the hub debounce** — keep either Alpine's modifier or the manual timer.
9. **Add a marquee pause control** beside the "Search instead" link.
10. **Give the directory and the landing labels, focus rings and a skip link.**
11. **Finish reviews**: wire the `reviews` block to real approved rows, put a star
    summary on product cards, mount the form on product and service detail pages,
    and build the seller moderation inbox. (Fixes #19, and closes section 6.)

---

## 9. Source map

| Area | Path |
|---|---|
| Platform CSS | `resources/css/app.css` |
| Tenant CSS + tokens | `resources/css/storefront-theme.css` |
| Token derivation | `app/Shared/Support/ThemeTokens.php` |
| Token emitter | `resources/views/components/theme-vars.blade.php` |
| Storefront shell | `resources/views/components/themes/shared/layout.blade.php` |
| Theme registry | `config/themes.php` |
| Theming options | `config/theming.php` |
| Landing | `resources/views/pages/landing.blade.php` |
| Directory | `resources/views/pages/directory/index.blade.php` |
| Discovery hub | `resources/views/discovery/` |
| Store theme | `resources/views/themes/store/` |
| Service theme | `resources/views/themes/service/` |
| Theme blocks | `resources/views/components/themes/{store,service}/blocks/` |
| Cart JS | `resources/js/app.js` |