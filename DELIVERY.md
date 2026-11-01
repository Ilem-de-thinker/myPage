# Delivery & fulfilment

Status: **phase 4 done** — schema, pricing, checkout, dashboard settings,
listing overrides, storefront delivery band, booking travel fees and the
provider-aware map picker landed. Only discovery filtering remains.
Owner: platform team.

This document is the single source of truth for how merchants and service
providers describe what they deliver, where they deliver it, and what it costs.
It is written before the UI so the pricing rules are agreed first.

## Problem

Stores sell physical goods and services sell time. Today neither can say:

- whether an item is delivered, collected, or performed at the customer's place;
- which areas are served and what each area costs;
- whether delivery is free, free above a threshold, or free in some areas;
- where home service is available for services.

Checkout has an address free-text note and nothing else. Fees are negotiated on
WhatsApp, so the order total is not the real total.

## Goals

- One fulfilment model shared by product and service sellers.
- Store-wide defaults with per-listing overrides.
- Server-authoritative fees: the buyer never sends a price we trust.
- Zone-based delivery (state / city / area names), not polygon drawing.
- Services can advertise **home service available** (the provider travels).
- Free delivery: per zone, and by basket threshold.
- Snapshot the agreed delivery terms on the order.

## Non-goals

- Digital downloads. Out of scope for now.
- Route optimisation, live rider tracking, third-party courier APIs.
- Automatic geocoding of buyer addresses; zones are chosen, not inferred.

## Data model

### `delivery_profiles` (one per store)

| Column | Type | Notes |
| --- | --- | --- |
| `store_page_id` | FK, unique | cascade delete |
| `offers_delivery` | bool, default true | the shop ships to buyers |
| `offers_pickup` | bool, default false | buyer collects |
| `offers_home_service` | bool, default false | service seller travels to the buyer |
| `free_over_kobo` | int, nullable | basket subtotal that earns free delivery |
| `radius_km` | int, nullable | soft service/delivery radius, display only |
| `notes` | string(255), nullable | "Same-day within Lagos Island" |

A store without a profile keeps the legacy behaviour: delivery is assumed and
the fee is zero, confirmed on WhatsApp. No existing order changes meaning.

### `delivery_zones`

| Column | Type | Notes |
| --- | --- | --- |
| `delivery_profile_id` | FK | cascade delete |
| `name` | string(80) | "Lekki Phase 1", "Wuse 2" |
| `state` | string(80), nullable | helps group long lists |
| `fee_kobo` | int, default 0 | `0` means free delivery in this area |
| `position` | int, default 0 | seller ordering |

### Per-listing overrides (nullable = inherit the store)

`products`

| Column | Notes |
| --- | --- |
| `delivery_mode` | `delivery` or `pickup`; null inherits the store |
| `delivery_fee_kobo` | nullable floor for this item's delivery |

`services`

| Column | Notes |
| --- | --- |
| `service_mode` | `at_location`, `home`, or `remote`; null inherits the store |
| `travel_fee_kobo` | nullable travel fee for home service |
| `travel_radius_km` | nullable home-service radius |

### Order snapshot

`orders` gains `delivery_mode` (`delivery`, `pickup`, `none`),
`delivery_fee_kobo`, `delivery_zone`, `delivery_address`, `delivery_city`,
`delivery_state`, `delivery_latitude`, `delivery_longitude`.

These are **written once** at checkout. Changing a zone fee later must never
rewrite an order that was already placed.

## Fee rules (server-side, `QuoteDelivery`)

1. A pickup-only line, or a buyer who chose pickup, makes the whole order a
   pickup: fee `0`.
2. No profile → legacy delivery, fee `0` (WhatsApp confirmation, as before).
3. Profile without `offers_delivery` → pickup if offered, otherwise `none`.
4. Delivery fee = `max(zone fee, highest product override)`; nulls ignored.
   The per-product fee is a floor, so a shop never undercharges because one
   item carried an old override.
5. `free_over_kobo` zeroes the fee when the priced subtotal reaches it.
6. A zone id that does not belong to this store is never a rejection: checkout
   shows the shop's own areas and pre-selects the first, and an unknown or
   missing id falls back to that same first area.
7. Buyers are never turned away over delivery. Checkout always shows the areas
   (or the WhatsApp note when the shop has none); the server re-quotes the
   order it can see instead of failing.

Prices are always read from the database (`PriceCart`), never from the request.

## Maps

The location picker is **OpenStreetMap by default** because it is open source
and needs no key: Leaflet with the standard OSM tile layer, attributed to
OpenStreetMap contributors.

The admin can swap the provider from **Admin → Settings**:

- setting `platform.map_provider`, values `openstreetmap` (default) and `google`;
- Google renders only when `GOOGLE_MAPS_BROWSER_KEY` is configured; otherwise
  the picker falls back to OSM and says so.

Store coordinates already live on `store_pages.latitude/longitude` and the
existing Details tab already writes them, so the origin pin is reused rather
than rebuilt. Heavy tile traffic should move to a paid OSM tile provider or a
self-hosted tile server; the setting makes that swap possible without code.

## Buyer flow

1. Seller sets a delivery profile and zones in the dashboard.
2. Buyer adds products to the bag and opens checkout.
3. Checkout shows: fulfilment choice (delivery / pickup), zone select grouped by
   state, address fields, and a live fee preview computed from the zone data.
4. On submit the server re-quotes (`QuoteDelivery`), adds the fee to
   `total_kobo`, snapshots the delivery terms, then initialises payment.
5. Service bookings show "Home service available" and any travel fee before the
   buyer confirms.

## Seller UI

- **Dashboard → Storefront settings → Delivery**: delivery/pickup/home-service
  toggles, free-over amount, radius, notes, and a zone repeater (name, state,
  fee; fee `0` = free).
- **Product form**: "Fulfilment" (inherit / delivery / pickup) and optional
  per-item delivery fee.
- **Service form**: "Where is this delivered?" (inherit / at your location /
  home service / remote), travel fee and radius for home service.
- **Product page**: "Delivery available", "Free delivery over ₦X",
  "Free delivery in <zone>", or "Pickup only".
- **Service page**: "Home service available" with travel fee when set.

## Phases

1. **Schema and pricing (done)** — `delivery_profiles`, `delivery_zones`,
   listing overrides, order snapshot columns, enums, models, factories,
   `QuoteDelivery`, `tests/Feature/Orders/DeliveryQuoteTest.php`.
2. **Checkout (done)** — delivery/pickup choice, zone picker on the checkout
   page with a live fee preview, server re-quote, fee added to the order total,
   terms snapshotted. Missing or unknown zones fall back, they never reject.
3. **Seller dashboard (done)** — delivery settings tab, zone repeater, listing
   overrides on the product and service forms.
4. **Storefront (done)** — delivery band on both home themes, driven by the
   saved profile; checkout zone picker with live preview done in phase 2.
   Booking travel fee charged at booking time, and the provider-aware map
   picker (OSM default, Google when configured) are done.
5. **Discovery (later)** — filter directory results and the AI assistant by
   "delivers to my area".

## Decisions

- Zones, not polygons. Nigerian addresses are informal; a named area is easier
  to pick and explain than a map boundary.
- Physical delivery and services in this pass; digital delivery later.
- Fees are kobo integers everywhere, formatted with `Money` at the edges.
- The order stores the zone *name*, not just the id, so a deleted zone still
  reads correctly on historical orders.
