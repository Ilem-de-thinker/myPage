<?php

namespace App\Domains\Auth\Notifications;

use App\Domains\Seller\Models\Seller;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What a seller receives the moment they register.
 *
 * The verification code tells them how to get in; this tells them what getting
 * in is for, and it is the only message that will be read while they are still
 * deciding whether to finish. It says what to do in three steps rather than
 * listing the platform's features.
 */
class WelcomeNotification extends Notification
{
    public function __construct(public readonly string $firstStepUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to mypage.ng')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your page is not live yet. Three steps and a Lagos tailor, a barber or a provisions seller has a website that takes orders and bookings.')
            ->line('Confirm the code we just sent you.')
            ->line('Add your business name, WhatsApp number and the template that matches what you sell.')
            ->line('Add two products or services and publish.')
            ->action('Start setting up', $this->firstStepUrl)
            ->line('Nothing is charged until you choose a plan, and browsing your own page never needs one.');
    }

    /**
     * The welcome only makes sense once the seller can actually open the app.
     */
    public function shouldSend(object $notifiable): bool
    {
        return $notifiable instanceof Seller && ! $notifiable->hasVerifiedEmail();
    }
}
