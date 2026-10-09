<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\WorkflowEventNotification;

class WorkflowNotificationService
{
    public function compras(string $title, string $description, string $url, string $action, array $metadata = []): void
    {
        $this->send(['superadmin', 'admin', 'logistica', 'ventas'], $title, $description, $url, $action, $metadata);
    }

    public function contabilidad(string $title, string $description, string $url, string $action, array $metadata = []): void
    {
        $this->send(['superadmin', 'admin', 'contabilidad'], $title, $description, $url, $action, $metadata);
    }

    private function send(array $roles, string $title, string $description, string $url, string $action, array $metadata): void
    {
        User::role($roles)->get()->each->notify(
            new WorkflowEventNotification($title, $description, $url, $action, $metadata)
        );
    }
}
