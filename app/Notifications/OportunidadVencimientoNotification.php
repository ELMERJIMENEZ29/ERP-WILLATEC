<?php

namespace App\Notifications;

use App\Models\Licitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OportunidadVencimientoNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Licitacion $licitacion,
        protected string $reminderKey,
        protected string $reminderLabel,
        protected bool $expired = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tipo = strtoupper((string) $this->licitacion->tipo);
        $action = $this->expired ? 'oportunidad_vencida' : 'oportunidad_por_vencer';
        $title = $this->expired ? 'Oportunidad vencida' : 'Oportunidad por vencer';
        $description = $this->expired
            ? "La oportunidad {$tipo} de {$this->licitacion->empresa} ya vencio: {$this->licitacion->requerimiento}."
            : "La oportunidad {$tipo} de {$this->licitacion->empresa} vence en {$this->reminderLabel}: {$this->licitacion->requerimiento}.";

        return [
            'title' => $title,
            'description' => $description,
            'message' => $description,
            'action_url' => "/seguimiento-licitaciones?oportunidad_id={$this->licitacion->id}",
            'action' => $action,
            'reminder_key' => $this->reminderKey,
            'reminder_label' => $this->reminderLabel,
            'licitacion_id' => $this->licitacion->id,
            'licitacion_tipo' => $this->licitacion->tipo,
            'licitacion_empresa' => $this->licitacion->empresa,
            'licitacion_requerimiento' => $this->licitacion->requerimiento,
            'licitacion_vigencia' => optional($this->licitacion->vigencia)->toDateTimeString(),
        ];
    }
}
