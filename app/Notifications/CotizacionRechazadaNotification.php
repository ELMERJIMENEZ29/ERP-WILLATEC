<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionRechazadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Cotizacion $cotizacion,
        protected User $approver,
        protected ?string $reason = null
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
            'action' => 'rechazada',
            'message' => sprintf(
                'Cotización rechazada por %s a las %s.',
                trim($this->approver->nombres.' '.$this->approver->apellidos),
                now()->format('H:i')
            ),
            'rejected_by_id' => $this->approver->id,
            'rejected_by_name' => trim($this->approver->nombres.' '.$this->approver->apellidos),
            'rejected_at' => now()->toDateTimeString(),
            'reason' => $this->reason,
        ];
    }
}
