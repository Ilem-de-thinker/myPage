<?php

namespace App\Domains\Booking\Notifications;

use App\Domains\Booking\Models\Booking;
use App\Shared\Support\BuyerLinks;
use App\Shared\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a buyer their appointment moved. Transactional, so it goes out
 * regardless of broadcast consent.
 */
class BookingStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $statusLabel,
    ) {}

    /**
     * Sent to an anonymous mail route: a booking guest is not a user account.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;
        $when = $booking->starts_at->timezone($booking->storePage?->timezone ?? config('mypage.timezone'))
            ->format('D j M Y, g:i a');

        return (new MailMessage)
            ->subject("Your booking {$booking->reference} is {$this->statusLabel}")
            ->greeting($booking->customer_name ? "Hello {$booking->customer_name}," : 'Hello,')
            ->line("{$booking->service?->name} at {$booking->storePage?->name} is {$this->statusLabel}.")
            ->line("When: {$when}")
            ->when(
                $booking->deposit_kobo > 0,
                fn (MailMessage $message) => $message->line('Paid: '.Money::format($booking->deposit_kobo)),
            )
            ->line('Reference: '.$booking->reference)
            // The client's own link rather than a form to fill in. A booking
            // reference cannot open the lookup on its own, so an email that told
            // them to go and type it in was asking for work before it answered
            // anything.
            ->line('Keep this link. It shows your appointment and retainer status without an account, and it works for 30 days.')
            ->action('Manage your booking', BuyerLinks::booking($booking->storePage, $booking));
    }
}
