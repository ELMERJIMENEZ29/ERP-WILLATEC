<?php

namespace App\Notifications;

use App\Models\OcEmitida;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OcEmitidaRegistradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected OcEmitida $ocEmitida,
        protected User $emisor
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $issuedAt = now('America/Lima');
        $emisorName = trim($this->emisor->nombres.' '.$this->emisor->apellidos) ?: $this->emisor->email;
        $roles = $this->emisor->roles->pluck('name')->implode(', ') ?: 'sin rol';
        $cotizacion = $this->ocEmitida->cotizacion;
        $numero = $cotizacion?->numero ?: 'Cotizacion #'.$this->ocEmitida->cotizacion_id;

        return [
            'title' => 'OC emitida',
            'description' => sprintf(
                '%s (%s) emitio una OC para el proveedor %s correspondiente a la cotizacion %s.',
                $emisorName,
                $roles,
                $this->ocEmitida->proveedor,
                $numero
            ),
            'message' => sprintf(
                '%s (%s) emitio una OC para el proveedor %s correspondiente a la cotizacion %s a las %s.',
                $emisorName,
                $roles,
                $this->ocEmitida->proveedor,
                $numero,
                $issuedAt->format('H:i')
            ),
            'action_url' => "/ordenes-compra/emitidas/{$this->ocEmitida->id}",
            'action' => 'oc_emitida_registrada',
            'oc_emitida_id' => $this->ocEmitida->id,
            'oc_emitida_numero' => $this->ocEmitida->numero,
            'proveedor' => $this->ocEmitida->proveedor,
            'cotizacion_id' => $this->ocEmitida->cotizacion_id,
            'cotizacion_numero' => $cotizacion?->numero,
            'issued_by_id' => $this->emisor->id,
            'issued_by_name' => $emisorName,
            'issued_by_roles' => $roles,
            'issued_at' => $issuedAt->toDateTimeString(),
        ];
    }
}
