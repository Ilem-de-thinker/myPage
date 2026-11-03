<?php

namespace App\Domains\Auth\Notifications;

use App\Domains\Auth\Models\DataSubjectRequest;
use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DataSubjectRequestSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly DataSubjectRequest $request) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $due = $this->request->due_at?->translatedFormat('j F Y') ?? 'within one month';

        return (new TransactionalMailMessage)
            ->subject('A data protection request needs an answer')
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->request->name} ({$this->request->email}) asked for {$this->request->request_type}.")
            ->line("This is a data subject request under the NDPA. Answer by {$due}.")
            ->action('Open the request queue', route('admin.data-requests'))
            ->platformFooter();
    }

    /** @return array{title: string, body: string, url: string, action: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New data protection request',
            'body' => "{$this->request->name} asked for {$this->request->request_type}.",
            'url' => route('admin.data-requests'),
            'action' => 'Review',
        ];
    }
}
