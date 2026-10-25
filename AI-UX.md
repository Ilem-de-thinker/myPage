# MyPage.ng contextual AI UX

AI is surfaced only inside the workflow that uses it. The old `/ai` URL is a
compatibility redirect to the dashboard; there is no AI hub or AI dashboard.

## Registry

`App\Domains\Ai\Support\AiFeatureRegistry` is the source of truth. It maps
the page feature to its task, allowed business types, and plan gate:

| Page | Feature | Task | Business types |
| --- | --- | --- | --- |
| Store creation | `store-creation` | `site_builder` | store, service, both |
| Products | `products` | `listing_copy` for text and `listing_from_photo` for photo batches | store, both |
| Services | `services` | `service_listing` | service, both |
| Appearance | `appearance` | `theme_and_color_preset` | store, service, both |
| Content | `content` | `copy_rewrite` or `translate` | store, service, both |
| Dashboard and Analytics | `analytics` | `insights_digest` and `ask_your_data` | store, service, both |
| Order chat | `order-chat` | `order_chat_assist` | store, service, both |

Business type reads `settings.business_type` when present, and falls back to
the existing `StorePage.theme` convention. The registry is enforced on the
server as well as by the dashboard UI.

## Adding a feature

Add one registry entry, add the page's inline `@click="$dispatch('ai.open')"`
button, and use the shared launcher already rendered by `layouts.app`. The
launcher opens the same page-scoped panel, records an editable draft through
the existing AI manager, and never publishes automatically.

## Floating placement rules

The launcher and support control are rendered by one dashboard layout stack.
The AI pill sits directly above support with the same right offset and a fixed
gap; it is absent on routes with no registered feature. Both use design tokens,
44px minimum touch targets, a modal-safe z-index, and Escape-to-close behavior.
