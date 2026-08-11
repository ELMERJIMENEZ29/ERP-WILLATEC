<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use App\Models\Licitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LicitacionCotizacionListaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Licitacion $licitacion,
        protected Cotizacion $cotizacion
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tipo = strtoupper((string) $this->licitacion->tipo);
        $empresa = $this->licitacion->empresa;
        $requerimiento = $this->licitacion->requerimiento;
        $numero = $this->cotizacion->numero ?: 'Cotizacion #'.$this->cotizacion->id;

        return [
            'title' => "Oportunidad {$tipo} lista para subir",
            'description' => "La cotizacion {$numero} fue aprobada para {$empresa}: {$requerimiento}.",
            'message' => "La cotizacion {$numero} esta lista para subir en la oportunidad {$tipo} de {$empresa}: {$requerimiento}.",
            'action_url' => "/seguimiento-licitaciones?oportunidad_id={$this->licitacion->id}",
            'action' => 'cotizacion_lista_oportunidad',
            'licitacion_id' => $this->licitacion->id,
            'licitacion_tipo' => $this->licitacion->tipo,
            'licitacion_empresa' => $empresa,
            'licitacion_requerimiento' => $requerimiento,
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_numero' => $this->cotizacion->numero,
        ];
    }
}
