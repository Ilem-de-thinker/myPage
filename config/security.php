<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Strict Transport Security
    |--------------------------------------------------------------------------
    |
    | Sent only for responses that actually arrive over HTTPS. The setting is
    | deliberately not forced for plain HTTP, including local development and
    | any edge that has not yet terminated TLS for a custom seller domain.
    */
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),

    'csp' => [
        /*
        | The first CSP stage is report-only. The storefront still uses Alpine
        | expressions and inline event handlers, so enforcement first would
        | trade an observability step for a production outage.
        */
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),
        'report_uri' => env('SECURITY_CSP_REPORT_URI', '/webhooks/csp-report'),
    ],
];
