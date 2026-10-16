<?php

use App\Domains\Auth\Http\Controllers\EmailVerificationController;
use App\Domains\Auth\Http\Controllers\GoogleAuthController;
use App\Domains\Auth\Http\Controllers\LoginController;
use App\Domains\Auth\Http\Controllers\PasswordResetController;
use App\Domains\Auth\Http\Controllers\RegisterController;
use App\Domains\Billing\Http\Controllers\BillingController;
use App\Domains\Booking\Http\Controllers\BookingController;
use App\Domains\Booking\Http\Controllers\RescheduleBookingController;
use App\Domains\Booking\Http\Controllers\ServiceController;
use App\Domains\Booking\Http\Controllers\StaffMemberController;
use App\Domains\Booking\Http\Controllers\TimeOffController;
use App\Domains\Booking\Http\Controllers\WorkingHourController;
use App\Domains\Catalog\Http\Controllers\CategoryController;
use App\Domains\Catalog\Http\Controllers\ProductController;
use App\Domains\Catalog\Http\Controllers\ProductVariantController;
use App\Domains\Catalog\Http\Controllers\ReviewInboxController;
use App\Domains\Customers\Http\Controllers\CustomerController;
use App\Domains\Directory\Http\Controllers\DirectoryController;
use App\Domains\Directory\Http\Controllers\DiscoveryController;
use App\Domains\Directory\Http\Controllers\RobotsTxtController;
use App\Domains\Domains\Http\Controllers\DomainBindingController;
use App\Domains\Orders\Http\Controllers\AbandonedCartController;
use App\Domains\Orders\Http\Controllers\OrderController;
use App\Domains\Payments\Http\Controllers\PayoutController;
use App\Domains\Seller\Http\Controllers\BadgeController;
use App\Domains\Seller\Http\Controllers\DashboardController;
use App\Domains\Seller\Http\Controllers\OnboardingController;
use App\Domains\Seller\Http\Controllers\StorefrontController;
use App\Domains\Seller\Http\Controllers\StorefrontSettingsController;
use App\Domains\Seller\Http\Controllers\StorePageController;
use App\Domains\Seller\Models\BlockRenderer;
use App\Domains\Seller\Services\SlugResolver;
use App\Shared\Support\Hostname;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
    The landing page. When the request arrives on one of a seller's own domains
    this is not the platform's home page at all — it is that seller's shop, so
    the storefront is dispatched instead.

    The storefront routes carry an optional {slug} prefix and are registered
    below this one, so "/" is matched here first either way. Forwarding is what
    keeps a seller's domain showing their shop rather than our marketing page,
    which would otherwise be reachable on every hostname they own.
*/
Route::get('/', function (Request $request) {
    $host = Hostname::withoutWww($request->getHost());

    // One of a seller's own domains: this is their shop, not our home page.
    $page = app(SlugResolver::class)->resolveByHost($host);

    if ($page !== null) {
        return app(StorefrontController::class)->show(
            $page,
            app(BlockRenderer::class),
        );
    }

    /*
        Only on our own apex, or on loopback during development.

        A hostname pointed at us that belongs to no seller gets a 404 rather
        than our marketing page: serving the platform's front door on somebody
        else's domain is how a parked or misconfigured domain ends up looking
        like an official mypage.ng site. It is also what makes an unverified
        custom domain fail closed — the seller sees nothing rather than the
        platform's home page, which is not theirs.
    */
    $allowedHosts = array_filter([
        Hostname::withoutWww(strtolower(trim((string) config('mypage.domain', 'mypage.test')))),
        'localhost',
        '127.0.0.1',
    ]);

    abort_unless(in_array($host, $allowedHosts, true), 404);

    return view('pages.landing');
})->name('home');

// The living style guide: every token, type step, card and button the platform
// and the storefronts are built from, rendered from the same stylesheet they use
// rather than described in prose.
Route::get('/design-system', function () {
    return view('pages.design-system');
})->name('design-system');

// Before the {slug} storefront catch-all, and ahead of anything else that might
// want the name. Served by a controller so the sitemap line carries the real host.
Route::get('/robots.txt', RobotsTxtController::class)->name('robots');

