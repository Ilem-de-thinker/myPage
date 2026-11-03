<?php

use App\Domains\Ai\Http\Controllers\AiController;
use App\Domains\Ai\Http\Controllers\AiDraftController;
use App\Domains\Ai\Http\Controllers\AiTaskController;
use App\Domains\Analytics\Http\Controllers\AnalyticsController;
use App\Domains\Auth\Http\Controllers\AccountSecurityController;
use App\Domains\Auth\Http\Controllers\DataSubjectRequestController;
use App\Domains\Auth\Http\Controllers\EmailVerificationController;
use App\Domains\Auth\Http\Controllers\GoogleAuthController;
use App\Domains\Auth\Http\Controllers\LoginController;
use App\Domains\Auth\Http\Controllers\PasswordConfirmationController;
use App\Domains\Auth\Http\Controllers\PasswordResetController;
use App\Domains\Auth\Http\Controllers\PolicyController;
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
use App\Domains\Catalog\Http\Controllers\ProductImportController;
use App\Domains\Catalog\Http\Controllers\ProductVariantController;
use App\Domains\Catalog\Http\Controllers\ReviewInboxController;
use App\Domains\Chat\Http\Controllers\ChatController;
use App\Domains\Customers\Http\Controllers\CustomerController;
use App\Domains\Directory\Http\Controllers\DirectoryController;
use App\Domains\Directory\Http\Controllers\DirectoryListingReportController;
use App\Domains\Directory\Http\Controllers\DirectoryRedirectController;
use App\Domains\Directory\Http\Controllers\DiscoveryController;
use App\Domains\Directory\Http\Controllers\MarketplaceAssistantController;
use App\Domains\Directory\Http\Controllers\PlatformLandingController;
use App\Domains\Directory\Http\Controllers\PublicDirectoryController;
use App\Domains\Directory\Http\Controllers\RobotsTxtController;
use App\Domains\Directory\Services\DirectoryCatalogQuery;
use App\Domains\Directory\Services\DiscoveryQuery;
use App\Domains\Domains\Http\Controllers\DomainBindingController;
use App\Domains\Orders\Http\Controllers\AbandonedCartController;
use App\Domains\Orders\Http\Controllers\OrderController;
use App\Domains\Payments\Http\Controllers\SellerGatewayController;
use App\Domains\Seller\Http\Controllers\BadgeController;
use App\Domains\Seller\Http\Controllers\CarouselSlideController;
use App\Domains\Seller\Http\Controllers\DashboardController;
use App\Domains\Seller\Http\Controllers\NotificationController;
use App\Domains\Seller\Http\Controllers\OnboardingController;
use App\Domains\Seller\Http\Controllers\PwaController;
use App\Domains\Seller\Http\Controllers\StorefrontController;
use App\Domains\Seller\Http\Controllers\StorefrontSettingsController;
use App\Domains\Seller\Http\Controllers\StorePageController;
use App\Domains\Seller\Http\Controllers\SupportController;
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
        Hostname::withoutWww(strtolower(trim((string) config('mypage.domain')))),
        'localhost',
        '127.0.0.1',
    ]);

    abort_unless(in_array($host, $allowedHosts, true), 404);

    return app(PlatformLandingController::class)($request, app(DiscoveryQuery::class), app(DirectoryCatalogQuery::class));
})->name('home');

// The living style guide: every token, type step, card and button the platform
// and the storefronts are built from, rendered from the same stylesheet they use
// rather than described in prose.
Route::get('/design-system', function () {
    return view('pages.design-system');
})->name('design-system');
Route::get('/theme-preview', fn () => view('pages.theme-preview'))->name('theme-preview');

// Before the {slug} storefront catch-all, and ahead of anything else that might
// want the name. Served by a controller so the sitemap line carries the real host.
Route::get('/robots.txt', RobotsTxtController::class)->name('robots');
Route::get('/manifest.webmanifest', [PwaController::class, 'platformManifest'])->name('pwa.manifest');
Route::get('/service-worker.js', [PwaController::class, 'platformServiceWorker'])->name('pwa.worker');
Route::get('/offline', [PwaController::class, 'platformOffline'])->name('pwa.offline');
Route::get('/pwa/icon/{size}', [PwaController::class, 'platformIcon'])->whereIn('size', ['192', '512'])->name('pwa.icon');

Route::get('/legal/{policy}', [PolicyController::class, 'show'])
    ->whereIn('policy', ['terms', 'privacy', 'refunds', 'merchant-agreement', 'cookies', 'acceptable-use', 'disputes', 'ip', 'contact', 'kyc-verification', 'ai-usage', 'prohibited-items', 'faq', 'about'])
    ->name('legal.policy');
