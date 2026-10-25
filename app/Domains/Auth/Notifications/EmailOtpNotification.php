<?php

namespace App\Domains\Auth\Notifications;

use App\Shared\Mail\TransactionalMailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new TransactionalMailMessage)
            ->subject('Your mypage.ng verification code')
            ->greeting("Hello {$notifiable->name},")
            ->line("Your verification code is: {$this->code}")
            ->line('It expires in 15 minutes. If you did not request it, ignore this email.')
            ->platformFooter();
    }
}
