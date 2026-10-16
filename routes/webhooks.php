<?php

use App\Domains\Payments\Http\Controllers\PaymentWebhookController;
use App\Shared\Http\Controllers\CspReportController;
use Illuminate\Support\Facades\Route;

Route::post('paystack', [PaymentWebhookController::class, 'paystack'])->name('webhooks.paystack');
Route::post('flutterwave', [PaymentWebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');

// CSP violation reports are unauthenticated browser telemetry. The reporting
// browser cannot present a session or CSRF token, which is why this route
// deliberately lives outside the web group; throttling keeps it from becoming
// a log-spam endpoint.
Route::post('csp-report', CspReportController::class)->middleware('throttle:30,1')->name('webhooks.csp-report');
