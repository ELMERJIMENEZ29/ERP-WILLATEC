<?php

namespace App\Notifications;

use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CotizacionEnviadaRevisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Cotizacion $cotizacion,
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
        $numero = $this->cotizacion->numero ?: 'Cotizacion #'.$this->cotizacion->id;

        return [
            'title' => 'Cotizacion enviada para aprobacion',
            'description' => sprintf('%s envio la cotizacion %s para aprobacion.', $senderName, $numero),
            'message' => sprintf('%s envio la cotizacion %s para aprobacion a las %s.', $senderName, $numero, $sentAt->format('H:i')),
            'action_url' => "/cotizaciones/{$this->cotizacion->id}/view",
            'cotizacion_id' => $this->cotizacion->id,
            'cotizacion_numero' => $this->cotizacion->numero,
            'action' => 'enviada_revision',
            'sent_by_id' => $this->sender->id,
            'sent_by_name' => $senderName,
            'sent_at' => $sentAt->toDateTimeString(),
        ];
    }
}
