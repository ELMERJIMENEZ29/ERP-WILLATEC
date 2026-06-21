<?php

namespace App\Notifications;

use App\Models\OcRecibida;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OcRecibidaRegistradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OcRecibida $ocRecibida,
        protected User $registrador
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $registeredAt = now('America/Lima');
        $registradorName = trim($this->registrador->nombres.' '.$this->registrador->apellidos) ?: $this->registrador->email;
        $roles = $this->registrador->roles->pluck('name')->implode(', ') ?: 'sin rol';
        $cotizacion = $this->ocRecibida->cotizacion;
        $numero = $cotizacion?->numero ?: 'Cotizacion #'.$this->ocRecibida->cotizacion_id;

        return [
            'title' => 'OC recibida registrada',
            'description' => sprintf(
                '%s (%s) registro una OC recibida correspondiente a la cotizacion %s.',
                $registradorName,
                $roles,
                $numero
            ),
            'message' => sprintf(
                '%s (%s) registro una OC recibida correspondiente a la cotizacion %s a las %s.',
                $registradorName,
                $roles,
                $numero,
                $registeredAt->format('H:i')
            ),
            'action_url' => "/ordenes-compra/recibidas/{$this->ocRecibida->id}",
            'action' => 'oc_recibida_registrada',
            'oc_recibida_id' => $this->ocRecibida->id,
            'oc_recibida_numero' => $this->ocRecibida->numero,
            'cotizacion_id' => $this->ocRecibida->cotizacion_id,
            'cotizacion_numero' => $cotizacion?->numero,
            'registered_by_id' => $this->registrador->id,
            'registered_by_name' => $registradorName,
            'registered_by_roles' => $roles,
            'registered_at' => $registeredAt->toDateTimeString(),
        ];
    }
}
