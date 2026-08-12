<?php

namespace App\Notifications;

use App\Models\Licitacion;
use App\Models\LicitacionComentario;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OportunidadComentarioNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Licitacion $licitacion,
        protected LicitacionComentario $comentario,
        protected ?User $autor = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tipo = strtoupper((string) $this->licitacion->tipo);
        $autorName = $this->autor
            ? (trim($this->autor->nombres.' '.$this->autor->apellidos) ?: $this->autor->email)
            : ($this->comentario->usuario ?: 'Usuario');

        return [
            'title' => 'Nuevo comentario en oportunidad',
            'description' => "{$autorName} comento en la oportunidad {$tipo} de {$this->licitacion->empresa}: {$this->licitacion->requerimiento}.",
            'message' => "{$autorName} agrego un comentario interno en la oportunidad {$tipo} de {$this->licitacion->empresa}: {$this->licitacion->requerimiento}.",
            'action_url' => "/seguimiento-licitaciones?oportunidad_id={$this->licitacion->id}",
            'action' => 'comentario_oportunidad',
            'licitacion_id' => $this->licitacion->id,
            'licitacion_tipo' => $this->licitacion->tipo,
            'licitacion_empresa' => $this->licitacion->empresa,
            'licitacion_requerimiento' => $this->licitacion->requerimiento,
            'comentario_id' => $this->comentario->id,
            'created_by_id' => $this->autor?->id,
            'created_by_name' => $autorName,
        ];
    }
}
