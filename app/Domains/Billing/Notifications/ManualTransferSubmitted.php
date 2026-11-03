<?php

namespace App\Domains\Billing\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ManualTransferSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{seller_name: string, seller_email: string, plan_name: string, reference: string}  $claim
     */
    public function __construct(public readonly array $claim) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new TransactionalMailMessage)
            ->subject('A manual bank transfer is waiting for approval')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->claim['seller_name']} ({$this->claim['seller_email']}) says they paid by bank transfer for {$this->claim['plan_name']}.")
            ->line("Claimed reference: {$this->claim['reference']}")
            ->line('Check the bank before approving. Nothing activates until you do.')
            ->action('Review manual transfers', route('admin.manual-subscriptions'))
            ->platformFooter();
    }

    /** @return array{title: string, body: string, url: string, action: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Manual transfer waiting for approval',
            'body' => "{$this->claim['seller_name']} claims {$this->claim['reference']} for {$this->claim['plan_name']}.",
            'url' => route('admin.manual-subscriptions'),
            'action' => 'Review',
        ];
    }
}
