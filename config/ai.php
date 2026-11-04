<?php

return [
    'enabled' => env('AI_ENABLED', true),
    'provider' => env('AI_PROVIDER'),
    'model' => env('AI_MODEL'),
    'api_key' => env('AI_API_KEY'),
    'timeout' => env('AI_TIMEOUT'),
    'retries' => env('AI_RETRIES'),
    'feature_limits' => [],
    'limits' => [
        'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 30),
        'max_batch_items' => (int) env('AI_MAX_BATCH_ITEMS', 25),
        'max_photo_items' => (int) env('AI_MAX_PHOTO_ITEMS', 20),
        'absolute_max_photo_items' => (int) env('AI_ABSOLUTE_MAX_PHOTO_ITEMS', 20),
        'default_photo_items' => (int) env('AI_DEFAULT_PHOTO_ITEMS', 4),
        'fallback_monthly_credits' => (int) env('AI_FALLBACK_MONTHLY_CREDITS', 25),
        'reservation_ttl_minutes' => (int) env('AI_RESERVATION_TTL_MINUTES', 30),
        'max_active_operations' => (int) env('AI_MAX_ACTIVE_OPERATIONS', 50),
        'max_global_active_operations' => (int) env('AI_MAX_GLOBAL_ACTIVE_OPERATIONS', 500),
        'operation_stale_minutes' => (int) env('AI_OPERATION_STALE_MINUTES', 30),
        'staging_ttl_hours' => (int) env('AI_STAGING_TTL_HOURS', 168),
    ],
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
        'free' => ['monthly_credits' => (int) env('AI_FREE_MONTHLY_CREDITS', 25), 'max_images_per_request' => 4],
        'trial' => ['monthly_credits' => (int) env('AI_TRIAL_MONTHLY_CREDITS', 50), 'max_images_per_request' => 4],
        'paid' => ['monthly_credits' => (int) env('AI_PAID_MONTHLY_CREDITS', 500), 'max_images_per_request' => 8],
        'starter' => ['monthly_credits' => (int) env('AI_STARTER_MONTHLY_CREDITS', 50), 'max_images_per_request' => (int) env('AI_STARTER_MAX_IMAGES', 4)],
        'growth' => ['monthly_credits' => (int) env('AI_GROWTH_MONTHLY_CREDITS', 200), 'max_images_per_request' => (int) env('AI_GROWTH_MAX_IMAGES', 8)],
        'pro' => ['monthly_credits' => (int) env('AI_PRO_MONTHLY_CREDITS', 500), 'max_images_per_request' => (int) env('AI_PRO_MAX_IMAGES', 8)],
    ],

    /*
        Buyer-facing assistant calls are platform-funded: shoppers trigger them,
        so they must not spend the merchant's Mercho credits. Each store still
        gets a finite daily budget that grows with its plan, otherwise the
        storefront would be an open tap on the provider account.
    */
    'buyer_assistant' => [
        'enabled' => (bool) env('AI_BUYER_ASSISTANT_ENABLED', true),
        'daily_caps' => [
            'trial' => (int) env('AI_BUYER_ASSISTANT_TRIAL_DAILY', 40),
            'starter' => (int) env('AI_BUYER_ASSISTANT_STARTER_DAILY', 80),
            'growth' => (int) env('AI_BUYER_ASSISTANT_GROWTH_DAILY', 200),
            'pro' => (int) env('AI_BUYER_ASSISTANT_PRO_DAILY', 400),
        ],
        'fallback_daily_cap' => (int) env('AI_BUYER_ASSISTANT_DAILY', 40),
    ],
    'tasks' => [
        'site_builder' => ['provider' => env('AI_SITE_BUILDER_PROVIDER', 'groq'), 'model' => env('AI_SITE_BUILDER_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.2, 'max_tokens' => 3000, 'timeout' => 60, 'structured' => true, 'structured_mode' => env('AI_SITE_BUILDER_STRUCTURED_MODE', 'auto'), 'provider_options' => ['schema' => ['strict' => true]], 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'theme_and_color_preset' => ['provider' => env('AI_THEME_PROVIDER', 'groq'), 'model' => env('AI_THEME_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'storefront_direction' => ['provider' => env('AI_STOREFRONT_PROVIDER', env('AI_THEME_PROVIDER', 'groq')), 'model' => env('AI_STOREFRONT_MODEL', env('AI_THEME_MODEL', 'openai/gpt-oss-120b')), 'temperature' => 0.3, 'max_tokens' => 4000, 'timeout' => 60, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'listing_from_photo' => ['provider' => env('AI_LISTING_PHOTO_PROVIDER', 'groq'), 'model' => env('AI_LISTING_PHOTO_MODEL', 'qwen/qwen3.8-27b'), 'temperature' => 0.2, 'max_tokens' => 1200, 'timeout' => 45, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => []],
        'listing_photo_grouping' => ['provider' => env('AI_LISTING_PHOTO_PROVIDER', 'groq'), 'model' => env('AI_LISTING_PHOTO_MODEL', 'qwen/qwen3.8-27b'), 'temperature' => 0.1, 'max_tokens' => 500, 'timeout' => 45, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'listing_copy' => ['provider' => env('AI_LISTING_COPY_PROVIDER', 'groq'), 'model' => env('AI_LISTING_COPY_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.4, 'max_tokens' => 2000, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'service_listing' => ['provider' => env('AI_LISTING_COPY_PROVIDER', 'groq'), 'model' => env('AI_LISTING_COPY_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.4, 'max_tokens' => 2800, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'copy_rewrite' => ['provider' => env('AI_COPY_PROVIDER', 'groq'), 'model' => env('AI_COPY_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.5, 'max_tokens' => 800, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'category_structure' => ['provider' => env('AI_CATEGORY_PROVIDER', 'groq'), 'model' => env('AI_CATEGORY_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.2, 'max_tokens' => 4000, 'timeout' => 60, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'description_generation' => ['provider' => env('AI_DESCRIPTION_PROVIDER', 'groq'), 'model' => env('AI_DESCRIPTION_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.5, 'max_tokens' => 900, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'store_assistant' => ['provider' => env('AI_STORE_ASSISTANT_PROVIDER', 'groq'), 'model' => env('AI_STORE_ASSISTANT_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 25, 'structured' => false, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'service_booking_assistant' => ['provider' => env('AI_BOOKING_ASSISTANT_PROVIDER', 'groq'), 'model' => env('AI_BOOKING_ASSISTANT_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 25, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'order_chat_assist' => ['provider' => env('AI_ORDER_CHAT_PROVIDER', 'groq'), 'model' => env('AI_ORDER_CHAT_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.2, 'max_tokens' => 700, 'timeout' => 25, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'insights_digest' => ['provider' => env('AI_INSIGHTS_PROVIDER', 'groq'), 'model' => env('AI_INSIGHTS_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.3, 'max_tokens' => 1200, 'timeout' => 45, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'ask_your_data' => ['provider' => env('AI_DATA_PROVIDER', 'groq'), 'model' => env('AI_DATA_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.1, 'max_tokens' => 800, 'timeout' => 30, 'structured' => false, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'smart_search' => ['provider' => env('AI_SEARCH_PROVIDER', 'groq'), 'model' => env('AI_SEARCH_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0, 'max_tokens' => 400, 'timeout' => 30, 'structured' => false, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'translate' => ['provider' => env('AI_TRANSLATE_PROVIDER', 'groq'), 'model' => env('AI_TRANSLATE_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.1, 'max_tokens' => 800, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'marketplace_intent' => ['provider' => env('AI_MARKETPLACE_PROVIDER', 'groq'), 'model' => env('AI_MARKETPLACE_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.1, 'max_tokens' => 700, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
        'marketplace_rank' => ['provider' => env('AI_MARKETPLACE_PROVIDER', 'groq'), 'model' => env('AI_MARKETPLACE_MODEL', 'openai/gpt-oss-120b'), 'temperature' => 0.1, 'max_tokens' => 900, 'timeout' => 30, 'structured' => true, 'prompt_version' => 'v1', 'fallback' => [['provider' => 'groq', 'model' => 'openai/gpt-oss-20b']]],
    ],
];
