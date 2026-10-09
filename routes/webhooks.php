<?php

use App\Domains\Payments\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('paystack', [PaymentWebhookController::class, 'paystack'])->name('webhooks.paystack');
Route::post('flutterwave', [PaymentWebhookController::class, 'flutterwave'])->name('webhooks.flutterwave');
