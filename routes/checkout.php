<?php

use App\Domains\Payments\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('checkout/callback/{reference}', [CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('checkout/thanks/{order}', [CheckoutController::class, 'thanks'])->name('checkout.thanks');
