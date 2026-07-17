<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;

trait Auditable
{
    public function getActivitylogOptions(): LogOptions
    {
        $options = LogOptions::defaults()
            ->useLogName('auditoria')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName): string => $this->auditDescription($eventName));

        if (property_exists($this, 'auditOnly') && is_array($this->auditOnly)) {
            return $options->logOnly($this->auditOnly);
        }

        return $options->logFillable();
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
