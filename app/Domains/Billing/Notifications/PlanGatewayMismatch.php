<?php

namespace App\Domains\Billing\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanGatewayMismatch extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{plan_name: string, local_kobo: int, gateway_kobo: int, gateway_plan_code: string}  $mismatch
     */
    public function __construct(public readonly array $mismatch) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new TransactionalMailMessage)
            ->subject('A plan is charging the wrong amount — checkout was refused')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->mismatch['plan_name']} quotes ₦".number_format($this->mismatch['local_kobo'] / 100, 2)." locally, but the gateway plan {$this->mismatch['gateway_plan_code']} charges ₦".number_format($this->mismatch['gateway_kobo'] / 100, 2).'.')
            ->line('A seller was refused at checkout rather than charged the wrong amount. Fix the gateway plan or the local price.')
            ->action('Open plans', route('admin.plans'))
            ->platformFooter();
    }

    /** @return array{title: string, body: string, url: string, action: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Plan price mismatch',
            'body' => "{$this->mismatch['plan_name']}: local ≠ gateway ({$this->mismatch['gateway_plan_code']}). Checkout was refused.",
            'url' => route('admin.plans'),
            'action' => 'Open plans',
        ];
    }
}
