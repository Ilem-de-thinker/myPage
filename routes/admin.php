<?php

use App\Domains\Admin\Http\Controllers\AdminAnalyticsController;
use App\Domains\Admin\Http\Controllers\AdminController;
use App\Domains\Admin\Http\Controllers\AdminDashboardController;
use App\Domains\Admin\Http\Controllers\PlatformSettingsController;
use App\Domains\Admin\Http\Controllers\SupportController;
use Illuminate\Support\Facades\Route;

/*
| The internal operations panel. Hidden from search engines, and every action
| inside it writes an audit entry before it takes effect.
*/

Route::name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('index');
    Route::get('analytics', AdminAnalyticsController::class)->name('analytics');
    Route::get('badges', [AdminController::class, 'badges'])->name('badges');
    Route::get('badges/{application}/document', [AdminController::class, 'badgeDocument'])->name('badges.document');
    Route::get('stores', [AdminController::class, 'stores'])->name('stores');
    Route::get('transactions', [AdminController::class, 'transactions'])->name('transactions');
    Route::get('reports', [AdminController::class, 'reports'])->name('reports');
    Route::post('reports/{report}', [AdminController::class, 'updateReport'])->name('reports.update');
    Route::get('support', [SupportController::class, 'index'])->name('support');
    Route::get('support/{supportRequest}', [SupportController::class, 'show'])->name('support.show');
    Route::post('support/{supportRequest}', [SupportController::class, 'update'])->name('support.update');
    Route::get('gateway-health', [AdminController::class, 'gatewayHealth'])->name('gateway-health');
    Route::get('features', [AdminController::class, 'features'])->name('features');
    Route::post('features', [AdminController::class, 'updateFeature'])->name('features.update');
    Route::get('settings', [PlatformSettingsController::class, 'edit'])->name('settings');
    Route::post('settings', [PlatformSettingsController::class, 'update'])->name('settings.update');

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
    Route::post('stores/{storePage}/suspend', [AdminController::class, 'suspend'])->name('stores.suspend');
    Route::post('stores/{storePage}/reinstate', [AdminController::class, 'reinstate'])->name('stores.reinstate');
    Route::post('stores/{storePage}/directory', [AdminController::class, 'updateDirectory'])->name('stores.directory');
});
