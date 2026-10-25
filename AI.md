# MyPage.ng AI

## Product experience requirement

AI is distributed through the product, not collected in one general-purpose AI
Studio. Users should see an AI action beside the work they are already doing:

- Registration asks for AI enablement and the merchant's primary goal before the
  store or service is created.
- Product and service forms provide “Draft with AI” actions for names and
  descriptions.
- The storefront editor provides page-specific copy assistance.
- Store and service headers open their own buyer assistant.
- Order, booking, and enquiry chat provides reply suggestions, summaries,
  translation, and status-message drafts in context.
- Search uses optional embeddings behind the existing search experience and
  falls back to keyword retrieval.

An optional AI workspace is limited to product/service creation and draft review.
It must not be a provider-routing dashboard or a page containing every AI
feature. Provider/model selection remains deployment configuration only; users
should never need to choose a provider or model in the UI.

## Delivered implementation

The current implementation includes:

- Merchant AI creation workspace at `/ai` for product/service-oriented drafts
  and review history; provider/model routing is not a user-facing feature.
- Approval and rejection for every draft, with version numbers and version-history UI. AI output never publishes automatically.
- Buyer-facing AI assistants on both store and service themes. The shared theme layout renders the assistant, and both theme headers expose an explicit “Ask AI” action.
- Grounded buyer answers through `StoreGrounding`, with public throttling and an AI disclosure.
- Seller chat suggestions, summaries, translation, status timelines, unread polling, and WhatsApp actions.
- Optional embedding storage and semantic discovery search, with keyword fallback when embeddings are disabled or unavailable.

AI generation is intentionally unavailable until `AI_ENABLED=true` and the relevant provider credentials are configured. Embeddings are independently opt-in.

The AI layer is task-based and provider-agnostic. Feature code calls the
`App\Domains\Ai\Contracts\AiManager` contract with a task key. Only
`config/ai.php` and Prism configuration contain provider/model choices.

## Switching providers or models

Set the task-specific environment variables, clear the config cache, and run
the smoke command. No PHP code changes are required.

```dotenv
AI_ENABLED=true
AI_SITE_BUILDER_PROVIDER=openai
AI_SITE_BUILDER_MODEL=gpt-4o-mini
AI_STORE_ASSISTANT_PROVIDER=anthropic
AI_STORE_ASSISTANT_MODEL=claude-3-5-haiku-latest
AI_LISTING_PHOTO_PROVIDER=gemini
AI_LISTING_PHOTO_MODEL=gemini-2.0-flash
OPENAI_API_KEY=...
ANTHROPIC_API_KEY=...
GEMINI_API_KEY=...

# Optional semantic discovery search (OpenAI-compatible embeddings endpoint).
AI_EMBEDDINGS_ENABLED=false
AI_EMBEDDINGS_PROVIDER=openai
AI_EMBEDDINGS_API_KEY=...
AI_EMBEDDINGS_MODEL=text-embedding-3-small
AI_EMBEDDINGS_URL=https://api.openai.com/v1/embeddings
```

Supported Prism providers depend on the installed Prism release. The current
installation supports OpenAI, Anthropic, Gemini, Mistral, Groq, Ollama,
DeepSeek, OpenRouter, Perplexity, xAI, VoyageAI, ElevenLabs, and Z. A task's
fallback list is used after provider failure, timeout, overload, rate limit, or
invalid structured output.

## Tasks and prompts

Task definitions live in `config/ai.php`. Each task defines its primary
provider/model, fallback chain, temperature, token ceiling, timeout, structured
mode, provider options, and prompt version. Prompts live under
`resources/prompts/ai/{version}`. Add a task by adding its config definition,
prompt file, Prism schema, validation rules, and focused fake-backed tests.

Structured tasks always use an object-root schema, check Prism's finish reason,
validate the returned array with Laravel validation, retry once with validation
errors, and then fall back or fail safely. Refusals, content filters, length
cutoffs, and empty output are not accepted as successful drafts.

## Credits and limits

Successful calls are written to `ai_runs` and `ai_usage_ledgers` with seller,
store, task, provider, model, prompt version, latency, token counts, and an
estimated cost. Plan limits are configured in `config/ai.php`; public assistant
and chat endpoints also use store/IP throttles. Personal data should be omitted
from task input unless the task needs it.

## Smoke testing

```bash
php artisan config:clear
php artisan ai:test-task store_assistant --input='{"message":"What do you sell?"}'
```

The command reports a safe task response or a failure after the configured
fallback chain. Automated tests use Prism's `Prism::fake()` and never call a
live provider.

## Grounding and prompt-injection rules

Merchant descriptions, catalog copy, buyer messages, images, screenshots, and
documents are untrusted data. They cannot override task instructions. Buyer
assistants answer only from the current tenant's published catalog, FAQ,
settings, services, and retrieved records. They must not invent prices, stock,
delivery, booking slots, policies, refunds, payment state, or credentials.

Analytics models narrate application-computed metrics and never receive raw SQL
access. Safe analytics tools are allow-listed and tenant-scoped. AI cannot
publish drafts, send chat messages, alter orders/bookings, take payment, or
promise refunds. Those actions require normal application authorization and an
explicit merchant action.

## Chat and retention

Order and service chat threads belong to one StorePage and are readable by that
merchant or by the buyer's expiring, hashed guest token. Polling is the default
transport; broadcasts can be added without changing the message contract.
Attachments are validated by MIME and size and stored outside executable paths.
Prism conversation history must preserve provider `additionalContent` if it is
replayed in a later request.
