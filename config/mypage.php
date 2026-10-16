<?php

return [
    'domain' => env('APP_DOMAIN', 'mypage.test'),
    'path_routes' => env('STOREFRONT_PATH_ROUTES', true),
    'gateway' => env('PAYMENT_GATEWAY', 'paystack'),
    'commission_bps' => (int) env('PLATFORM_COMMISSION_BPS', 150),
    'payout_minimum_kobo' => (int) env('PAYOUT_MINIMUM_KOBO', 500000),
    'escrow_auto_release_days' => (int) env('ESCROW_AUTO_RELEASE_DAYS', 7),
    // How long a cart has to sit before the buyer is nudged once.
    'abandoned_cart_reminder_hours' => (int) env('ABANDONED_CART_REMINDER_HOURS', 24),

    // Booking. A store page may override the timezone in its own settings, but
    // every Nigerian seller defaults to Lagos.
    'timezone' => env('PLATFORM_TIMEZONE', 'Africa/Lagos'),
    'booking_min_notice_minutes' => (int) env('BOOKING_MIN_NOTICE_MINUTES', 60),
    'booking_slot_step_minutes' => (int) env('BOOKING_SLOT_STEP_MINUTES', 30),
    // How long an unpaid booking holds its slot before ExpirePendingBookings frees it.
    'booking_hold_minutes' => (int) env('BOOKING_HOLD_MINUTES', 15),
    'features' => [
        'escrow' => env('FEATURE_ESCROW', false),
        'broadcasts' => env('FEATURE_BROADCASTS', false),
        'directory' => env('FEATURE_DIRECTORY', false),
        // The discovery hub. Separate from the directory on purpose: the directory
        // is browsed by state and category, the hub is searched by what a buyer
        // needs. Switching one on should not put the other in front of the world.
        'discovery' => env('FEATURE_DISCOVERY', false),
        'custom_domains' => env('FEATURE_CUSTOM_DOMAINS', false),
    ],
    /*
     * Emergency switch: turns every feature off for every seller regardless of
     * config defaults and database rows. Off normally.
     */
    'features_locked' => (bool) env('FEATURES_LOCKED', false),

    'reserved_slugs' => ['www', 'admin', 'api', 'app', 'mail', 'support', 'help', 'blog', 'static', 'cdn'],

    /*
     |--------------------------------------------------------------------------
     | Live demo storefronts
     |--------------------------------------------------------------------------
     |
     | The marketing pages point at the two storefronts DemoSellerSeeder creates:
     | one per theme. They are ordinary pages, so the marketing links are
     | ordinary storefront URLs and there is no separate demo tree to keep in
     | sync.
     |
     | Null when those storefronts are not seeded, which hides the demo links
     | rather than linking to a 404.
     */
    'demo' => [
        'store' => env('DEMO_STORE_SLUG', 'ada-textiles'),
        'service' => env('DEMO_SERVICE_SLUG', 'kemi-braids'),
    ],

    /*
     |--------------------------------------------------------------------------
     | Custom domains
     |--------------------------------------------------------------------------
     |
     | A seller's own hostname, CNAMEd at cname_target. TLS is issued for it
     | once DNS resolves, which is why cname_target has to be a host we control
     | and terminate: a seller's domain must never present a certificate for
     | somebody else's shop.
     |
     | The CNAME target is not a secret and is shown to the seller in their
     | dashboard, so it can live in config rather than being hidden.
     */
    'custom_domain' => [
        'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET', 'domains.mypage.test'),
        'connect_timeout' => 3,
        'timeout' => 5,
        // How long to keep re-checking a domain nobody has finished pointing at
        // us yet. A seller is not in a hurry, but a CNAME can take hours.
        'attempts_before_giving_up' => 40,
    ],
];
