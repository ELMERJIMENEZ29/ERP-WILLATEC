<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionAprobadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Cotizacion $cotizacion,
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
        $numero = $this->cotizacion->numero ?: 'Cotizacion #'.$this->cotizacion->id;

        return [
            'title' => 'Cotizacion aprobada',
            'description' => sprintf('La cotizacion %s fue aprobada por %s.', $numero, $approverName),
            'action_url' => "/cotizaciones/{$this->cotizacion->id}/view",
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_numero' => $this->cotizacion->numero,
            'action' => 'aprobada',
            'message' => sprintf(
                'La cotizacion %s fue aprobada por %s a las %s.',
                $numero,
                $approverName,
                $approvedAt->format('H:i')
            ),
            'approved_by_id' => $this->approver->id,
            'approved_by_name' => $approverName,
            'approved_at' => $approvedAt->toDateTimeString(),
        ];
    }
}
