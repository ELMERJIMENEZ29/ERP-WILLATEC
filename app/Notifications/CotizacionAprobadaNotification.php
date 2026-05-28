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
        return [
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_numero' => $this->cotizacion->numero,
            'action' => 'aprobada',
            'message' => sprintf(
                'Cotización aprobada por %s a las %s.',
                trim($this->approver->nombres.' '.$this->approver->apellidos),
                now()->format('H:i')
            ),
            'approved_by_id' => $this->approver->id,
            'approved_by_name' => trim($this->approver->nombres.' '.$this->approver->apellidos),
            'approved_at' => now()->toDateTimeString(),
        ];
    }
}
