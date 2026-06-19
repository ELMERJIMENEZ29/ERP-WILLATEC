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
        $rejectedAt = now('America/Lima');
        $approverName = trim($this->approver->nombres.' '.$this->approver->apellidos) ?: $this->approver->email;
        $numero = $this->cotizacion->numero ?: 'Cotizacion #'.$this->cotizacion->id;

        return [
            'title' => 'Cotizacion rechazada',
            'description' => sprintf(
                'La cotizacion %s fue rechazada por %s%s.',
                $numero,
                $approverName,
                $this->reason ? ': '.$this->reason : ''
            ),
            'action_url' => "/cotizaciones/{$this->cotizacion->id}/view",
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_numero' => $this->cotizacion->numero,
            'action' => 'rechazada',
            'message' => sprintf(
                'La cotizacion %s fue rechazada por %s a las %s.',
                $numero,
                $approverName,
                $rejectedAt->format('H:i')
            ),
            'rejected_by_id' => $this->approver->id,
            'rejected_by_name' => $approverName,
            'rejected_at' => $rejectedAt->toDateTimeString(),
            'reason' => $this->reason,
        ];
    }
}
