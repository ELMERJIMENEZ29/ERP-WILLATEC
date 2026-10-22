<?php

namespace App\Console\Commands;

use App\Models\Licitacion;
use App\Models\User;
use App\Notifications\OportunidadVencimientoNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class EnviarAlertasOportunidades extends Command
{
    protected $signature = 'oportunidades:enviar-alertas {--dry-run : Simula el proceso sin crear notificaciones}';

    protected $description = 'Envia notificaciones automaticas por oportunidades proximas a vencer o vencidas';

    private const ACTIVE_STATES = ['sin_atender', 'en_atencion', 'cotizacion_generada'];

    private const TRACKED_STATES = ['sin_atender', 'en_atencion', 'cotizacion_generada', 'vencida'];

    private const REMINDERS = [
        ['key' => '10m', 'minutes' => 10, 'label' => '10 minutos'],
        ['key' => '1h', 'minutes' => 60, 'label' => '1 hora'],
        ['key' => '24h', 'minutes' => 1440, 'label' => '24 horas'],
    ];

    public function handle(): int
    {
        $now = Carbon::now('America/Lima');
        $dryRun = (bool) $this->option('dry-run');
        $users = User::query()->where('activo', true)->get();
        $sent = 0;
        $skipped = 0;

        if ($users->isEmpty()) {
            $this->warn('No hay usuarios activos para notificar.');

            return self::SUCCESS;
        }

        Licitacion::query()
            ->whereIn('estado', self::TRACKED_STATES)
            ->whereNotNull('vigencia')
            ->where('vigencia', '<=', $now->copy()->addDay())
            ->orderBy('vigencia')
            ->chunkById(100, function ($oportunidades) use ($now, $dryRun, $users, &$sent, &$skipped): void {
                foreach ($oportunidades as $oportunidad) {
                    $vigencia = Carbon::parse($oportunidad->vigencia, 'America/Lima');
                    $minutesRemaining = $now->diffInMinutes($vigencia, false);

                    if ($minutesRemaining <= 0) {
                        if (! $dryRun && $oportunidad->estado !== 'vencida') {
                            $oportunidad->forceFill([
                                'estado' => 'vencida',
                                'modificado_por' => 'Sistema',
                                'modificado_en' => $now,
                            ])->save();
                        }

                        if ($this->alreadyNotified($oportunidad->id, 'vencida')) {
                            $skipped++;

                            continue;
                        }

                        if (! $dryRun) {
                            Notification::send(
                                $users,
                                new OportunidadVencimientoNotification($oportunidad->refresh(), 'vencida', 'vencida', true)
                            );
                        }

                        $sent++;

                        continue;
                    }

                    $reminder = collect(self::REMINDERS)->first(
                        fn (array $item) => $minutesRemaining <= $item['minutes']
                    );

                    if (! $reminder) {
                        $skipped++;

                        continue;
                    }

                    if ($this->alreadyNotified($oportunidad->id, $reminder['key'])) {
                        $skipped++;

                        continue;
                    }

                    if (! $dryRun) {
                        Notification::send(
                            $users,
                            new OportunidadVencimientoNotification(
                                $oportunidad,
                                $reminder['key'],
                                $reminder['label'],
                            )
                        );
                    }

                    $sent++;
                }
            });

        $mode = $dryRun ? 'simulados' : 'enviados';
        $this->info("Recordatorios {$mode}: {$sent}");
        $this->info("Oportunidades omitidas: {$skipped}");

        return self::SUCCESS;
    }

    private function alreadyNotified(int $licitacionId, string $reminderKey): bool
    {
        return DB::table('notifications')
            ->where('type', OportunidadVencimientoNotification::class)
            ->where('data', 'like', '%"licitacion_id":'.$licitacionId.'%')
            ->where('data', 'like', '%"reminder_key":"'.$reminderKey.'"%')
            ->exists();
    }
}
