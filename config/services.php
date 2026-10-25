<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'paystack' => [
        'secret' => env('PAYSTACK_SECRET'),
        'public' => env('PAYSTACK_PUBLIC'),
    ],

    'flutterwave' => [
        'secret' => env('FLUTTERWAVE_SECRET'),
        // Used to verify inbound webhook signatures, not for API calls.
        'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
    ],

    /*
    | Google, for sellers who would rather sign in with the account they already
    | have than remember another password on a phone.
    |
    | The redirect is the one thing here that must be declared rather than
    | derived: Google only redirects to origins you list in the console, and
    | app_url is wrong on every environment except production.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        'maps_browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

];
