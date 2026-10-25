<?php

namespace App\Domains\Payments\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerGatewayBrokenNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $gateway) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new TransactionalMailMessage)
            ->subject("Your {$this->gateway} payment key needs attention")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->gateway} rejected the key connected to your storefront.")
            ->line('Until the key is replaced, new checkout payments cannot be verified here.')
            ->action('Replace payment key', route('payments.gateways.index'))
            ->line('Buyer payments go directly to your own gateway account. mypage.ng never receives or holds buyer money.')
            ->platformFooter();
    }
}
