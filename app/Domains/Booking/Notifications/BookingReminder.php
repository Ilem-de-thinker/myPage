<?php

namespace App\Domains\Booking\Notifications;

use App\Domains\Booking\Models\Booking;
use App\Shared\Mail\TransactionalMailMessage;
use App\Shared\Support\Money;
use App\Shared\Support\PhoneNumber;
use App\Shared\Support\WhatsApp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A nudge before an appointment. One notification class, parameterised by how
 * far ahead it is, so the two reminders cannot drift apart.
 */
class BookingReminder extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly int $hoursBefore,
    ) {}

    public function via(object $notifiable): array
    {
        // WhatsApp is the channel Nigerian customers actually read, but it is
        // only used when we already hold a number and the seller has connected a
        // line. Until the Cloud API exists this falls back to mail alone.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking;

        $timezone = $booking->storePage?->settings['timezone'] ?? config('mypage.timezone', 'Africa/Lagos');
        $when = $booking->starts_at->timezone($timezone)->format('l j F, g:i A');

        $lead = match (true) {
            $this->hoursBefore >= 24 => 'Tomorrow',
            $this->hoursBefore >= 2 => 'In a couple of hours',
            default => 'Soon',
        };

        return (new TransactionalMailMessage)
            ->subject("{$lead}: {$booking->service?->name} at {$booking->storePage?->name}")
            ->greeting($booking->customer_name ? "Hello {$booking->customer_name}," : 'Hello,')
            ->line("{$lead} you have {$booking->service?->name} with {$booking->storePage?->name}.")
            ->line("When: {$when}")
            ->when(
                $booking->deposit_kobo > 0,
                fn (MailMessage $message) => $message->line('Paid to book: '.Money::format($booking->deposit_kobo)),
            )
            ->when(
                $booking->staffMember,
                fn (MailMessage $message) => $message->line('With: '.$booking->staffMember->name),
            )
            ->when(
                $booking->notes,
                fn (MailMessage $message) => $message->line('Your note: '.$booking->notes),
            )
            ->line('Reference: '.$booking->reference)
            ->when(
                PhoneNumber::normalize($booking->customer_phone),
                fn (MailMessage $message) => $message->line(
                    'Need to change it? Reply to this email or call '.$booking->storePage?->name.'.'
                ),
            )
            ->platformFooter();
    }

    /**
     * A wa.me link the seller can forward by hand until the Cloud API ships.
     */
    public function whatsappLink(): ?string
    {
        $booking = $this->booking;

        // Neither ?-> nor ?: can appear inside string interpolation, so every
        // optional value is pulled out before it is used in a message.
        $service = $booking->service?->name ?? 'your appointment';
        $shop = $booking->storePage?->name ?? 'us';
        $who = $booking->customer_name ?: 'there';

        return WhatsApp::buyerLink(
            $booking->customer_phone,
            "Hello {$who}, this is a reminder of your "
            ."{$service} booking ({$booking->reference}) at {$shop}."
        );
    }
}
