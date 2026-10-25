<?php

namespace App\Domains\Billing\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCardExpiring extends Notification implements ShouldQueue
{
    public function __construct(public readonly string $nextPaymentDate) {}

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
            ->subject('Your subscription card needs attention')
            ->greeting("Hello {$notifiable->name},")
            ->line('The card Paystack holds for your mypage.ng plan is close to expiry.')
            ->line("The next charge is due around {$this->nextPaymentDate}. If the card has expired, the charge may fail.")
            ->action('Update your card', route('billing.manage-card'))
            ->line('Your storefront stays online while you update the card. Nothing is charged by updating it.')
            ->platformFooter();
    }

    /**
     * @return array{title: string, body: string, url: string, action: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Update the card on your plan',
            'body' => "The card on file expires before the next charge around {$this->nextPaymentDate}.",
            'url' => route('billing.manage-card'),
            'action' => 'Update card',
        ];
    }
}
