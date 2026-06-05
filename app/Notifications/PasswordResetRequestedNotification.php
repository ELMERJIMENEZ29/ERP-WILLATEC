<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PasswordResetRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected User $user
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => trim($this->user->nombres.' '.$this->user->apellidos),
            'message' => sprintf('%s solicitó restablecer su contraseña.', trim($this->user->nombres.' '.$this->user->apellidos)),
            'requested_at' => now()->toDateTimeString(),
        ];
    }
}
