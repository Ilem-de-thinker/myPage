<?php

return [
    'domain' => env('APP_DOMAIN', 'mypage.test'),
    'path_routes' => env('STOREFRONT_PATH_ROUTES', true),
    'gateway' => env('PAYMENT_GATEWAY', 'paystack'),
    'commission_bps' => (int) env('PLATFORM_COMMISSION_BPS', 150),
    'payout_minimum_kobo' => (int) env('PAYOUT_MINIMUM_KOBO', 500000),
    'escrow_auto_release_days' => (int) env('ESCROW_AUTO_RELEASE_DAYS', 7),
    'features' => [
        'escrow' => env('FEATURE_ESCROW', false),
        'broadcasts' => env('FEATURE_BROADCASTS', false),
        'directory' => env('FEATURE_DIRECTORY', false),
        'custom_domains' => env('FEATURE_CUSTOM_DOMAINS', false),
    ],
    'reserved_slugs' => ['www', 'admin', 'api', 'app', 'mail', 'support', 'help', 'blog', 'static', 'cdn'],
];
