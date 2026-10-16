<?php

namespace App\Domains\Payments\Http\Controllers;

use App\Domains\Orders\Actions\CreateOrderFromCheckout;
use App\Domains\Orders\Actions\RecordAbandonedCart;
use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Exceptions\InvalidCart;
use App\Domains\Orders\Models\Order;
use App\Domains\Payments\Actions\ConfirmTransaction;
use App\Domains\Payments\Actions\InitializePayment;
use App\Domains\Seller\Models\StorePage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CheckoutController
{
    /**
     * Buyer submits the cart and their details; we price it ourselves and hand
     * them to the gateway.
     */
    public function store(Request $request, CreateOrderFromCheckout $createOrder, InitializePayment $initializePayment): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'note' => ['nullable', 'string', 'max:1000'],
            'cart' => ['required', 'array', 'min:1', 'max:50'],
            'cart.*.product_id' => ['required', 'integer'],
            'cart.*.variant_id' => ['nullable', 'integer'],
            'cart.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        try {
            $order = $createOrder->handle(
                app(StorePage::class),
                $data['cart'],
                $data,
                OrderSource::Platform,
            );
        } catch (InvalidCart $exception) {
            return back()->withInput()->withErrors(['cart' => $exception->getMessage()]);
        }

        // The buyer converted: stop the seller chasing them for a cart they paid for.
        app(RecordAbandonedCart::class)->markRecoveredBy($order);

        /*
         * The order exists and the buyer has converted. A gateway that cannot be
         * reached is our failure to explain, not a 500 to hand them: the order
         * stays unpaid and visible to the seller, and the buyer is sent back with
         * their cart still filled in.
         */
        try {
            $checkoutUrl = $initializePayment->handle($order, $data['email']);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'email' => 'We could not reach the payment provider. Your order is saved — try paying again in a moment.',
                ]);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * Where the gateway returns the buyer. The query string is never trusted, so
     * we re-verify with the gateway and only then report the result.
     */
    public function callback(Request $request, string $reference, ConfirmTransaction $confirmTransaction)
    {
        $transaction = $confirmTransaction->handle($reference);

        $order = $transaction?->payable instanceof Order
            ? $transaction->payable
            : Order::where('reference', $request->query('reference'))->first();

        if (! $order) {
            return redirect()->route('home')->with('status', 'We could not find that order.');
        }

        return redirect()
            ->route('checkout.thanks', [
                'slug' => app(StorePage::class)->slug,
                'order' => $order,
            ])
            ->with('status', $transaction ? 'Payment received. Thank you!' : 'We are still confirming your payment.');
    }

    public function thanks(Request $request, Order $order)
    {
        $page = app(StorePage::class);

        abort_unless($order->store_page_id === $page->getKey(), 404);

        return view('checkout.thanks', [
            'page' => $page,
            'order' => $order->load('items'),
        ]);
    }
}