// The directory. Off until switched on, because a directory over three pages
// looks worse than no directory at all.
Route::middleware('feature:directory')->group(function (): void {
    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory.index');
    Route::get('/directory/{state}', [DirectoryController::class, 'area'])->name('directory.state');
    Route::get('/directory/{state}/{category}', [DirectoryController::class, 'area'])->name('directory.area');
});

/*
    The discovery hub: every business that has opted into being found, searched by
    what a buyer is looking for rather than browsed by state. One route serves both
    the whole page and the live-filter fragment, so a filtered URL is the same URL a
    visitor can share, bookmark, or page through.
*/
Route::middleware('feature:discovery')->group(function (): void {
    Route::get('/discover', [DiscoveryController::class, 'index'])->name('discovery.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');

    /*
        Google sign-in.

        Both legs are guest-only. The callback in particular has to stay
        reachable while logged out, because that is the state a Google redirect
        arrives in.
    */
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

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

    // subscription.valid gates dashboard writes only. It is deliberately absent
    // from the storefront routes: a failed debit must never take a shop offline.
    Route::middleware(['onboarding.complete', 'subscription.valid'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard')->middleware('verified');
        Route::get('orders', [OrderController::class, 'index'])->name('dashboard.orders.index')->middleware('verified');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('dashboard.orders.show')->middleware('verified');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('dashboard.orders.status')->middleware('verified');
        Route::post('orders/{order}/escrow/dispute', [OrderController::class, 'disputeEscrow'])->name('dashboard.orders.escrow-dispute')->middleware('verified');
        Route::post('orders/{order}/escrow/dispute', [OrderController::class, 'disputeEscrow'])->name('dashboard.orders.escrow-dispute')->middleware('verified');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index')->middleware('verified');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show')->middleware('verified');
        Route::post('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->middleware('verified');
        Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store')->middleware('verified');
        Route::delete('customers/{customer}/notes/{note}', [CustomerController::class, 'destroyNote'])->name('customers.notes.destroy')->middleware('verified');
        Route::get('verified', [BadgeController::class, 'create'])->name('badge.apply')->middleware('verified');
        Route::post('verified', [BadgeController::class, 'store'])->name('badge.submit')->middleware('verified');
        Route::get('abandoned-carts', [AbandonedCartController::class, 'index'])->name('abandoned-carts.index')->middleware('verified');
        Route::delete('abandoned-carts/{cart}', [AbandonedCartController::class, 'destroy'])->name('abandoned-carts.destroy')->middleware('verified');
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index')->middleware('verified');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show')->middleware('verified');
        Route::post('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status')->middleware('verified');
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel')->middleware('verified');
        Route::get('bookings/{booking}/reschedule', [RescheduleBookingController::class, 'show'])->name('bookings.reschedule.show')->middleware('verified');
        Route::post('bookings/{booking}/reschedule', [RescheduleBookingController::class, 'store'])->name('bookings.reschedule.store')->middleware('verified');
        Route::resource('services', ServiceController::class)->except(['show'])->middleware('verified');
        // Reviews. Moderation is the whole feature: nothing a customer writes
        // reaches the public page without passing through this queue.
        Route::get('/reviews', [ReviewInboxController::class, 'index'])->name('reviews.index')->middleware('verified');
        Route::post('/reviews/{review}/approve', [ReviewInboxController::class, 'approve'])->name('reviews.approve')->middleware('verified');
        Route::post('/reviews/{review}/reject', [ReviewInboxController::class, 'reject'])->name('reviews.reject')->middleware('verified');
        Route::post('/reviews/{review}/feature', [ReviewInboxController::class, 'feature'])->name('reviews.feature')->middleware('verified');
        // time-off is registered first so it is not swallowed by availability/{staff?}.
        Route::get('availability/time-off', [TimeOffController::class, 'index'])->name('time-off.index')->middleware('verified');
        Route::post('availability/time-off', [TimeOffController::class, 'store'])->name('time-off.store')->middleware('verified');
        Route::delete('availability/time-off/{timeOff}', [TimeOffController::class, 'destroy'])->name('time-off.destroy')->middleware('verified');
        // The staff member is a real path segment, not a query string, so implicit
        // model binding can resolve it.
        Route::get('availability/{staff?}', [WorkingHourController::class, 'index'])->name('availability.index')->middleware('verified');
        // Both verbs, not PUT alone. This screen is a plain HTML form, and a form
        // that depends on a hidden _method field is a form that 405s the moment
        // that field is missing — a stale tab, a stripped field, a hand-written
        // request. The action replaces the whole week either way, so POST and
        // PUT are the same request.
        Route::match(['post', 'put'], 'availability/{staff?}', [WorkingHourController::class, 'update'])->name('availability.update')->middleware('verified');
        // {staffMember} rather than the resource's default {staff}, so implicit model
        // binding finds the matching controller argument by name.
        Route::get('staff', [StaffMemberController::class, 'index'])->name('staff.index')->middleware('verified');
        Route::post('staff', [StaffMemberController::class, 'store'])->name('staff.store')->middleware('verified');
        Route::put('staff/{staffMember}', [StaffMemberController::class, 'update'])->name('staff.update')->middleware('verified');
        Route::delete('staff/{staffMember}', [StaffMemberController::class, 'destroy'])->name('staff.destroy')->middleware('verified');
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index')->middleware('verified');

        // Withdrawals. Self-service: the money is already the seller's by the
        // time it gets here, so waiting on an admin would only add delay.
        Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
        Route::post('payouts/bank', [PayoutController::class, 'saveBankDetails'])->name('payouts.bank');
        Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store');
        Route::post('billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe')->middleware('verified');
        Route::post('billing/manual', [BillingController::class, 'requestManualSubscription'])->name('billing.manual')->middleware('verified');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel')->middleware('verified');
        Route::get('billing/manage-card', [BillingController::class, 'manageCard'])->name('billing.manage-card')->middleware('verified');
        Route::get('billing/callback', [BillingController::class, 'callback'])->name('billing.callback')->middleware('verified');
        // The storefront settings screen. Replaces the old page builder: no
        // enable/disable switches and no drag handles, just content.
        Route::get('storefront', [StorefrontSettingsController::class, 'edit'])->name('storefront.edit')->middleware('verified');
        Route::post('storefront/{tab}', [StorefrontSettingsController::class, 'update'])->name('storefront.update')->middleware('verified');

        // Custom domains. Behind the platform flag as well as the plan check:
        // the flag keeps the feature invisible before launch, and the plan check
        // keeps it off a shop that has not paid for it.
        Route::middleware('feature:custom_domains')->group(function (): void {
            Route::get('domains', [DomainBindingController::class, 'index'])->name('domains.index')->middleware('verified');
            Route::post('domains', [DomainBindingController::class, 'store'])->name('domains.store')->middleware('verified');
            Route::post('domains/{binding}/verify', [DomainBindingController::class, 'verify'])->name('domains.verify')->middleware('verified');
            Route::delete('domains/{binding}', [DomainBindingController::class, 'destroy'])->name('domains.destroy')->middleware('verified');
        });
        Route::post('/page/publish', [StorePageController::class, 'publish'])->name('page.publish')->middleware('verified');
        Route::post('/page/unpublish', [StorePageController::class, 'unpublish'])->name('page.unpublish')->middleware('verified');
        Route::post('products/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy')->middleware('verified');
        Route::patch('products/{product}/stock', [ProductController::class, 'adjustStock'])->name('products.stock')->middleware('verified');
        Route::resource('products.variants', ProductVariantController::class)->shallow()->middleware('verified');
        Route::resource('products', ProductController::class)->except(['show'])->middleware('verified');
        Route::post('categories/bulk-destroy', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy')->middleware('verified');
        Route::resource('categories', CategoryController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->middleware('verified');
    });

    Route::prefix('onboarding')->name('onboarding.')->middleware('verified')->group(function () {
        Route::get('/{step}', [OnboardingController::class, 'show'])->name('show')->whereIn('step', ['business', 'theme', 'brand', 'review']);
        Route::post('/{step}', [OnboardingController::class, 'store'])->name('store')->whereIn('step', ['business', 'theme', 'brand', 'review']);
    });
});