Route::get('/data-request', [PolicyController::class, 'requestData'])->name('legal.data-request');
Route::post('/data-request', [DataSubjectRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('legal.data-request.store');

// The public discovery directory. Store listing remains opt-in at the row level.
// The directory stays off until it has enough published sellers to be useful.
Route::middleware('feature:directory')->group(function (): void {
    Route::get('/directory', [PublicDirectoryController::class, 'stores'])->name('directory.index');
    Route::get('/directory/stores', [PublicDirectoryController::class, 'stores'])->name('directory.stores');
    Route::get('/directory/products', [PublicDirectoryController::class, 'products'])->name('directory.products');
    Route::get('/directory/services', [PublicDirectoryController::class, 'services'])->name('directory.services');
    Route::get('/directory/category/{category}', [PublicDirectoryController::class, 'category'])->name('directory.category');
    Route::get('/directory/store/{storePage}', [PublicDirectoryController::class, 'store'])->name('directory.store');
    Route::get('/directory/{state}', [DirectoryController::class, 'area'])->name('directory.state');
    Route::get('/directory/{state}/{category}', [DirectoryController::class, 'area'])->name('directory.area');
    Route::post('/directory/assist', MarketplaceAssistantController::class)->middleware('throttle:20,1')->name('directory.assist');
    Route::post('/directory/{type}/{id}/report', DirectoryListingReportController::class)->middleware('throttle:10,10')->name('directory.report');
});

Route::get('/go/{type}/{id}', DirectoryRedirectController::class)->middleware('throttle:120,1')->name('directory.go');

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
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:20,1')->name('login.store');

    /*
        Google sign-in.

        Both legs are guest-only. The callback in particular has to stay
        reachable while logged out, because that is the state a Google redirect
        arrives in.
    */
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');

    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:6,1');
});

