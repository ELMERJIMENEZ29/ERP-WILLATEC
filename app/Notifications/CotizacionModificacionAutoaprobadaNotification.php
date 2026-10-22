<?php

namespace App\Notifications;

use App\Models\CotizacionModificacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionModificacionAutoaprobadaNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected CotizacionModificacion $modificacion,
        protected User $solicitante
    ){}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $cotizacion = $this->modificacion->cotizacion;
        $numero = $cotizacion->numero ?: 'Cotización #'.$this->modificacion->cotizacion_id;

        $nombre = trim(
            $this->solicitante->nombres.''.$this->solicitante->apellidos
        ) ?: $this->solicitante->email;
        return [
            'title' => 'Modificación de cotización autoaprobada',
            'description' => sprintf(
                '%s modificó la cotización %s. La version V%s fue aprobada automáticamente.',
                $nombre,
                $numero,
                $this->modificacion->version_number
            ),
            'action' => 'modificacion_autoaprobada',
            'action_url' => "/cotizaciones/{$this->modificacion->cotizacion_id}/view",
            'cotizacion_id' => $this->modificacion->cotizacion_id,
            'cotizacion_numero' =>$cotizacion?->numero,
            'modificacion_id' => $this->modificacion->id,
            'version_number' => $this->modificacion->version_number,
            'motivo' => $this->modificacion->motivo,
            'requestd_by_id' => $this->solicitante->id,
            'requestd_by_name' => $nombre,
            'approval_type' => 'automatic',
            'approved-at' => $this->modificacion->reviewed_at?->toDateTimeString(),
        ];
    }
}
