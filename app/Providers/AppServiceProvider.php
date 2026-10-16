<?php

namespace App\Providers;

use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Observers\StorePageObserver;
use App\Domains\Seller\Policies\StorePagePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // N+1 failures should be loud in development and tests, where they are
        // cheap to fix, rather than silent latency in production.
        Model::preventLazyLoading(! app()->isProduction());

        StorePage::observe(StorePageObserver::class);
        Gate::policy(StorePage::class, StorePagePolicy::class);
    }
}
