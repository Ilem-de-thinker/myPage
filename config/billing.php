<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Subscription plans
     |--------------------------------------------------------------------------
     |
     | Prices are in kobo, like every other amount in this application. The
     | seeder reads this file, so a plan only ever exists in one place.
     |
     */

    'currency' => 'NGN',

    /*
     |--------------------------------------------------------------------------
     | Custom domain eligibility
     |--------------------------------------------------------------------------
     |
     | Which plans may point their own hostname at their shop. Read by the
     | Domains domain, so the rule lives with billing rather than being scattered
     | through the controller.
     */
    'custom_domain_plans' => ['pro'],

    'directory_featured_plans' => ['growth', 'pro'],

    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'price_kobo' => 350000,        // ₦3,500 / month
            'interval' => 'monthly',
            'trial_days' => 14,
            'max_products' => 25,
            'description' => 'A page, products and payments. Enough to start selling.',
            'is_default' => false,
            'is_featured' => false,
            /*
             | The PLN_... code Paystack bills this plan against. Create the plan
             | once on the gateway (dashboard or POST /plan), then paste the code
             | here. Null means the seller is charged the plan price one period
             | at a time by hand and nothing renews automatically — which is the
             | honest, recoverable failure for a plan nobody has set up yet.
             */
            'gateway_plan_code' => env('PAYSTACK_PLAN_STARTER'),
        ],
        'growth' => [
            'name' => 'Growth',
            'price_kobo' => 950000,        // ₦9,500 / month
            'interval' => 'monthly',
            'trial_days' => 14,
            'max_products' => 200,
            'description' => 'More products and priority support.',
            'is_default' => true,
            'is_featured' => true,
            'gateway_plan_code' => env('PAYSTACK_PLAN_GROWTH'),
        ],
        'pro' => [
            'name' => 'Pro',
            'price_kobo' => 2500000,       // ₦25,000 / month
            'interval' => 'monthly',
            'trial_days' => 0,
            'max_products' => null,        // null means unlimited
            'description' => 'Unlimited products, custom domain and broadcast credits.',
            'is_default' => false,
            'is_featured' => false,
            'gateway_plan_code' => env('PAYSTACK_PLAN_PRO'),
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Lifecycle
     |--------------------------------------------------------------------------
     |
     | A lapsed subscription must never take a paying seller offline without
     | warning, so a grace period runs before anything is restricted.
     |
     */

    'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 7),

    // Manual bank transfer approval, for sellers without a card.
    'manual_transfers' => [
        'bank_name' => env('TRANSFER_BANK_NAME', 'mypage.ng'),
        'account_number' => env('TRANSFER_ACCOUNT_NUMBER'),
        'instructions' => env('TRANSFER_INSTRUCTIONS', 'Send your payment receipt to support@mypage.ng and we will confirm your subscription within one working day.'),
    ],

    'notifications' => [
        // Warn before anything lapses, and again when the grace period starts.
        'expiring_soon_days' => 7,
        'grace_reminder_days' => 3,
    ],
];
