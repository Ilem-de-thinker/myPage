<?php

namespace App\Providers;

use App\Domains\Ai\Contracts\AiManager;
use App\Domains\Ai\Services\PrismAiManager;
use App\Domains\Auth\Events\SellerRegistered;
use App\Domains\Auth\Listeners\SendWelcomeEmail;
use App\Domains\Billing\Models\Subscription;
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
use App\Domains\Payments\Data\GatewayCredentials;
use App\Domains\Payments\Events\PaymentConfirmed;
use App\Domains\Payments\Listeners\SettleBookingAfterPayment;
use App\Domains\Payments\Listeners\SettleOrderAfterPayment;
use App\Domains\Payments\Services\Contracts\PaymentGatewayInterface;
use App\Domains\Payments\Services\GatewayFactory;
use App\Domains\Payments\Services\Gateways\FlutterwaveGateway;
use App\Domains\Payments\Services\Gateways\PaystackGateway;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class, PrismAiManager::class);
        // DNS is asked the same way everywhere, so there is one place to swap in
        // a resolver for a different platform or for tests.
        $this->app->singleton(DnsResolver::class, NativeDnsResolver::class);

        $this->app->bind(PaymentGatewayInterface::class, fn (): PaymentGatewayInterface => $this->app
            ->make(GatewayFactory::class)
            ->platform());

        /*
         * The concrete gateways are also resolvable on their own: the webhook
         * controller takes PaystackGateway/FlutterwaveGateway by type for the
         * platform's subscription endpoint. Seller-money calls go through the
         * factory and never use these instances.
         */
        $this->app->bind(PaystackGateway::class, fn (): PaystackGateway => new PaystackGateway(
            GatewayCredentials::platform('paystack'),
        ));

        $this->app->bind(FlutterwaveGateway::class, fn (): FlutterwaveGateway => new FlutterwaveGateway(
            GatewayCredentials::platform('flutterwave'),
        ));
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
        Event::listen(PaymentConfirmed::class, SettleBookingAfterPayment::class);
        Event::listen(OrderStatusChanged::class, TrackCustomerLifetimeValue::class);
        Event::listen(OrderStatusChanged::class, NotifyBuyerOfStatusChange::class);
        Event::listen(OrderPlaced::class, NotifySellerOfNewOrder::class);
        Event::listen(BookingStatusChanged::class, TrackBookingCustomerValue::class);

        // Payments asks where a completed sale's money should land; Billing
        // answers, and knows nothing about orders or bookings.
    }
}
