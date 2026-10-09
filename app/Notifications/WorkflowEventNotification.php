<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkflowEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly string $actionUrl,
        private readonly string $action,
        private readonly array $metadata = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'message' => $this->description,
            'action_url' => $this->actionUrl,
            'action' => $this->action,
            ...$this->metadata,
        ];
    }
}
