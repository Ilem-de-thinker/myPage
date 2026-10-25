<?php

return [
    'terms_version' => env('TERMS_VERSION', '2026-10-23'),
    'privacy_version' => env('PRIVACY_VERSION', '2026-10-23'),
    'controller_name' => env('LEGAL_CONTROLLER_NAME', 'mypage.ng'),
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'legal@mypage.ng'),
    'contact_address' => env('LEGAL_CONTACT_ADDRESS', 'Nigeria'),
    'kyc_retention_years' => (int) env('KYC_RETENTION_YEARS', 5),
];
