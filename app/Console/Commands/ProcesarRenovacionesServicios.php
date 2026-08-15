<?php

namespace App\Console\Commands;

use App\Models\Hosting;
use App\Models\Licencia;
use App\Models\User;
use App\Notifications\ServicioRenovacionNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ProcesarRenovacionesServicios extends Command
{
    protected $signature = 'servicios:procesar-renovaciones {--dry-run}';

    protected $description = 'Procesa renovaciones programadas de licencias y hosting';

    public function handle(): int
    {
        $today = Carbon::today('America/Lima');
        $dryRun = (bool) $this->option('dry-run');
        $renewed = 0;

        if (Schema::hasTable('licencias')) {
            Licencia::query()
                ->where('renovacion_programada', true)
                ->whereDate('renovacion_programada_para', '<=', $today)
                ->chunkById(100, function ($licencias) use ($dryRun, &$renewed): void {
                    foreach ($licencias as $licencia) {
                        if ($dryRun) {
                            $this->line("Licencia {$licencia->id} lista para renovar.");
                            continue;
                        }

                        $this->renovarLicencia($licencia);
                        $renewed++;
                    }
                });
        } else {
            $this->warn('Tabla licencias no encontrada. Se omitio el procesamiento de licencias.');
        }

        if (Schema::hasTable('hostings')) {
            Hosting::query()
                ->where('renovacion_programada', true)
                ->whereDate('renovacion_programada_para', '<=', $today)
                ->chunkById(100, function ($hostings) use ($dryRun, &$renewed): void {
                    foreach ($hostings as $hosting) {
                        if ($dryRun) {
                            $this->line("Hosting {$hosting->id} listo para renovar.");
                            continue;
                        }

                        $this->renovarHosting($hosting);
                        $renewed++;
                    }
                });
        } else {
            $this->warn('Tabla hostings no encontrada. Se omitio el procesamiento de hosting.');
        }

        $this->info("Renovaciones procesadas: {$renewed}");

        return self::SUCCESS;
    }

    private function renovarLicencia(Licencia $licencia): void
    {
        $meses = $this->resolveMeses($licencia->renovacion_modo, $licencia->renovacion_meses);
        $inicio = Carbon::parse($licencia->renovacion_programada_para);
        $fin = $inicio->copy()->addMonthsNoOverflow($meses)->subDay();

        $licencia->update([
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_renovacion' => $fin->toDateString(),
            'suscripcion_meses' => $meses,
            'renovacion_programada' => false,
            'renovacion_modo' => null,
            'renovacion_meses' => null,
            'renovacion_programada_para' => null,
            'renovacion_programada_at' => null,
            'renovacion_programada_por' => null,
        ]);

        $licencia->alertasEnviadas()->delete();

        $this->notifyAdmins(new ServicioRenovacionNotification(
            'licencia',
            $licencia->id,
            'Licencia renovada automaticamente',
            "Segun la programacion, se renovo la licencia {$licencia->producto} de {$licencia->empresa}.",
            '/servicios/licencias',
            [
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_renovacion' => $fin->toDateString(),
                'renovacion_meses' => $meses,
            ]
        ));
    }

    private function renovarHosting(Hosting $hosting): void
    {
        $modo = $hosting->renovacion_modo === 'MENSUAL' ? 'MENSUAL' : 'ANUAL';
        $meses = $this->resolveMeses($modo, $hosting->renovacion_meses);
        $inicio = Carbon::parse($hosting->renovacion_programada_para);
        $fin = $inicio->copy()->addMonthsNoOverflow($meses)->subDay();

        $hosting->update([
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_renovacion' => $fin->toDateString(),
            'suscripcion' => $modo,
            'renovacion_programada' => false,
            'renovacion_modo' => null,
            'renovacion_meses' => null,
            'renovacion_programada_para' => null,
            'renovacion_programada_at' => null,
            'renovacion_programada_por' => null,
        ]);

        $this->notifyAdmins(new ServicioRenovacionNotification(
            'hosting',
            $hosting->id,
            'Hosting renovado automaticamente',
            "Segun la programacion, se renovo el hosting {$hosting->dominio} de {$hosting->empresa}.",
            '/servicios/hosting',
            [
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_renovacion' => $fin->toDateString(),
                'renovacion_meses' => $meses,
            ]
        ));
    }

    private function resolveMeses(?string $modo, ?int $meses): int
    {
        return $modo === 'MENSUAL' ? max(1, (int) $meses) : 12;
    }

    private function notifyAdmins(ServicioRenovacionNotification $notification): void
    {
        User::role(['superadmin', 'admin'])->get()->each->notify($notification);
    }
}
