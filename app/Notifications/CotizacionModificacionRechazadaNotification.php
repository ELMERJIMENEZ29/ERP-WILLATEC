<?php

namespace App\Notifications;

use App\Models\CotizacionModificacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionModificacionRechazadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CotizacionModificacion $modificacion,
        protected User $reviewer,
        protected ?string $reason = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $rejectedAt = now('America/Lima');
        $reviewerName = trim($this->reviewer->nombres.' '.$this->reviewer->apellidos) ?: $this->reviewer->email;
        $cotizacion = $this->modificacion->cotizacion;
        $numero = $cotizacion?->numero ?: 'Cotizacion #'.$this->modificacion->cotizacion_id;

        return [
            'title' => 'Version rechazada',
            'description' => sprintf(
                'La version V%s de la cotizacion %s fue rechazada por %s%s.',
                $this->modificacion->version_number,
                $numero,
                $reviewerName,
                $this->reason ? ': '.$this->reason : ''
            ),
            'message' => sprintf('La version V%s de la cotizacion %s fue rechazada por %s a las %s.', $this->modificacion->version_number, $numero, $reviewerName, $rejectedAt->format('H:i')),
            'action_url' => "/cotizaciones/modificaciones/{$this->modificacion->id}/edit",
            'cotizacion_id' => $this->modificacion->cotizacion_id,
            'cotizacion_numero' => $cotizacion?->numero,
            'modificacion_id' => $this->modificacion->id,
            'version_number' => $this->modificacion->version_number,
            'action' => 'modificacion_rechazada',
            'rejected_by_id' => $this->reviewer->id,
            'rejected_by_name' => $reviewerName,
            'rejected_at' => $rejectedAt->toDateTimeString(),
            'reason' => $this->reason,
        ];
    }
}
