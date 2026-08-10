<?php

namespace App\Notifications;

use App\Models\Licitacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NuevaOportunidadDisponibleNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Licitacion $licitacion,
        protected ?User $creator = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tipo = strtoupper((string) $this->licitacion->tipo);
        $creatorName = $this->creator
            ? (trim($this->creator->nombres.' '.$this->creator->apellidos) ?: $this->creator->email)
            : ($this->licitacion->creado_por ?: 'Sistema');

        return [
            'title' => 'Nueva oportunidad disponible',
            'description' => "{$creatorName} registro una nueva oportunidad {$tipo} para {$this->licitacion->empresa}: {$this->licitacion->requerimiento}.",
            'message' => "{$creatorName} registro una nueva oportunidad {$tipo} para {$this->licitacion->empresa}: {$this->licitacion->requerimiento}.",
            'action_url' => '/seguimiento-licitaciones',
            'action' => 'nueva_oportunidad',
            'licitacion_id' => $this->licitacion->id,
            'licitacion_tipo' => $this->licitacion->tipo,
            'licitacion_empresa' => $this->licitacion->empresa,
            'licitacion_requerimiento' => $this->licitacion->requerimiento,
            'created_by_id' => $this->creator?->id,
            'created_by_name' => $creatorName,
        ];
    }
}
