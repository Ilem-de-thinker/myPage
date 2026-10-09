<?php

use App\Shared\Middleware\EnsureOnboardingComplete;
use App\Shared\Middleware\EnsureSellerIsActive;
use App\Shared\Middleware\ResolveStorePage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/web.php'));

            Route::prefix('webhooks')->group(base_path('routes/webhooks.php'));

            if (config('mypage.path_routes')) {
                Route::middleware(['web', 'resolve.store'])
                    ->prefix('{slug}')
                    ->group(base_path('routes/storefront.php'));

                Route::middleware(['web', 'resolve.store'])
                    ->prefix('{slug}')
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
        $middleware->alias([
            'seller.active' => EnsureSellerIsActive::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'resolve.store' => ResolveStorePage::class,
        ]);

        // Gateway deliveries are signed, not session-authenticated.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
