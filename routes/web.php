<?php

use App\Domains\Auth\Http\Controllers\EmailVerificationController;
use App\Domains\Auth\Http\Controllers\LoginController;
use App\Domains\Auth\Http\Controllers\PasswordResetController;
use App\Domains\Auth\Http\Controllers\RegisterController;
use App\Domains\Catalog\Http\Controllers\CategoryController;
use App\Domains\Catalog\Http\Controllers\ProductController;
use App\Domains\Catalog\Http\Controllers\ProductVariantController;
use App\Domains\Orders\Http\Controllers\OrderController;
use App\Domains\Seller\Http\Controllers\DashboardController;
use App\Domains\Seller\Http\Controllers\OnboardingController;
use App\Domains\Seller\Http\Controllers\StorePageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
});

Route::middleware(['auth', 'seller.active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('throttle:6,1');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send')->middleware('throttle:6,1');

    Route::middleware('onboarding.complete')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('verified');
        Route::get('orders', [OrderController::class, 'index'])->name('dashboard.orders.index')->middleware('verified');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('dashboard.orders.show')->middleware('verified');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('dashboard.orders.status')->middleware('verified');
        Route::get('/page', [StorePageController::class, 'edit'])->name('page.editor')->middleware('verified');
        Route::post('/page', [StorePageController::class, 'update'])->name('page.editor.update')->middleware('verified');
        Route::post('products/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy')->middleware('verified');
        Route::patch('products/{product}/stock', [ProductController::class, 'adjustStock'])->name('products.stock')->middleware('verified');
        Route::resource('products.variants', ProductVariantController::class)->shallow()->middleware('verified');
        Route::resource('products', ProductController::class)->except(['show'])->middleware('verified');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store')->middleware('verified');
    });

    Route::prefix('onboarding')->name('onboarding.')->middleware('verified')->group(function () {
        Route::get('/{step}', [OnboardingController::class, 'show'])->name('show')->whereIn('step', ['business', 'theme', 'brand', 'review']);
        Route::post('/{step}', [OnboardingController::class, 'store'])->name('store')->whereIn('step', ['business', 'theme', 'brand', 'review']);
    });
});
