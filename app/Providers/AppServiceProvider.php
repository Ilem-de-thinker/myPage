<?php

namespace App\Providers;

use App\Domains\Seller\Models\StorePage;
use App\Domains\Seller\Observers\StorePageObserver;
use App\Domains\Seller\Policies\StorePagePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
        Blade::directive('jsExpr', fn (string $expression): string => "<?php echo e(json_encode($expression)); ?>");

        // N+1 failures should be loud in development and tests, where they are
        // cheap to fix, rather than silent latency in production.
        Model::preventLazyLoading(! app()->isProduction());

        StorePage::observe(StorePageObserver::class);
        Gate::policy(StorePage::class, StorePagePolicy::class);
        Queue::failing(function ($event): void {
            Log::critical('Queue job failed', ['job' => $event->job->resolveName(), 'exception' => $event->exception]);
        });
    }
}
