<?php

namespace App\Domains\Ai\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AiTaskFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public int $taskId, public string $type, public int $failed, public int $total) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Mercho task finished with failures',
            'body' => ucfirst($this->type).' task: '.$this->failed.' of '.$this->total.' item(s) failed. Retry the failed items or adjust your input.',
            'url' => '/ai/tasks/'.$this->taskId,
            'action' => 'Review task',
        ];
    }
}
