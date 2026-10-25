<?php

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Models\Order;
use App\Shared\Mail\TransactionalMailMessage;
use App\Shared\Support\BuyerLinks;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a buyer their order moved. This is transactional, not marketing, so it
 * goes out regardless of whether the buyer ever opted in to broadcasts.
 */
class OrderStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $statusLabel,
    ) {}

    /**
     * Sent to an anonymous mail route built by the listener: a buyer is not a
     * user account, so there is nothing to notify by id.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;

        $message = (new TransactionalMailMessage)
            ->subject("Your order {$order->reference} is now {$this->statusLabel}")
            ->greeting($order->customer_name ? "Hello {$order->customer_name}," : 'Hello,')
            ->line("Your order {$order->reference} from {$order->storePage?->name} is now {$this->statusLabel}.");

        if ($order->status->isTerminal() && $order->customer_note) {
            $message->line('Your note: '.$order->customer_note);
        }

        /*
            The buyer's own link, not the lookup form.

            A reference alone cannot open the lookup — it also needs the email or
            phone the order was placed with — so a "track your order" button that
            pointed at the form without it landed the buyer on a blank search
            every single time. The signed link needs nothing from them: it is the
            authorisation, which is the whole point of issuing one.
        */
        $message->line('Keep this link. It is how you check on the order without an account, and it works for 30 days.');

        return $message
            ->action('Track your order', BuyerLinks::order($order->storePage, $order))
            ->platformFooter();
    }
}
