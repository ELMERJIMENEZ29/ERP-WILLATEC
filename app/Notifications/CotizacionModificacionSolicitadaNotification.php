<?php

namespace App\Notifications;

use App\Models\CotizacionModificacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionModificacionSolicitadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CotizacionModificacion $modificacion,
        protected User $requester
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $requestedAt = now('America/Lima');
        $requesterName = trim($this->requester->nombres.' '.$this->requester->apellidos) ?: $this->requester->email;
        $cotizacion = $this->modificacion->cotizacion;
        $numero = $cotizacion?->numero ?: 'Cotizacion #'.$this->modificacion->cotizacion_id;

        return [
            'title' => 'Solicitud de modificacion',
            'description' => sprintf('%s solicito modificar la cotizacion %s.', $requesterName, $numero),
            'message' => sprintf('%s solicito modificar la cotizacion %s a las %s.', $requesterName, $numero, $requestedAt->format('H:i')),
            'action_url' => "/cotizaciones/modificaciones/{$this->modificacion->id}/edit",
            'cotizacion_id' => $this->modificacion->cotizacion_id,
            'cotizacion_numero' => $cotizacion?->numero,
            'modificacion_id' => $this->modificacion->id,
            'version_number' => $this->modificacion->version_number,
            'action' => 'modificacion_solicitada',
            'requested_by_id' => $this->requester->id,
            'requested_by_name' => $requesterName,
            'requested_at' => $requestedAt->toDateTimeString(),
            'motivo' => $this->modificacion->motivo,
        ];
    }
}
