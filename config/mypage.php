<?php

$platformDomain = env('APP_DOMAIN') ?: parse_url(
    (string) env('APP_URL', 'http://localhost'),
    PHP_URL_HOST,
) ?: 'localhost';

return [
    /*
     * APP_DOMAIN is useful when the app URL includes a port or when local DNS
     * maps a wildcard domain separately. Otherwise the host is derived from
     * APP_URL, so development and staging do not inherit a production domain.
     */
    'domain' => $platformDomain,
    /*
     * Retired. Storefronts live on {store}.{domain} and nowhere else, so the
     * slug is never a path segment. Kept as a config key only because removing
     * it would break config caching in environments that still set it.
     */
    'path_routes' => env('STOREFRONT_PATH_ROUTES', false),
    'gateway' => env('PAYMENT_GATEWAY', 'paystack'),
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
        'broadcasts' => env('FEATURE_BROADCASTS', false),
        'directory' => env('FEATURE_DIRECTORY', true),
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
        'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET', 'domains.'.$platformDomain),
        'connect_timeout' => 3,
        'timeout' => 5,
        // How long to keep re-checking a domain nobody has finished pointing at
        // us yet. A seller is not in a hurry, but a CNAME can take hours.
        'attempts_before_giving_up' => 40,
    ],
];
