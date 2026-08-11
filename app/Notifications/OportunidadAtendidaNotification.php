<?php

namespace App\Notifications;

use App\Models\Licitacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OportunidadAtendidaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Licitacion $licitacion,
        protected ?User $responsable = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tipo = strtoupper((string) $this->licitacion->tipo);
        $responsableName = $this->responsable
            ? (trim($this->responsable->nombres.' '.$this->responsable->apellidos) ?: $this->responsable->email)
            : ($this->licitacion->modificado_por ?: 'Sistema');

        return [
            'title' => 'Oportunidad atendida',
            'description' => "{$responsableName} marco como atendida la oportunidad {$tipo} de {$this->licitacion->empresa}: {$this->licitacion->requerimiento}.",
            'message' => "{$responsableName} marco como atendida la oportunidad {$tipo} de {$this->licitacion->empresa}: {$this->licitacion->requerimiento}.",
            'action_url' => "/seguimiento-licitaciones?oportunidad_id={$this->licitacion->id}",
            'action' => 'oportunidad_atendida',
            'licitacion_id' => $this->licitacion->id,
            'licitacion_tipo' => $this->licitacion->tipo,
            'licitacion_empresa' => $this->licitacion->empresa,
            'licitacion_requerimiento' => $this->licitacion->requerimiento,
            'updated_by_id' => $this->responsable?->id,
            'updated_by_name' => $responsableName,
        ];
    }
}
