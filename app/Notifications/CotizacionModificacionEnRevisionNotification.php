<?php

namespace App\Notifications;

use App\Models\CotizacionModificacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionModificacionEnRevisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected CotizacionModificacion $modificacion,
        protected User $sender
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $sentAt = now('America/Lima');
        $senderName = trim($this->sender->nombres.' '.$this->sender->apellidos) ?: $this->sender->email;
        $cotizacion = $this->modificacion->cotizacion;
        $numero = $cotizacion?->numero ?: 'Cotizacion #'.$this->modificacion->cotizacion_id;

        return [
            'title' => 'Modificacion enviada a revision',
            'description' => sprintf('%s envio una modificacion de la cotizacion %s para revision.', $senderName, $numero),
            'message' => sprintf('%s envio una modificacion de la cotizacion %s para revision a las %s.', $senderName, $numero, $sentAt->format('H:i')),
            'action_url' => "/cotizaciones/modificaciones/{$this->modificacion->id}/edit",
            'cotizacion_id' => $this->modificacion->cotizacion_id,
            'cotizacion_numero' => $cotizacion?->numero,
            'modificacion_id' => $this->modificacion->id,
            'version_number' => $this->modificacion->version_number,
            'action' => 'modificacion_en_revision',
            'sent_by_id' => $this->sender->id,
            'sent_by_name' => $senderName,
            'sent_at' => $sentAt->toDateTimeString(),
        ];
    }
}
