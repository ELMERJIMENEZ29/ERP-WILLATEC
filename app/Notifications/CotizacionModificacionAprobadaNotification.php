<?php

namespace App\Notifications;

use App\Models\CotizacionModificacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionModificacionAprobadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CotizacionModificacion $modificacion,
        protected User $approver
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $approvedAt = now('America/Lima');
        $approverName = trim($this->approver->nombres.' '.$this->approver->apellidos) ?: $this->approver->email;
        $cotizacion = $this->modificacion->cotizacion;
        $numero = $cotizacion?->numero ?: 'Cotizacion #'.$this->modificacion->cotizacion_id;

        return [
            'title' => 'Version aprobada',
            'description' => sprintf('La version V%s de la cotizacion %s fue aprobada por %s.', $this->modificacion->version_number, $numero, $approverName),
            'message' => sprintf('La version V%s de la cotizacion %s fue aprobada por %s a las %s.', $this->modificacion->version_number, $numero, $approverName, $approvedAt->format('H:i')),
            'action_url' => "/cotizaciones/{$this->modificacion->cotizacion_id}/view",
            'cotizacion_id' => $this->modificacion->cotizacion_id,
            'cotizacion_numero' => $cotizacion?->numero,
            'modificacion_id' => $this->modificacion->id,
            'version_number' => $this->modificacion->version_number,
            'action' => 'modificacion_aprobada',
            'approved_by_id' => $this->approver->id,
            'approved_by_name' => $approverName,
            'approved_at' => $approvedAt->toDateTimeString(),
        ];
    }
}
