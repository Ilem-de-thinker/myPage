<?php

namespace App\Shared\Mail;

use Illuminate\Notifications\Messages\MailMessage;

class TransactionalMailMessage extends MailMessage
{
    public function platformFooter(): static
    {
        return $this
            ->salutation('mypage.ng')
            ->line('This is a service email from mypage.ng. Sellers pay mypage.ng for subscriptions only; buyer payments go directly to the seller’s own gateway account.')
            ->line('Privacy: '.route('legal.policy', ['policy' => 'privacy']).' · Contact: '.route('legal.policy', ['policy' => 'contact']));
    }
}
