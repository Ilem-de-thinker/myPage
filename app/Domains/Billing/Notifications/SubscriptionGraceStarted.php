<?php

namespace App\Domains\Billing\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionGraceStarted extends Notification implements ShouldQueue
{
    public function __construct(public readonly Carbon $graceEndsAt) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new TransactionalMailMessage)
            ->subject('Your storefront is in a grace period')
            ->greeting("Hello {$notifiable->name},")
            ->line('We could not confirm your last subscription payment, so your plan has entered a grace period.')
            ->line("You keep full dashboard access and your storefront keeps taking orders until {$this->graceEndsAt->format('j F Y')}.")
            ->line('After that date your dashboard becomes read-only and your storefront stops accepting new orders until you renew.')
            ->action('Update your payment', route('billing.index'))
            ->line('Your data, products and orders are kept either way.')
            ->platformFooter();
    }

    /**
     * @return array{title: string, body: string, url: string, action: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your plan is in a grace period',
            'body' => "Full access continues until {$this->graceEndsAt->format('j M Y')}. Renew before then to avoid read-only mode.",
            'url' => route('billing.index'),
            'action' => 'Update payment',
        ];
    }
}
