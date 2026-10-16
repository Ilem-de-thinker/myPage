<?php

namespace App\Domains\Orders\Notifications;

use App\Domains\Orders\Models\AbandonedCart;
use App\Shared\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reminder the buyer only ever receives because they gave us an email at
 * checkout, which is transactional enough to be worth one nudge.
 */
class AbandonedCartReminder extends Notification
{
    use Queueable;

    public function __construct(public readonly AbandonedCart $cart) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $items = $this->cart->cart;
        $lines = collect($items)->take(5)
            ->map(fn (array $item) => "{$item['quantity']} x {$item['name']} — ".Money::format($item['line_kobo']))
            ->implode("\n");

        return (new MailMessage)
            ->subject("You left something in your cart at {$this->cart->storePage?->name}")
            ->greeting($this->cart->email ? 'Hello,' : 'Hello,')
            ->line('You started an order but did not finish it. Your cart is:')
            ->line($lines)
            ->line('Total: '.Money::format($this->cart->total_kobo))
            ->action('Finish your order', $this->cart->recoveryUrl())
            ->line('If you would rather not hear from us, ignore this and we will not chase you again.');
    }
}