/*
     Google sign-in callback. Deliberately not in the guest group: an
     authenticated seller passing through Google to confirm a money-routing
     action lands here too, and the controller distinguishes the two.
*/
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'seller.active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/support', [SupportController::class, 'index'])->name('support.index')->middleware('policies.current');
    Route::get('/support/{supportRequest}', [SupportController::class, 'show'])->name('support.show')->middleware('policies.current');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store')->middleware(['policies.current', 'throttle:5,1']);
    Route::post('/support/{supportRequest}/reply', [SupportController::class, 'reply'])
        ->name('support.reply')
        ->middleware(['policies.current', 'throttle:10,1']);

    /*
        Confirming a password before a money-routing change.

        Laravel's password.confirm middleware redirects here and remembers where
        the seller was going. Kept outside the business-verified group because a
        seller may need to confirm on the way to a screen that is itself gated;
        a redirect loop is worse than the extra route.
    */
    Route::get('/auth/google/confirm', [GoogleAuthController::class, 'confirmRedirect'])->name('auth.google.confirm');
    Route::get('/confirm-password', [PasswordConfirmationController::class, 'show'])->name('password.confirm');
    Route::post('/confirm-password', [PasswordConfirmationController::class, 'store'])->middleware('throttle:6,1');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->name('verification.verify')->middleware('throttle:6,1');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send')->middleware('throttle:6,1');

    // Re-acceptance when a published policy version changes. Kept outside the
    // dashboard group so the middleware cannot redirect this screen to itself.
    Route::get('/legal/consent', [PolicyController::class, 'consent'])->name('legal.consent');
    Route::post('/legal/consent', [PolicyController::class, 'accept'])->name('legal.consent.accept')->middleware('throttle:6,1');

    // Email verification protects the dashboard. Business verification is a
    // buyer-facing trust signal and is deliberately not a dashboard gate.
    //
    // 'verified' is Laravel's own email check — confirm the address you signed up
    // with. 'business.verified' remains available for future payment-specific
    // protection, but must not gate the seller's dashboard.
    //
    // subscription.valid gates dashboard writes only. It is deliberately absent
    // from the storefront routes: a failed debit must never take a shop offline.
    Route::middleware(['verified', 'onboarding.complete', 'subscription.valid', 'policies.current'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read');
        Route::get('orders', [OrderController::class, 'index'])->name('dashboard.orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('dashboard.orders.show');
        Route::get('chat', [ChatController::class, 'index'])->name('dashboard.chat.index');
        Route::get('chat/refresh', [ChatController::class, 'refresh'])->name('dashboard.chat.refresh');
        Route::get('chat/{thread}', [ChatController::class, 'show'])->name('dashboard.chat.show');
        Route::post('chat/{thread}/messages', [ChatController::class, 'store'])->name('dashboard.chat.store');
        Route::post('chat/{thread}/ai-suggestion', [ChatController::class, 'aiSuggestion'])->middleware('throttle:20,1')->name('dashboard.chat.ai-suggestion');
        Route::post('chat/{thread}/summary', [ChatController::class, 'summarize'])->middleware('throttle:10,1')->name('dashboard.chat.summary');
        Route::post('chat/{thread}/translate', [ChatController::class, 'translate'])->middleware('throttle:20,1')->name('dashboard.chat.translate');
        Route::post('chat-ai/toggle', [ChatController::class, 'toggleAi'])->name('dashboard.chat.ai-toggle');
        Route::get('ai', fn () => redirect()->route('dashboard'))->name('dashboard.ai.index');
        Route::get('ai/usage', [AiController::class, 'usage'])->middleware('throttle:30,1')->name('dashboard.ai.usage');
        Route::get('ai/drafts', [AiDraftController::class, 'index'])->name('dashboard.ai.drafts');
        Route::get('ai/drafts/{draft}/versions', [AiDraftController::class, 'versions'])->name('dashboard.ai.drafts.versions');
        Route::post('ai/drafts/{draft}/approve', [AiDraftController::class, 'approve'])->name('dashboard.ai.drafts.approve');
        Route::post('ai/drafts/{draft}/reject', [AiDraftController::class, 'reject'])->name('dashboard.ai.drafts.reject');
        Route::patch('ai/drafts/{draft}', [AiDraftController::class, 'update'])->name('dashboard.ai.drafts.update');
        Route::post('ai/drafts/{draft}/discard', [AiDraftController::class, 'discard'])->name('dashboard.ai.drafts.discard');
        Route::post('ai/drafts/{draft}/regenerate', [AiDraftController::class, 'regenerate'])->name('dashboard.ai.drafts.regenerate');
        Route::post('ai/drafts/{draft}/undo', [AiDraftController::class, 'undo'])->name('dashboard.ai.drafts.undo');
        Route::post('ai/drafts/confirm-selected', [AiDraftController::class, 'confirmSelected'])->name('dashboard.ai.drafts.confirm-selected');
        Route::get('ai/media', [AiDraftController::class, 'media'])->name('dashboard.ai.media');
        Route::get('ai/tasks', [AiTaskController::class, 'history'])->name('dashboard.ai.tasks.index');
        Route::get('ai/tasks/active', [AiTaskController::class, 'active'])->name('dashboard.ai.tasks.active');
        Route::get('ai/tasks/{task}', [AiTaskController::class, 'show'])->name('dashboard.ai.tasks.show');
        Route::post('ai/tasks/{task}/cancel', [AiTaskController::class, 'cancel'])->middleware('throttle:10,1')->name('dashboard.ai.tasks.cancel');
        Route::post('ai/tasks/{task}/retry-failed', [AiTaskController::class, 'retryFailed'])->middleware('throttle:10,1')->name('dashboard.ai.tasks.retry-failed');
        Route::get('ai/tasks/{task}/status', [AiTaskController::class, 'status'])->name('dashboard.ai.tasks.status');
        Route::get('analytics', [AnalyticsController::class, 'index'])->name('dashboard.analytics.index');
        Route::post('analytics/digest', [AnalyticsController::class, 'digest'])->middleware('throttle:5,1')->name('dashboard.analytics.digest');
        Route::post('analytics/ask', [AnalyticsController::class, 'ask'])->middleware('throttle:12,1')->name('dashboard.analytics.ask');
        Route::post('ai/run', [AiController::class, 'run'])->middleware('throttle:20,1')->name('dashboard.ai.run');
        Route::post('ai/contextual', [AiController::class, 'contextual'])->middleware('throttle:20,1')->name('dashboard.ai.contextual');
        Route::post('ai/logo-colors', [AiController::class, 'logoColors'])->middleware('throttle:10,1')->name('dashboard.ai.logo-colors');
        Route::post('ai/photo-groups', [AiController::class, 'suggestPhotoGroups'])->middleware('throttle:10,1')->name('dashboard.ai.photo-groups');
        Route::post('ai/estimate', [AiController::class, 'estimate'])->middleware('throttle:30,1')->name('dashboard.ai.estimate');
        Route::post('ai/batch', [AiController::class, 'batch'])->middleware('throttle:10,1')->name('dashboard.ai.batch');
        Route::post('ai/queue', [AiController::class, 'queue'])->middleware('throttle:10,1')->name('dashboard.ai.queue');
        Route::post('ai/photo-listing', [AiController::class, 'photoListing'])->middleware('throttle:10,1')->name('dashboard.ai.photo-listing');
        Route::get('ai/operations/{operation}', [AiController::class, 'status'])->name('dashboard.ai.status');
        Route::post('ai/operations/{operation}/retry', [AiController::class, 'retry'])->middleware('throttle:10,1')->name('dashboard.ai.retry');
        Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('dashboard.orders.status');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::post('customers/{customer}/notes', [CustomerController::class, 'storeNote'])->name('customers.notes.store');
        Route::delete('customers/{customer}/notes/{note}', [CustomerController::class, 'destroyNote'])->name('customers.notes.destroy');
        Route::get('abandoned-carts', [AbandonedCartController::class, 'index'])->name('abandoned-carts.index');
        Route::delete('abandoned-carts/{cart}', [AbandonedCartController::class, 'destroy'])->name('abandoned-carts.destroy');
        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::get('bookings/{booking}/reschedule', [RescheduleBookingController::class, 'show'])->name('bookings.reschedule.show');
        Route::post('bookings/{booking}/reschedule', [RescheduleBookingController::class, 'store'])->name('bookings.reschedule.store');
        Route::post('carousel/{slide}/move/{direction}', [CarouselSlideController::class, 'move'])->whereIn('direction', ['up', 'down'])->name('carousel.move');
        Route::post('services/generate-descriptions', [ServiceController::class, 'generateDescriptions'])->name('services.generate-descriptions');
        Route::post('services/apply-descriptions/{task}', [ServiceController::class, 'applyDescriptions'])->name('services.apply-descriptions');
        Route::post('services/undo-descriptions/{task}', [ServiceController::class, 'undoDescriptions'])->name('services.undo-descriptions');
        Route::resource('services', ServiceController::class)->except(['show']);
        // Reviews. Moderation is the whole feature: nothing a customer writes
        // reaches the public page without passing through this queue.
        Route::get('/reviews', [ReviewInboxController::class, 'index'])->name('reviews.index');
        Route::post('/reviews/{review}/approve', [ReviewInboxController::class, 'approve'])->name('reviews.approve');
        Route::post('/reviews/{review}/reject', [ReviewInboxController::class, 'reject'])->name('reviews.reject');
        Route::post('/reviews/{review}/feature', [ReviewInboxController::class, 'feature'])->name('reviews.feature');
        // time-off is registered first so it is not swallowed by availability/{staff?}.
        Route::get('availability/time-off', [TimeOffController::class, 'index'])->name('time-off.index');
        Route::post('availability/time-off', [TimeOffController::class, 'store'])->name('time-off.store');
        Route::delete('availability/time-off/{timeOff}', [TimeOffController::class, 'destroy'])->name('time-off.destroy');
        // The staff member is a real path segment, not a query string, so implicit
        // model binding can resolve it.
        Route::get('availability/{staff?}', [WorkingHourController::class, 'index'])->name('availability.index');
        // Both verbs, not PUT alone. This screen is a plain HTML form, and a form
        // that depends on a hidden _method field is a form that 405s the moment
        // that field is missing — a stale tab, a stripped field, a hand-written
        // request. The action replaces the whole week either way, so POST and
        // PUT are the same request.
        Route::match(['post', 'put'], 'availability/{staff?}', [WorkingHourController::class, 'update'])->name('availability.update');
        // {staffMember} rather than the resource's default {staff}, so implicit model
        // binding finds the matching controller argument by name.
        Route::get('staff', [StaffMemberController::class, 'index'])->name('staff.index');
        Route::post('staff', [StaffMemberController::class, 'store'])->name('staff.store');
        Route::put('staff/{staffMember}', [StaffMemberController::class, 'update'])->name('staff.update');
        Route::delete('staff/{staffMember}', [StaffMemberController::class, 'destroy'])->name('staff.destroy');
        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('account/password', [AccountSecurityController::class, 'show'])->name('account.password');
        Route::post('account/password', [AccountSecurityController::class, 'update'])->middleware('throttle:6,1')->name('account.password.update');

        /*
            The seller's own gateway keys. Writes sit behind password.confirm:
            a hijacked session must not be able to redirect a seller's money to
            another account by pasting a key into this form.
        */
        Route::get('payments/keys', [SellerGatewayController::class, 'index'])->name('payments.gateways.index');
        Route::post('payments/keys', [SellerGatewayController::class, 'store'])
            ->middleware('password.confirm')
            ->name('payments.gateways.store');
        Route::delete('payments/keys/{gateway}', [SellerGatewayController::class, 'destroy'])
            ->middleware('password.confirm')
            ->name('payments.gateways.destroy');

        // Withdrawals are gone: a seller is paid into their own gateway account,
        // so there is no platform balance to withdraw.

        Route::post('billing/subscribe', [BillingController::class, 'subscribe'])->name('billing.subscribe');
        Route::post('billing/manual', [BillingController::class, 'requestManualSubscription'])->name('billing.manual');
        Route::post('billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
        Route::get('billing/manage-card', [BillingController::class, 'manageCard'])->name('billing.manage-card');
        Route::get('billing/callback', [BillingController::class, 'callback'])->name('billing.callback');
        // The storefront settings screen. Replaces the old page builder: no
        // enable/disable switches and no drag handles, just content.
        Route::get('storefront', [StorefrontSettingsController::class, 'edit'])->name('storefront.edit');
        Route::post('storefront/{tab}', [StorefrontSettingsController::class, 'update'])->name('storefront.update');
        Route::post('storefront/carousel', [CarouselSlideController::class, 'store'])->name('carousel.store');
        Route::put('storefront/carousel/{slide}', [CarouselSlideController::class, 'update'])->name('carousel.update');
        Route::delete('storefront/carousel/{slide}', [CarouselSlideController::class, 'destroy'])->name('carousel.destroy');
        Route::post('storefront/carousel/{slide}/remove', [CarouselSlideController::class, 'destroy'])->name('carousel.remove');

        // Custom domains. Behind the platform flag as well as the plan check:
        // the flag keeps the feature invisible before launch, and the plan check
        // keeps it off a shop that has not paid for it.
        Route::middleware('feature:custom_domains')->group(function (): void {
            Route::get('domains', [DomainBindingController::class, 'index'])->name('domains.index');
            Route::post('domains', [DomainBindingController::class, 'store'])->name('domains.store');
            Route::post('domains/{binding}/verify', [DomainBindingController::class, 'verify'])->name('domains.verify');
            Route::delete('domains/{binding}', [DomainBindingController::class, 'destroy'])->name('domains.destroy');
        });
        Route::post('/page/publish', [StorePageController::class, 'publish'])->name('page.publish');
        Route::post('/page/unpublish', [StorePageController::class, 'unpublish'])->name('page.unpublish');
        Route::post('products/bulk-destroy', [ProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
        Route::post('products/generate-descriptions', [ProductController::class, 'generateDescriptions'])->name('products.generate-descriptions');
        Route::post('products/apply-descriptions/{task}', [ProductController::class, 'applyDescriptions'])->name('products.apply-descriptions');
        Route::post('products/undo-descriptions/{task}', [ProductController::class, 'undoDescriptions'])->name('products.undo-descriptions');
        Route::post('products/import', [ProductImportController::class, 'store'])->name('products.import');
        Route::patch('products/{product}/stock', [ProductController::class, 'adjustStock'])->name('products.stock');
        Route::resource('products.variants', ProductVariantController::class)->shallow()->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('categories/bulk-destroy', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
        Route::post('categories/generate-ai', [CategoryController::class, 'generateWithAi'])->name('categories.generate-ai');
        Route::post('categories/apply-ai/{task}', [CategoryController::class, 'applyGeneratedCategories'])->name('categories.apply-ai');
        Route::post('categories/undo-ai/{task}', [CategoryController::class, 'undoGeneratedCategories'])->name('categories.undo-ai');
        Route::resource('categories', CategoryController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    });

    // Badge application stays outside the dashboard group so sellers can apply
    // without business verification already being present.
    Route::middleware(['verified', 'onboarding.complete'])->group(function () {
        Route::get('verified', [BadgeController::class, 'create'])->name('badge.apply');
        Route::post('verified', [BadgeController::class, 'store'])->middleware('throttle:6,1')->name('badge.submit');
    });

    // The wizard needs a confirmed email but no badge: a seller who cannot get
    // past this has no page to verify, and business.verified would make the
    // platform impossible to sign up for.
    Route::prefix('onboarding')->name('onboarding.')->middleware(['verified', 'policies.current'])->group(function () {
        Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
        Route::get('/{step}', [OnboardingController::class, 'show'])->name('show')->whereIn('step', ['business', 'theme', 'brand', 'review']);
        Route::post('/{step}', [OnboardingController::class, 'store'])->name('store')->whereIn('step', ['business', 'theme', 'brand', 'review']);
        Route::post('/ai', [OnboardingController::class, 'aiCreate'])->middleware('throttle:5,1')->name('ai');
    });
});
