<?php

namespace App\Domains\Admin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KycStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public int $applicationId,
        public string $status,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = str_replace('_', ' ', $this->status);
        $body = match ($this->status) {
            'approved' => 'Your business verification was approved. The verified tick is now live on your storefront.',
            'rejected' => 'Your business verification was rejected. '.(string) $this->reason,
            'needs_more_info' => 'We need more information to finish your verification. '.(string) $this->reason,
            'revoked' => 'Your business verification was revoked. '.(string) $this->reason,
            'expired' => 'Your verification document has expired. Re-upload a current document to stay verified.',
            default => 'Your business verification is now '.$label.'.',
        };

        return [
            'title' => 'Verification '.$label,
            'body' => trim($body),
            'url' => '/dashboard/verified',
            'action' => 'Open verification',
        ];
    }
}
