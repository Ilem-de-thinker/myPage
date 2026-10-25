<?php

return [
    'enabled' => env('AI_ENABLED', true),
    'default_timeout' => (int) env('AI_DEFAULT_TIMEOUT', 30),
    'default_retries' => (int) env('AI_DEFAULT_RETRIES', 1),
    'structured_mode' => env('AI_STRUCTURED_MODE', 'auto'),
    'prompts_path' => resource_path('prompts/ai'),
    'embeddings' => [
        'enabled' => env('AI_EMBEDDINGS_ENABLED', false),
        'provider' => env('AI_EMBEDDINGS_PROVIDER', env('AI_SEARCH_PROVIDER', 'openai')),
        'api_key' => env('AI_EMBEDDINGS_API_KEY', env('OPENAI_API_KEY')),
        'model' => env('AI_EMBEDDINGS_MODEL', env('AI_SEARCH_MODEL', 'text-embedding-3-small')),
        'url' => env('AI_EMBEDDINGS_URL', 'https://api.openai.com/v1/embeddings'),
        'timeout' => (int) env('AI_EMBEDDINGS_TIMEOUT', 20),
    ],
    'plans' => [
        'free' => ['monthly_credits' => (int) env('AI_FREE_MONTHLY_CREDITS', 25)],
        'paid' => ['monthly_credits' => (int) env('AI_PAID_MONTHLY_CREDITS', 500)],
    ],
    'tasks' => [
        'site_builder' => ['provider' => env('AI_SITE_BUILDER_PROVIDER', 'openai'), 'model' => env('AI_SITE_BUILDER_MODEL', 'gpt-4o-mini'), 'temperature' => 0.2, 'max_tokens' => 3000, 'timeout' => 60, 'structured' => true, 'structured_mode' => env('AI_SITE_BUILDER_STRUCTURED_MODE', 'auto'), 'provider_options' => ['schema' => ['strict' => true]], 'prompt_version' => 'v1', 'fallback' => [['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest']]],
        'theme_and_color_preset' => ['provider' => env('AI_THEME_PROVIDER', 'openai'), 'model' => env('AI_THEME_MODEL', 'gpt-4o-mini'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest']]],
        'listing_from_photo' => ['provider' => env('AI_LISTING_PHOTO_PROVIDER', 'gemini'), 'model' => env('AI_LISTING_PHOTO_MODEL', 'gemini-2.0-flash'), 'temperature' => 0.2, 'max_tokens' => 1200, 'timeout' => 45, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'openai', 'model' => 'gpt-4o-mini']]],
        'listing_photo_grouping' => ['provider' => env('AI_LISTING_PHOTO_PROVIDER', 'gemini'), 'model' => env('AI_LISTING_PHOTO_MODEL', 'gemini-2.0-flash'), 'temperature' => 0.1, 'max_tokens' => 500, 'timeout' => 45, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'openai', 'model' => 'gpt-4o-mini']]],
        'listing_copy' => ['provider' => env('AI_LISTING_COPY_PROVIDER', 'openai'), 'model' => env('AI_LISTING_COPY_MODEL', 'gpt-4o-mini'), 'temperature' => 0.4, 'max_tokens' => 800, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'service_listing' => ['provider' => env('AI_LISTING_COPY_PROVIDER', 'openai'), 'model' => env('AI_LISTING_COPY_MODEL', 'gpt-4o-mini'), 'temperature' => 0.4, 'max_tokens' => 1200, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'copy_rewrite' => ['provider' => env('AI_COPY_PROVIDER', 'openai'), 'model' => env('AI_COPY_MODEL', 'gpt-4o-mini'), 'temperature' => 0.5, 'max_tokens' => 800, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'store_assistant' => ['provider' => env('AI_STORE_ASSISTANT_PROVIDER', 'openai'), 'model' => env('AI_STORE_ASSISTANT_MODEL', 'gpt-4o-mini'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 25, 'structured' => false, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest']]],
        'service_booking_assistant' => ['provider' => env('AI_BOOKING_ASSISTANT_PROVIDER', 'openai'), 'model' => env('AI_BOOKING_ASSISTANT_MODEL', 'gpt-4o-mini'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 25, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest']]],
        'order_chat_assist' => ['provider' => env('AI_ORDER_CHAT_PROVIDER', 'openai'), 'model' => env('AI_ORDER_CHAT_MODEL', 'gpt-4o-mini'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 25, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'insights_digest' => ['provider' => env('AI_INSIGHTS_PROVIDER', 'openai'), 'model' => env('AI_INSIGHTS_MODEL', 'gpt-4o-mini'), 'temperature' => 0.3, 'max_tokens' => 1200, 'timeout' => 45, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'ask_your_data' => ['provider' => env('AI_DATA_PROVIDER', 'openai'), 'model' => env('AI_DATA_MODEL', 'gpt-4o-mini'), 'temperature' => 0.1, 'max_tokens' => 800, 'timeout' => 30, 'structured' => false, 'prompt_version' => 'v1', 'fallback' => []],
        'smart_search' => ['provider' => env('AI_SEARCH_PROVIDER', 'openai'), 'model' => env('AI_SEARCH_MODEL', 'text-embedding-3-small'), 'temperature' => 0, 'max_tokens' => 400, 'timeout' => 30, 'structured' => false, 'prompt_version' => 'v1', 'fallback' => []],
        'translate' => ['provider' => env('AI_TRANSLATE_PROVIDER', 'openai'), 'model' => env('AI_TRANSLATE_MODEL', 'gpt-4o-mini'), 'temperature' => 0.1, 'max_tokens' => 800, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'marketplace_intent' => ['provider' => env('AI_MARKETPLACE_PROVIDER', 'openai'), 'model' => env('AI_MARKETPLACE_MODEL', 'gpt-4o-mini'), 'temperature' => 0.1, 'max_tokens' => 700, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest']]],
        'marketplace_rank' => ['provider' => env('AI_MARKETPLACE_PROVIDER', 'openai'), 'model' => env('AI_MARKETPLACE_MODEL', 'gpt-4o-mini'), 'temperature' => 0.1, 'max_tokens' => 900, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'anthropic', 'model' => 'claude-3-5-haiku-latest']]],
    ],
];
