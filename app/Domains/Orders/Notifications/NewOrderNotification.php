<?php

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Enums\OrderSource;
use App\Domains\Orders\Models\Order;
use App\Shared\Mail\TransactionalMailMessage;
use App\Shared\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the seller an order landed. Queued, because a seller must never wait on
 * a mail server to see their own order.
 */
class NewOrderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $buyer = $order->customer_name ?: 'A buyer';

        $message = (new TransactionalMailMessage)
            ->subject("New {$order->source->shortLabel()} order {$order->reference} — ".Money::format($order->total_kobo))
            ->greeting("New order from {$buyer}")
            ->line(
                $order->source === OrderSource::Whatsapp
                    ? "They sent this over WhatsApp quoting {$order->reference}. Confirm you received it and agreed the total."
                    : 'They paid for this through your page and payment is being confirmed.'
            )
            ->line('Items: '.($order->items->pluck('name')->join(', ') ?: 'None'))
            ->action('Open the order', route('dashboard.orders.show', $order));

        if ($order->customer_phone) {
            $message->line("Phone: {$order->customer_phone}");
        }

        return $message->platformFooter();
    }
}
