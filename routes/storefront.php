<?php

use App\Domains\Seller\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'show'])->name('storefront.show');
Route::get('/about', [StorefrontController::class, 'about'])->name('storefront.about');
Route::get('/contact', [StorefrontController::class, 'contact'])->name('storefront.contact');
Route::get('/products', [StorefrontController::class, 'products'])->name('storefront.products');
Route::get('/products/{productSlug}', [StorefrontController::class, 'productShow'])->name('storefront.product');
Route::get('/services', [StorefrontController::class, 'services'])->name('storefront.services');
Route::get('/services/{serviceSlug}', [StorefrontController::class, 'serviceShow'])->name('storefront.service');
Route::get('/cart', [StorefrontController::class, 'cart'])->name('storefront.cart');
Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('storefront.checkout');
Route::get('/book', [StorefrontController::class, 'book'])->name('storefront.book');
Route::get('/track-order', [StorefrontController::class, 'trackOrder'])->name('storefront.track-order');
Route::post('/checkout/whatsapp', [StorefrontController::class, 'whatsappOrder'])->name('storefront.checkout.whatsapp');
