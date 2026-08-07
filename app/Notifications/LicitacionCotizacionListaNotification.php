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
        $requerimiento = $this->licitacion->requerimiento;
        $numero = $this->cotizacion->numero ?: 'Cotizacion #'.$this->cotizacion->id;

        return [
            'title' => 'Cotizacion lista para oportunidad',
            'description' => "La cotizacion esta lista en la oportunidad: {$tipo} {$requerimiento}.",
            'message' => "La cotizacion {$numero} esta lista en la oportunidad: {$tipo} {$requerimiento}.",
            'action_url' => '/seguimiento-licitaciones',
            'action' => 'cotizacion_lista_oportunidad',
            'licitacion_id' => $this->licitacion->id,
            'licitacion_tipo' => $this->licitacion->tipo,
            'licitacion_requerimiento' => $requerimiento,
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_numero' => $this->cotizacion->numero,
        ];
    }
}
