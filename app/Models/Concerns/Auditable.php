<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;

trait Auditable
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('auditoria')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => $this->auditDescription($eventName));
    }

    protected function auditDescription(string $eventName): string
    {
        $modelName = $this->auditModelName();

        return match ($eventName) {
            'created' => "{$modelName} creado",
            'updated' => "{$modelName} actualizado",
            'deleted' => "{$modelName} eliminado",
            default => "{$modelName} {$eventName}",
        };
    }

    protected function auditModelName(): string
    {
        return class_basename($this);
    }
}
