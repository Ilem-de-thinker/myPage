<?php

use App\Domains\Admin\Http\Controllers\AdminController;
use App\Domains\Admin\Http\Controllers\AdminDashboardController;
use Illuminate\Support\Facades\Route;

/*
| The internal operations panel. Hidden from search engines, and every action
| inside it writes an audit entry before it takes effect.
*/

Route::name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('index');
    Route::get('badges', [AdminController::class, 'badges'])->name('badges');
    Route::get('escrows', [AdminController::class, 'escrows'])->name('escrows');
    Route::get('stores', [AdminController::class, 'stores'])->name('stores');
    Route::get('transactions', [AdminController::class, 'transactions'])->name('transactions');
    Route::get('payouts', [AdminController::class, 'payouts'])->name('payouts');
    Route::get('features', [AdminController::class, 'features'])->name('features');
    Route::post('features', [AdminController::class, 'updateFeature'])->name('features.update');

    /*
    | Plans. Config seeds what a plan *is*; this is where its price and its link to
    | the gateway live, because both of those change after launch and neither should
    | be changed by a deploy.
    */
    Route::get('plans', [AdminController::class, 'plans'])->name('plans');
    Route::post('plans/{plan}/connect', [AdminController::class, 'createGatewayPlan'])->name('plans.connect');
    Route::post('plans/{plan}/price', [AdminController::class, 'updatePlanPrice'])->name('plans.price');

    Route::get('audit', [AdminController::class, 'auditLog'])->name('audit');

    /*
     * These act on somebody else's row. Route model binding is still by key, so
     * the id has to exist; EnsureUserIsAdmin lifts the tenant scope that would
     * otherwise 404 an admin looking at the wrong shop.
     */
    Route::post('badges/{application}', [AdminController::class, 'decideBadge'])->name('badges.decide');
    Route::post('escrows/{escrow}', [AdminController::class, 'resolveDispute'])->name('escrows.resolve');
    Route::post('stores/{storePage}/suspend', [AdminController::class, 'suspend'])->name('stores.suspend');
    Route::post('stores/{storePage}/reinstate', [AdminController::class, 'reinstate'])->name('stores.reinstate');
});
