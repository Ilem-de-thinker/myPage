<?php

use App\Domains\Admin\Middleware\EnsureUserIsAdmin;
use App\Domains\Shared\Middleware\EnsureSubscriptionValid;
use App\Shared\Middleware\EnsureApexHost;
use App\Shared\Middleware\EnsureFeatureIsEnabled;
use App\Shared\Middleware\EnsureOnboardingComplete;
use App\Shared\Middleware\EnsureSellerIsActive;
use App\Shared\Middleware\EnsureSellerIsVerified;
use App\Shared\Middleware\ForwardCustomDomainToStorefront;
use App\Shared\Middleware\ResolveStorePage;
use App\Shared\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            /*
             | The storefront owns {store}.{domain}. These groups are registered
             | before the apex ones on purpose: on a tenant host "/" and paths
             | such as /products, /cart and /login collide with platform routes,
             | and the shop has to win on its own address. On the apex the domain
             | constraint does not match at all, so nothing here can swallow a
             | platform path.
             */
            Route::middleware(['web', 'resolve.store'])
                ->domain('{slug}.'.config('mypage.domain'))
                ->group(base_path('routes/storefront.php'));

            Route::middleware(['web', 'resolve.store'])
                ->domain('{slug}.'.config('mypage.domain'))
                ->group(base_path('routes/checkout.php'));

            /*
             | The apex routes. ForwardCustomDomainToStorefront answers a seller's
             | own domain first, and EnsureApexHost 404s a tenant subdomain for
             | any path the storefront did not claim.
             */
            Route::middleware(['web', 'auth', 'seller.active', 'admin', 'apex.host'])
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));

            Route::middleware(['web', 'forward.custom.domain', 'apex.host'])
                ->group(base_path('routes/web.php'));

            /*
             | Storefront pages such as /about and /cart exist only in the shop's
             | route file, so on a custom domain there is no apex route to carry
             | the forwarding middleware. Without a fallback, those paths 404
             | before a seller's own domain is ever considered. On the apex this
             | is still a 404.
             */
            Route::middleware(['web', 'forward.custom.domain', 'apex.host'])
                ->group(function (): void {
                    Route::fallback(fn () => abort(404));
                });

            Route::prefix('webhooks')->group(base_path('routes/webhooks.php'));
        },
    )
    ->withCommands([__DIR__.'/../app/Domains/Ai/Console'])
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Forwarding has to run before routing, not just on routes that already
         * matched. A custom-domain POST (checkout, booking) matches no apex route
         * by method, so it never reached the per-group middleware and 405'd.
         */
        $middleware->append(ForwardCustomDomainToStorefront::class);

        // Baseline browser protections belong on every HTML route, including
        // the landing page, dashboard, and storefronts.
        $middleware->appendToGroup('web', SecurityHeaders::class);

        $middleware->alias([
            'seller.active' => EnsureSellerIsActive::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            /*
             * Deliberately not called 'verified'. That name is Laravel's own alias
             * for EnsureEmailIsVerified, and it is applied here on purpose: a
             * seller confirms their address on the way in. Business verification
             * is a different question with a different answer, and naming it
             * differently is the only way a reader of routes/web.php can tell them
             * apart.
             */
            'business.verified' => EnsureSellerIsVerified::class,
            'resolve.store' => ResolveStorePage::class,
            'subscription.valid' => EnsureSubscriptionValid::class,
            'admin' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeatureIsEnabled::class,
            'forward.custom.domain' => ForwardCustomDomainToStorefront::class,
            'apex.host' => EnsureApexHost::class,
        ]);

        // Gateway deliveries are signed, not session-authenticated.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        /*
         * The admin check has to run before route model binding. It lifts the
         * tenant scope, and an admin reviewing somebody else's store would
         * otherwise get a 404 on a row that plainly exists.
         */
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            EnsureUserIsAdmin::class,
        );

        /*
         * Custom-domain forwarding has to run before auth.
         *
         * Laravel's priority list forces AuthenticatesRequests ahead of any
         * middleware not on it, so without this a request to shop.com/products
         * was matched to the seller's dashboard route and bounced to the login
         * screen before forwarding was ever considered.
         */
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            ForwardCustomDomainToStorefront::class,
        );

        /*
         * And the apex guard has to run before auth for the same reason: on a
         * tenant host a dashboard path must 404, not bounce to a login screen
         * that belongs to the apex.
         */
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureApexHost::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
