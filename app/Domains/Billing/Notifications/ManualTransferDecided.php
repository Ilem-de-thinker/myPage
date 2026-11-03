<?php

namespace App\Domains\Billing\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ManualTransferDecided extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{plan_name: string, approved: bool, reference: string}  $claim
     */
    public function __construct(public readonly array $claim) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new TransactionalMailMessage)
            ->subject($this->claim['approved'] ? 'Your transfer was confirmed — your plan is live' : 'We could not confirm your transfer')
            ->greeting("Hello {$notifiable->name},");

        if ($this->claim['approved']) {
            $mail->line("We found your transfer ({$this->claim['reference']}) for {$this->claim['plan_name']}. Your plan is active now.")
                ->action('Open billing', route('billing.index'));
        } else {
            $mail->line("We could not confirm transfer {$this->claim['reference']} for {$this->claim['plan_name']}, so your plan was not activated.")
                ->line('If you did transfer the money, reply to this email with the bank receipt and we will sort it out.')
                ->action('Open billing', route('billing.index'));
        }

        return $mail->platformFooter();
    }

    /** @return array{title: string, body: string, url: string, action: string} */
    public function toArray(object $notifiable): array
    {
        return $this->claim['approved']
            ? ['title' => 'Transfer confirmed', 'body' => "Your {$this->claim['plan_name']} plan is live.", 'url' => route('billing.index'), 'action' => 'Open billing']
            : ['title' => 'Transfer not confirmed', 'body' => 'Your plan was not activated. Reply with your bank receipt to fix this.', 'url' => route('billing.index'), 'action' => 'Open billing'];
    }
}
