<?php

namespace App\Providers;

use App\Domains\Auth\Events\SellerRegistered;
use App\Domains\Auth\Listeners\SendWelcomeEmail;
use App\Domains\Billing\Listeners\OpenEscrowAfterPayment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\EscrowRouterService;
use App\Domains\Booking\Events\BookingStatusChanged;
use App\Domains\Booking\Listeners\TrackBookingCustomerValue;
use App\Domains\Booking\Models\Booking;
use App\Domains\Customers\Listeners\TrackCustomerLifetimeValue;
use App\Domains\Domains\Services\Contracts\DnsResolver;
use App\Domains\Domains\Services\Resolvers\NativeDnsResolver;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Events\OrderStatusChanged;
use App\Domains\Orders\Listeners\NotifyBuyerOfStatusChange;
use App\Domains\Orders\Listeners\NotifySellerOfNewOrder;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Events\PaymentConfirmed;
use App\Domains\Payments\Listeners\SettleOrderAfterPayment;
use App\Domains\Payments\Services\Contracts\EscrowRouter;
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
        // DNS is asked the same way everywhere, so there is one place to swap in
        // a resolver for a different platform or for tests.
        $this->app->singleton(DnsResolver::class, NativeDnsResolver::class);

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
            'booking' => Booking::class,
            'subscription' => Subscription::class,
        ]);

        Event::listen(SellerRegistered::class, SendWelcomeEmail::class);
        Event::listen(PaymentConfirmed::class, SettleOrderAfterPayment::class);
        Event::listen(OrderStatusChanged::class, TrackCustomerLifetimeValue::class);
        Event::listen(OrderStatusChanged::class, NotifyBuyerOfStatusChange::class);
        Event::listen(OrderPlaced::class, NotifySellerOfNewOrder::class);
        Event::listen(BookingStatusChanged::class, TrackBookingCustomerValue::class);
        Event::listen(PaymentConfirmed::class, OpenEscrowAfterPayment::class);

        // Payments asks where a completed sale's money should land; Billing
        // answers, and knows nothing about orders or bookings.
        $this->app->singleton(EscrowRouter::class, EscrowRouterService::class);
    }
}
