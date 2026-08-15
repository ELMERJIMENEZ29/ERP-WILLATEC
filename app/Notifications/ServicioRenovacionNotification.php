<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServicioRenovacionNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $servicioTipo,
        protected int $servicioId,
        protected string $title,
        protected string $description,
        protected string $actionUrl,
        protected array $extra = []
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
            'action_url' => $this->actionUrl,
            'servicio_tipo' => $this->servicioTipo,
            'servicio_id' => $this->servicioId,
            'action' => 'renovacion_servicio',
            'message' => $this->description,
            ...$this->extra,
        ];
    }
}
