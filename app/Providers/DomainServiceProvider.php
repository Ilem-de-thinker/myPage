<?php

namespace App\Providers;

use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Events\PaymentConfirmed;
use App\Domains\Payments\Listeners\SettleOrderAfterPayment;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\Gateways\FlutterwaveGateway;
use App\Domains\Payments\Services\Gateways\PaystackGateway;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function (): PaymentGatewayInterface {
            return match (config('mypage.gateway')) {
                'flutterwave' => new FlutterwaveGateway,
                default => new PaystackGateway,
            };
        });
    }

    public function boot(): void
    {
        // Stable morph keys so payable_type and ref_type survive refactors.
        Relation::morphMap([
            'order' => Order::class,
        ]);

        Event::listen(PaymentConfirmed::class, SettleOrderAfterPayment::class);
    }
}
