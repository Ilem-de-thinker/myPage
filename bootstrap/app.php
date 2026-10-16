<?php

use App\Domains\Admin\Middleware\EnsureUserIsAdmin;
use App\Domains\Shared\Middleware\EnsureSubscriptionValid;
use App\Shared\Middleware\EnsureFeatureIsEnabled;
use App\Shared\Middleware\EnsureOnboardingComplete;
use App\Shared\Middleware\EnsureSellerIsActive;
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
            Route::middleware(['web', 'auth', 'seller.active', 'admin'])
                ->prefix('admin')
                ->group(base_path('routes/admin.php'));

            /*
             | The apex routes. ForwardCustomDomainToStorefront sits in front of
             | them so that on one of a seller's own domains a request is
             | answered by the shop rather than by whichever dashboard route
             | happens to share the path.
             */
            Route::middleware(['web', 'forward.custom.domain'])
                ->group(base_path('routes/web.php'));

            Route::prefix('webhooks')->group(base_path('routes/webhooks.php'));

            /*
             | The storefront runs on two kinds of host. On mypage.ng the seller's
             | slug is in the path, because that is what the URL has to look like
             | when every seller shares one domain. On a seller's own domain
             | there is no slug at all: the hostname is the whole address, so the
             | segment is optional and ResolveStorePage falls back to looking the
             | shop up by host.
             |
             | web.php is registered above these groups, so an apex route such as
             | /login or /directory always wins over an optional storefront slug.
             */
            if (config('mypage.path_routes')) {
                Route::middleware(['web', 'resolve.store'])
                    ->prefix('{slug?}')
                    ->group(base_path('routes/storefront.php'));

                Route::middleware(['web', 'resolve.store'])
                    ->prefix('{slug?}')
                    ->group(base_path('routes/checkout.php'));
            } else {
                Route::middleware(['web', 'resolve.store'])
                    ->domain('{slug}.'.config('mypage.domain'))
                    ->group(base_path('routes/storefront.php'));

                Route::middleware(['web', 'resolve.store'])
                    ->domain('{slug}.'.config('mypage.domain'))
                    ->group(base_path('routes/checkout.php'));
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Baseline browser protections belong on every HTML route, including
        // the landing page, dashboard, and storefronts.
        $middleware->appendToGroup('web', SecurityHeaders::class);

        $middleware->alias([
            'seller.active' => EnsureSellerIsActive::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'resolve.store' => ResolveStorePage::class,
            'subscription.valid' => EnsureSubscriptionValid::class,
            'admin' => EnsureUserIsAdmin::class,
            'feature' => EnsureFeatureIsEnabled::class,
            'forward.custom.domain' => ForwardCustomDomainToStorefront::class,
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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
