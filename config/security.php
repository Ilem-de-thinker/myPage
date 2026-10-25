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
        | Enforce the baseline policy now. The policy retains only the existing
        | Blade component registrations still use inline scripts; Alpine itself
        | uses the CSP build and no longer requires unsafe-eval.
        */
        'enforce' => (bool) env('SECURITY_CSP_ENFORCE', true),
        'report_only' => (bool) env('SECURITY_CSP_REPORT_ONLY', true),
        'report_uri' => env('SECURITY_CSP_REPORT_URI', '/webhooks/csp-report'),
    ],
];
